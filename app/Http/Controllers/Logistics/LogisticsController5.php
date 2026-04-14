<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{Shipment, Consignment, Grn, GrnItem, Inventory, InventoryMovement, WarehouseCharge, Warehouse, LiveSheet};
use App\Services\{DashboardService, LogisticsService};
use Illuminate\Http\Request;

class LogisticsController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected LogisticsService $logisticsService
    ) {}

    public function dashboard(Request $request)
    {
        $companyCode = $request->get('company_code');
        $data = $this->dashboardService->getLogisticsDashboard($companyCode ?? '2100');
        return view('logistics.dashboard', compact('data', 'companyCode'));
    }

    // ─── CONTAINER PLANNING ──────────────────────────────────────
    public function containerPlanning(Request $request)
    {
        $consignments = Consignment::with('vendor', 'liveSheet')
            ->where('status', 'created')
            ->whereDoesntHave('shipments')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->get();
        $totalCbm = $consignments->sum('total_cbm');
        return view('logistics.container-planning', compact('consignments', 'totalCbm'));
    }

    public function createShipment(Request $request)
    {
        $request->validate([
            'consignment_ids' => 'required|array|min:1',
            'shipment_type'   => 'required|in:FCL,LCL,AIR',
            'company_code'    => 'required|in:2000,2100,2200',
        ]);
        $shipment = $this->logisticsService->createShipment($request->consignment_ids, $request->shipment_type, $request->company_code, $request->all());
        return redirect()->route('logistics.shipments')->with('success', 'Shipment created. Code: ' . $shipment->shipment_code);
    }

    // ─── SHIPMENTS ───────────────────────────────────────────────
    public function shipments(Request $request)
    {
        $shipments = Shipment::with('consignments.vendor')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->type, fn($q, $v) => $q->where('shipment_type', $v))
            ->latest()->paginate(20);
        return view('logistics.shipments.index', compact('shipments'));
    }

    public function showShipment(Shipment $shipment)
    {
        $shipment->load('consignments.vendor', 'consignments.liveSheet');
        $asn = \App\Models\Asn::where('shipment_id', $shipment->id)->first();
        $grn = Grn::where('shipment_id', $shipment->id)->first();
        return view('logistics.shipments.show', compact('shipment', 'asn', 'grn'));
    }

    public function lockShipment(Request $request, Shipment $shipment)
    {
        $request->validate(['sailing_date' => 'required|date']);
        $this->logisticsService->lockShipment($shipment, $request->all(), auth()->user());
        return redirect()->route('logistics.shipments')->with('success', 'Shipment locked. ASN generated.');
    }

    // ─── GRN ─────────────────────────────────────────────────────
    public function grnList(Request $request)
    {
        $grns = Grn::with('shipment', 'warehouse')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn($q, $v) => $q->where('warehouse_id', $v))
            ->latest()->paginate(20);

        $pendingShipments = Shipment::whereIn('status', ['arrived', 'grn_pending', 'locked', 'asn_generated', 'in_transit', 'consolidated'])
            ->whereDoesntHave('grn')
            ->with('consignments.vendor', 'warehouse')
            ->latest()->get();

        $warehouses = Warehouse::active()->get();
        return view('logistics.grn.index', compact('grns', 'pendingShipments', 'warehouses'));
    }

    public function showGrn(Grn $grn)
    {
        $grn->load('shipment.consignments.vendor', 'warehouse', 'items.product');
        $ageing = now()->diffInDays($grn->receipt_date);
        return view('logistics.grn.show', compact('grn', 'ageing'));
    }

    public function uploadGrn(Shipment $shipment)
    {
        $shipment->load('consignments.liveSheet.items.product');
        $warehouses = Warehouse::active()->get();
        return view('logistics.grn.upload', compact('shipment', 'warehouses'));
    }

    public function storeGrn(Request $request, Shipment $shipment)
    {
        $request->validate([
            'warehouse_id'             => 'required|exists:warehouses,id',
            'receipt_date'             => 'required|date',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required|exists:products,id',
            'items.*.expected_quantity' => 'required|integer|min:0',
            'items.*.received_quantity' => 'required|integer|min:0',
        ]);
        $this->logisticsService->uploadGrn($shipment, $request->all(), $request->items);
        return redirect()->route('logistics.grn')->with('success', 'GRN uploaded. Inventory updated automatically.');
    }

    // ─── ASN ─────────────────────────────────────────────────────
    public function downloadAsn(\App\Models\Asn $asn)
    {
        $asn->load('shipment.consignments.vendor');
        $items = $asn->items ?? [];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('ASN');

        $headers = ['Vendors Name', 'SKU', 'ITEM NAME', 'Pack Type', 'Expected Pack Qty', 'Received Package Quantity', 'EXTRA', 'Difference'];
        foreach ($headers as $col => $header) {
            $cell = chr(65 + $col) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        $row = 2;
        foreach ($items as $item) {
            $vendorName = $item['vendor_name'] ?? '';
            if (empty($vendorName) && isset($item['vendor_id'])) {
                $vendorName = \App\Models\Vendor::find($item['vendor_id'])->company_name ?? '';
            }
            $sheet->setCellValue("A{$row}", $vendorName);
            $sheet->setCellValue("B{$row}", $item['sku'] ?? '');
            $sheet->setCellValue("C{$row}", $item['name'] ?? '');
            $sheet->setCellValue("D{$row}", $item['pack_type'] ?? 'Unit');
            $sheet->setCellValue("E{$row}", $item['expected_qty'] ?? $item['quantity'] ?? 0);
            $sheet->setCellValue("F{$row}", $item['received_qty'] ?? 0);
            $sheet->setCellValue("G{$row}", $item['extra'] ?? 0);
            $sheet->setCellValue("H{$row}", 0);
            $row++;
        }

        $filename = "ASN-{$asn->asn_number}.xlsx";
        $tempPath = storage_path("app/temp-{$filename}");
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempPath);
        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    // ─── INVENTORY ───────────────────────────────────────────────
    public function inventory(Request $request)
    {
        $query = Inventory::with('product.vendor', 'product.category', 'warehouse')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn($q, $v) => $q->where('warehouse_id', $v))
            ->when($request->vendor_id, fn($q, $v) => $q->whereHas('product', fn($pq) => $pq->where('vendor_id', $v)))
            ->when($request->search, fn($q, $v) => $q->whereHas('product', fn($pq) => $pq->where('sku', 'like', "%{$v}%")->orWhere('name', 'like', "%{$v}%")))
            ->where('quantity', '>', 0);

        $inventory = $query->paginate(50)->appends($request->query());
        $warehouses = Warehouse::active()->get();
        $vendors = \App\Models\Vendor::active()->orderBy('company_name')->get();

        $stats = [
            'total_skus'  => Inventory::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->where('quantity', '>', 0)->count(),
            'total_units' => Inventory::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->sum('quantity'),
            'available'   => Inventory::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->sum('available_quantity'),
            'reserved'    => Inventory::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->sum('reserved_quantity'),
        ];

        return view('logistics.inventory.index', compact('inventory', 'warehouses', 'vendors', 'stats'));
    }

    public function downloadInventory(Request $request)
    {
        $items = Inventory::with('product.vendor', 'product.category', 'warehouse')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn($q, $v) => $q->where('warehouse_id', $v))
            ->where('quantity', '>', 0)->get();

        $csv = "SKU,Product Name,Category,Vendor,Warehouse,Company,Quantity,Available,Reserved,Received Date,Ageing (Days)\n";
        foreach ($items as $inv) {
            $ageing = $inv->received_date ? now()->diffInDays($inv->received_date) : 0;
            $csv .= implode(',', [
                $inv->product->sku ?? '', '"' . str_replace('"', '""', $inv->product->name ?? '') . '"',
                '"' . ($inv->product->category->name ?? '') . '"', '"' . ($inv->product->vendor->company_name ?? '') . '"',
                '"' . ($inv->warehouse->name ?? '') . '"', $inv->company_code,
                $inv->quantity, $inv->available_quantity, $inv->reserved_quantity,
                $inv->received_date?->format('Y-m-d') ?? '', $ageing,
            ]) . "\n";
        }
        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="inventory-' . date('Y-m-d') . '.csv"']);
    }

    public function inventoryAgeing(Request $request)
    {
        $companyCode = $request->get('company_code');

        // Build flat ageing summary for KPI cards
        $allInventory = Inventory::when($companyCode, fn($q, $v) => $q->where('company_code', $v))
            ->where('quantity', '>', 0)
            ->whereNotNull('received_date')
            ->get()
            ->map(function ($inv) {
                $inv->ageing_days = now()->diffInDays($inv->received_date);
                return $inv;
            });

        $ageing = [
            '0_30'     => $allInventory->where('ageing_days', '<=', 30)->sum('quantity'),
            '31_60'    => $allInventory->whereBetween('ageing_days', [31, 60])->sum('quantity'),
            '61_90'    => $allInventory->whereBetween('ageing_days', [61, 90])->sum('quantity'),
            '91_120'   => $allInventory->whereBetween('ageing_days', [91, 120])->sum('quantity'),
            '120_plus' => $allInventory->where('ageing_days', '>', 120)->sum('quantity'),
        ];

        // Ageing by warehouse
        $byWarehouse = Inventory::with('warehouse')
            ->when($companyCode, fn($q, $v) => $q->where('company_code', $v))
            ->where('quantity', '>', 0)
            ->whereNotNull('received_date')
            ->get()
            ->groupBy('warehouse_id')
            ->map(function ($group) {
                $items = $group->map(function ($inv) {
                    $inv->ageing_days = now()->diffInDays($inv->received_date);
                    return $inv;
                });
                return [
                    'warehouse' => $group->first()->warehouse,
                    '0_30'      => $items->where('ageing_days', '<=', 30)->sum('quantity'),
                    '31_60'     => $items->whereBetween('ageing_days', [31, 60])->sum('quantity'),
                    '61_90'     => $items->whereBetween('ageing_days', [61, 90])->sum('quantity'),
                    '91_plus'   => $items->where('ageing_days', '>', 90)->sum('quantity'),
                ];
            })->values();

        // GRN ageing
        $grnAgeing = Grn::with('shipment', 'warehouse')
            ->when($companyCode, fn($q, $v) => $q->where('company_code', $v))
            ->latest()->get()->map(function ($grn) {
                $grn->ageing_days = $grn->receipt_date ? now()->diffInDays($grn->receipt_date) : 0;
                return $grn;
            });

        return view('logistics.inventory.ageing', compact('ageing', 'byWarehouse', 'grnAgeing', 'companyCode'));
    }

    public function warehouseAllocation(Request $request)
    {
        $inventoryByWarehouse = Warehouse::active()
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->withCount(['inventory' => fn($q) => $q->where('quantity', '>', 0)])
            ->withSum(['inventory' => fn($q) => $q->where('quantity', '>', 0)], 'quantity')
            ->withSum(['inventory' => fn($q) => $q->where('quantity', '>', 0)], 'available_quantity')
            ->with(['subWarehouses', 'subLocations'])
            ->get();

        $warehouses = $inventoryByWarehouse;

        $movements = InventoryMovement::with('product', 'fromWarehouse', 'toWarehouse', 'performer')
            ->latest()->take(20)->get();

        return view('logistics.inventory.allocation', compact('inventoryByWarehouse', 'warehouses', 'movements'));
    }

    public function transferInventory(Request $request)
    {
        $request->validate([
            'product_id'        => 'required|exists:products,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id'   => 'required|exists:warehouses,id|different:from_warehouse_id',
            'quantity'          => 'required|integer|min:1',
        ]);
        $this->logisticsService->transferInventory($request->product_id, $request->from_warehouse_id, $request->to_warehouse_id, $request->quantity, $request->from_sub_id, $request->to_sub_id);
        return back()->with('success', 'Inventory transferred.');
    }

    // ─── WAREHOUSE CHARGES ───────────────────────────────────────
    public function warehouseCharges(Request $request)
    {
        $charges = WarehouseCharge::with('vendor', 'warehouse')
            ->when($request->month, fn($q, $v) => $q->where('charge_month', $v))
            ->when($request->year, fn($q, $v) => $q->where('charge_year', $v))
            ->when($request->vendor_id, fn($q, $v) => $q->where('vendor_id', $v))
            ->when($request->charge_type, fn($q, $v) => $q->where('charge_type', $v))
            ->latest()->paginate(30);

        $vendors = \App\Models\Vendor::active()->orderBy('company_name')->get();
        $warehouses = Warehouse::active()->get();
        return view('logistics.warehouse-charges.index', compact('charges', 'vendors', 'warehouses'));
    }

    public function vendorChargeAllocation(Request $request)
    {
        $month = $request->get('month', date('n'));
        $year = $request->get('year', date('Y'));
        $companyCode = $request->get('company_code');

        $charges = WarehouseCharge::with('vendor', 'warehouse')
            ->where('charge_month', $month)
            ->where('charge_year', $year)
            ->when($companyCode, fn($q, $v) => $q->where('company_code', $v))
            ->get();

        $allocations = $charges->groupBy('vendor_id')->map(function ($group) {
            $vendor = $group->first()->vendor;
            $byType = $group->groupBy('charge_type');
            return [
                'vendor'           => $vendor,
                'inward'           => $byType->get('inward', collect())->sum('calculated_amount'),
                'storage'          => $byType->get('storage', collect())->sum('calculated_amount'),
                'pick_pack'        => $byType->get('pick_pack', collect())->sum('calculated_amount'),
                'consumable'       => $byType->get('consumable', collect())->sum('calculated_amount'),
                'last_mile'        => $byType->get('last_mile', collect())->sum('calculated_amount'),
                'total_calculated' => $group->sum('calculated_amount'),
                'total_actual'     => $group->sum('actual_amount'),
                'total_variance'   => $group->sum('variance'),
                'status'           => $group->contains('status', 'receipt_uploaded') ? 'receipt_uploaded' : 'calculated',
            ];
        })->values();

        $warehouses = Warehouse::active()->get();
        $vendors = \App\Models\Vendor::active()->orderBy('company_name')->get();

        return view('logistics.warehouse-charges.vendor-allocation', compact('allocations', 'vendors', 'warehouses', 'month', 'year', 'companyCode'));
    }

    public function uploadChargeReceipt(Request $request, WarehouseCharge $charge)
    {
        $request->validate(['receipt' => 'required|file|max:10240', 'actual_amount' => 'required|numeric']);
        $path = $request->file('receipt')->store('warehouse-receipts', 'public');
        $charge->update([
            'receipt_file' => $path, 'actual_amount' => $request->actual_amount,
            'variance' => $request->actual_amount - $charge->calculated_amount,
            'variance_comment' => $request->variance_comment, 'status' => 'receipt_uploaded',
        ]);
        return back()->with('success', 'Receipt uploaded.');
    }

    public function calculateCharges(Request $request)
    {
        $request->validate(['vendor_id' => 'required', 'warehouse_id' => 'required', 'month' => 'required|integer', 'year' => 'required|integer']);
        $this->logisticsService->calculateWarehouseCharges($request->vendor_id, $request->month, $request->year, $request->warehouse_id);
        return back()->with('success', 'Warehouse charges calculated.');
    }

    public function bulkCalculateCharges(Request $request)
    {
        $request->validate(['warehouse_id' => 'required|exists:warehouses,id', 'month' => 'required|integer', 'year' => 'required|integer']);

        $vendorIds = Inventory::where('warehouse_id', $request->warehouse_id)
            ->where('quantity', '>', 0)
            ->join('products', 'inventory.product_id', '=', 'products.id')
            ->distinct()->pluck('products.vendor_id');

        $count = 0;
        foreach ($vendorIds as $vendorId) {
            if ($vendorId) {
                $this->logisticsService->calculateWarehouseCharges($vendorId, $request->month, $request->year, $request->warehouse_id);
                $count++;
            }
        }

        return redirect()->route('logistics.warehouse-charges.vendor-allocation', ['month' => $request->month, 'year' => $request->year])
            ->with('success', "Charges calculated for {$count} vendors.");
    }

    // ─── RATE CARDS ──────────────────────────────────────────────
    public function rateCards(Request $request)
    {
        $companyCode = $request->get('company_code');
        $warehouses = Warehouse::when($companyCode, fn($q, $v) => $q->where('company_code', $v))->get();
        return view('logistics.rate-cards.index', compact('warehouses', 'companyCode'));
    }

    public function updateRateCard(Request $request, Warehouse $warehouse)
    {
        $warehouse->update($request->only([
            'inward_rate_per_cbm', 'storage_rate_per_cbm_month',
            'pick_pack_rate', 'consumable_rate', 'last_mile_rate',
        ]));
        return back()->with('success', "Rate card updated for {$warehouse->name}.");
    }
}
