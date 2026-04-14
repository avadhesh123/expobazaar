<?php

namespace App\Services;

use App\Models\{Shipment, Consignment, Asn, Grn, GrnItem, Inventory, InventoryMovement, WarehouseCharge, Warehouse, Product, LiveSheet, ActivityLog, User};
use App\Notifications\{ShipmentNotification, AsnNotification, GrnNotification, WarehouseNotification};
use Illuminate\Support\Facades\{DB, Notification};

class LogisticsService
{
    /**
     * Consolidate consignments into a shipment
     */
    public function createShipment(array $consignmentIds, string $shipmentType, string $companyCode, array $data = []): Shipment
    {
        return DB::transaction(function () use ($consignmentIds, $shipmentType, $companyCode, $data) {
            $consignments = Consignment::whereIn('id', $consignmentIds)->with('liveSheet')->get();
            $totalCbm = $consignments->sum('total_cbm');
            $totalItems = $consignments->sum('total_items');
            $totalValue = $consignments->sum('total_value');
            $country = $consignments->first()->destination_country;

            $capacity = $shipmentType === 'FCL' ? 65 : ($shipmentType === 'LCL' ? 30 : 10);

            $shipment = Shipment::create([
                'shipment_code' => Shipment::generateCode($companyCode, $shipmentType),
                'company_code' => $companyCode,
                'destination_country' => $country,
                'shipment_type' => $shipmentType,
                'status' => 'consolidated',
                'total_cbm' => $totalCbm,
                'capacity_cbm' => $capacity,
                'total_items' => $totalItems,
                'total_value' => $totalValue,
                'container_number' => $data['container_number'] ?? null,
                'container_size' => $data['container_size'] ?? null,
                'port_of_loading' => $data['port_of_loading'] ?? null,
                'port_of_discharge' => $data['port_of_discharge'] ?? null,
                'destination_warehouse_id' => $data['warehouse_id'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Attach consignments and update their status
            foreach ($consignments as $consignment) {
                $shipment->consignments()->attach($consignment->id, [
                    'cbm' => $consignment->total_cbm,
                    'items' => $consignment->total_items,
                ]);
                // Mark as in shipment — no longer available for container planning
                $consignment->update(['status' => 'in_shipment']);
            }

            // Capacity warning
            if ($shipment->isOverCapacity()) {
                $logistics = User::internal()->byDepartment('logistics')->active()->get();
                Notification::send($logistics, new ShipmentNotification($shipment, 'capacity_warning'));
            }

            ActivityLog::log('created', 'shipment', $shipment, null, null, 'Shipment consolidated');
            return $shipment;
        });
    }

    /**
     * Update sailing date and lock shipment
     */
    public function lockShipment(Shipment $shipment, array $data, User $user): Shipment
    {
        return DB::transaction(function () use ($shipment, $data, $user) {
            $shipment->update([
                'sailing_date' => $data['sailing_date'],
                'eta_date' => $data['eta_date'] ?? null,
                'shipping_line' => $data['shipping_line'] ?? null,
                'vessel_name' => $data['vessel_name'] ?? null,
                'voyage_number' => $data['voyage_number'] ?? null,
                'bill_of_lading' => $data['bill_of_lading'] ?? null,
                'status' => 'locked',
                'locked_by' => $user->id,
                'locked_at' => now(),
            ]);

            // Auto-generate ASN
            $asn = $this->generateAsn($shipment);

            ActivityLog::log('locked', 'shipment', $shipment, null, null, 'Shipment locked and ASN generated');

            // Notify HOD for pricing
            $hod = User::internal()->byDepartment('hod')->active()->get();
            Notification::send($hod, new AsnNotification($asn, 'pricing_required'));

            // Notify vendors
            foreach ($shipment->consignments as $consignment) {
                $consignment->vendor->user->notify(new ShipmentNotification($shipment, 'sailing_date_updated'));
            }

            return $shipment;
        });
    }

    /**
     * Auto-generate ASN from locked shipment
     */
    public function generateAsn(Shipment $shipment): Asn
    {
        $items = [];
        foreach ($shipment->consignments as $consignment) {
            if ($consignment->liveSheet) {
                foreach ($consignment->liveSheet->items as $item) {
                    $items[] = [
                        'product_id' => $item->product_id,
                        'sku' => $item->product->sku,
                        'name' => $item->product->name,
                        'quantity' => $item->quantity,
                        'cbm' => $item->total_cbm,
                        'vendor_id' => $consignment->vendor_id,
                        'consignment_id' => $consignment->id,
                    ];
                }
            }
        }

        return Asn::create([
            'asn_number' => Asn::generateNumber($shipment->shipment_code),
            'shipment_id' => $shipment->id,
            'company_code' => $shipment->company_code,
            'status' => 'generated',
            'items' => $items,
            'total_cbm' => $shipment->total_cbm,
            'total_items' => $shipment->total_items,
            'generated_by' => auth()->id(),
            'generated_at' => now(),
        ]);
    }

    /**
     * Upload GRN
     */
    public function uploadGrn(Shipment $shipment, array $data, array $items): Grn
    {
        return DB::transaction(function () use ($shipment, $data, $items) {
            $grn = Grn::create([
                'grn_number' => Grn::generateNumber($shipment->shipment_code),
                'shipment_id' => $shipment->id,
                'warehouse_id' => $data['warehouse_id'],
                'company_code' => $shipment->company_code,
                'receipt_date' => $data['receipt_date'],
                'status' => 'uploaded',
                'total_items_expected' => collect($items)->sum('expected_quantity'),
                'total_items_received' => collect($items)->sum('received_quantity'),
                'damaged_items' => collect($items)->sum('damaged_quantity'),
                'missing_items' => collect($items)->sum('missing_quantity'),
                'grn_file' => $data['grn_file'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'uploaded_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                GrnItem::create([
                    'grn_id' => $grn->id,
                    'product_id' => $item['product_id'],
                    'consignment_id' => $item['consignment_id'] ?? null,
                    'expected_quantity' => $item['expected_quantity'],
                    'received_quantity' => $item['received_quantity'],
                    'damaged_quantity' => $item['damaged_quantity'] ?? 0,
                    'missing_quantity' => $item['missing_quantity'] ?? 0,
                    'remarks' => $item['remarks'] ?? null,
                ]);

                // Add to inventory
                $this->addToInventory($item['product_id'], $data['warehouse_id'], $shipment->company_code, $item['received_quantity'], $grn, $item['consignment_id'] ?? null);
            }

            $shipment->update(['status' => 'grn_completed']);

            ActivityLog::log('uploaded', 'grn', $grn, null, null, 'GRN uploaded');

            // Notify vendor, cataloguing, finance
            foreach ($shipment->consignments as $consignment) {
                $consignment->vendor->user->notify(new GrnNotification($grn, 'received'));
            }

            $cataloguing = User::internal()->byDepartment('cataloguing')->active()->get();
            Notification::send($cataloguing, new GrnNotification($grn, 'inventory_available'));

            return $grn;
        });
    }

    /**
     * Add received goods to inventory
     */
    protected function addToInventory(int $productId, int $warehouseId, string $companyCode, int $quantity, Grn $grn, ?int $consignmentId): void
    {
        $inventory = Inventory::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId, 'company_code' => $companyCode],
            ['quantity' => 0, 'reserved_quantity' => 0, 'available_quantity' => 0, 'received_date' => now()]
        );

        $inventory->increment('quantity', $quantity);
        $inventory->increment('available_quantity', $quantity);
        $inventory->update(['grn_id' => $grn->id, 'consignment_id' => $consignmentId]);

        // Update product stock
        Product::where('id', $productId)->increment('stock_quantity', $quantity);

        // Record movement
        InventoryMovement::create([
            'product_id' => $productId,
            'movement_type' => 'inward',
            'to_warehouse_id' => $warehouseId,
            'quantity' => $quantity,
            'reference_type' => 'grn',
            'reference_id' => $grn->id,
            'performed_by' => auth()->id(),
        ]);
    }

    /**
     * Transfer inventory between warehouses/sub-locations
     */
    public function transferInventory(int $productId, int $fromWarehouseId, int $toWarehouseId, int $quantity, ?int $fromSubId = null, ?int $toSubId = null): void
    {
        DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $quantity, $fromSubId, $toSubId) {
            // Decrease from source
            $source = Inventory::where('product_id', $productId)->where('warehouse_id', $fromWarehouseId)->firstOrFail();
            $source->decrement('quantity', $quantity);
            $source->decrement('available_quantity', $quantity);

            // Increase at destination
            $dest = Inventory::firstOrCreate(
                ['product_id' => $productId, 'warehouse_id' => $toWarehouseId, 'company_code' => $source->company_code],
                ['quantity' => 0, 'reserved_quantity' => 0, 'available_quantity' => 0]
            );
            $dest->increment('quantity', $quantity);
            $dest->increment('available_quantity', $quantity);
            if ($toSubId) $dest->update(['warehouse_sub_location_id' => $toSubId]);

            InventoryMovement::create([
                'product_id' => $productId,
                'movement_type' => 'transfer',
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'from_sub_location_id' => $fromSubId,
                'to_sub_location_id' => $toSubId,
                'quantity' => $quantity,
                'reference_type' => 'transfer',
                'performed_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Calculate monthly warehouse charges for a vendor
     */
    public function calculateWarehouseCharges(int $vendorId, int $month, int $year, int $warehouseId): array
    {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $inventory = Inventory::where('warehouse_id', $warehouseId)
            ->whereHas('product', fn($q) => $q->where('vendor_id', $vendorId))
            ->get();

        $totalCbm = $inventory->sum(fn($inv) => $inv->product->cbm * $inv->quantity);

        $charges = [];

        // Inward charges
        $charges[] = WarehouseCharge::create([
            'warehouse_id' => $warehouseId,
            'vendor_id' => $vendorId,
            'company_code' => $warehouse->company_code,
            'charge_month' => $month,
            'charge_year' => $year,
            'charge_type' => 'inward',
            'calculated_amount' => $totalCbm * $warehouse->inward_rate_per_cbm,
            'status' => 'calculated',
        ]);

        // Storage charges
        $charges[] = WarehouseCharge::create([
            'warehouse_id' => $warehouseId,
            'vendor_id' => $vendorId,
            'company_code' => $warehouse->company_code,
            'charge_month' => $month,
            'charge_year' => $year,
            'charge_type' => 'storage',
            'calculated_amount' => $totalCbm * $warehouse->storage_rate_per_cbm_month,
            'status' => 'calculated',
        ]);

        return $charges;
    }
}
