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
    ) {
    }

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
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->country, fn ($q, $v) => $q->where('destination_country', $v))
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
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->type, fn ($q, $v) => $q->where('shipment_type', $v))
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
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->latest()->paginate(20);

        // Pending GRN shipments (arrived but no GRN yet)
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

        // Calculate ageing
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
            'warehouse_id'                 => 'required|exists:warehouses,id',
            'receipt_date'                 => 'required|date',
            'items'                        => 'required|array|min:1',
            'items.*.product_id'           => 'required|exists:products,id',
            'items.*.expected_quantity'     => 'required|integer|min:0',
            'items.*.received_quantity'     => 'required|integer|min:0',
        ]);
        $this->logisticsService->uploadGrn($shipment, $request->all(), $request->items);
        return redirect()->route('logistics.grn')->with('success', 'GRN uploaded. Inventory updated automatically.');
    }

    // ─── ASN ─────────────────────────────────────────────────────
    public function downloadAsn(\App\Models\Asn $asn)
    {
        $asn->load('shipment.consignments.vendor');
        $items = $asn->items ?? [];

        // Generate XLSX using PhpSpreadsheet matching ASN_FILE format
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('ASN - ' . $asn->asn_number);

        // Headers matching ASN_FILE template
        $headers = ['Vendors Name', 'SKU', 'ITEM NAME', 'Pack Type', 'Expected Pack Qty', 'Received Package Quantity', 'EXTRA', 'Difference'];
        foreach ($headers as $col => $header) {
            $cell = chr(65 + $col) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        }

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(35);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(28);
        $sheet->getColumnDimension('G')->setWidth(10);
        $sheet->getColumnDimension('H')->setWidth(14);

        // Data rows
        $row = 2;
        foreach ($items as $item) {
            $vendorName = $item['vendor_name'] ?? '';
            if (empty($vendorName) && isset($item['vendor_id'])) {
                $vendor = \App\Models\Vendor::find($item['vendor_id']);
                $vendorName = $vendor->company_name ?? '';
            }

            $expectedQty = $item['expected_qty'] ?? $item['quantity'] ?? 0;
            $receivedQty = $item['received_qty'] ?? 0;
            $extra = $item['extra'] ?? 0;
            $difference = $receivedQty - $expectedQty + $extra;

            $sheet->setCellValue("A{$row}", $vendorName);
            $sheet->setCellValue("B{$row}", $item['sku'] ?? '');
            $sheet->setCellValue("C{$row}", $item['name'] ?? '');
            $sheet->setCellValue("D{$row}", $item['pack_type'] ?? 'Unit');
            $sheet->setCellValue("E{$row}", $expectedQty);
            $sheet->setCellValue("F{$row}", $receivedQty);
            $sheet->setCellValue("G{$row}", $extra);
            $sheet->setCellValue("H{$row}", $difference);

            $row++;
        }

        // Total row
        $lastDataRow = $row - 1;
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->setCellValue("E{$row}", "=SUM(E2:E{$lastDataRow})");
        $sheet->setCellValue("F{$row}", "=SUM(F2:F{$lastDataRow})");
        $sheet->setCellValue("G{$row}", "=SUM(G2:G{$lastDataRow})");
        $sheet->setCellValue("H{$row}", "=SUM(H2:H{$lastDataRow})");
        $sheet->getStyle("A{$row}:H{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2EFDA');

        // Borders
        $sheet->getStyle("A1:H{$row}")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Auto-filter
        $sheet->setAutoFilter("A1:H{$lastDataRow}");

        // Write to temp file and download
        $filename = "ASN-{$asn->asn_number}.xlsx";
        $tempPath = storage_path("app/temp-{$filename}");

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ─── INVENTORY ───────────────────────────────────────────────
    public function inventory(Request $request)
    {
        $inventory = Inventory::with('product.vendor', 'product.category', 'warehouse')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($request->search, fn ($q, $v) => $q->whereHas('product', fn ($pq) => $pq->where('sku', 'like', "%{$v}%")->orWhere('name', 'like', "%{$v}%")))
            ->where('quantity', '>', 0)
            ->paginate(50);
        $warehouses = Warehouse::active()->get();

        $totals = [
            'total_skus'  => Inventory::when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))->where('quantity', '>', 0)->count(),
            'total_qty'   => Inventory::when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))->sum('quantity'),
            'available'   => Inventory::when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))->sum('available_quantity'),
            'reserved'    => Inventory::when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))->sum('reserved_quantity'),
        ];

        return view('logistics.inventory.index', compact('inventory', 'warehouses', 'totals'));
    }

    public function downloadInventory(Request $request)
    {
        $items = Inventory::with('product.vendor', 'product.category', 'warehouse')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->where('quantity', '>', 0)->get();

        $csv = "SKU,Product Name,Category,Vendor,Warehouse,Company,Quantity,Available,Reserved,Received Date,Ageing (Days)\n";
        foreach ($items as $inv) {
            $ageing = $inv->received_date ? now()->diffInDays($inv->received_date) : '—';
            $csv .= implode(',', [
                $inv->product->sku ?? '',
                '"' . str_replace('"', '""', $inv->product->name ?? '') . '"',
                '"' . ($inv->product->category->name ?? '') . '"',
                '"' . ($inv->product->vendor->company_name ?? '') . '"',
                '"' . ($inv->warehouse->name ?? '') . '"',
                $inv->company_code,
                $inv->quantity, $inv->available_quantity, $inv->reserved_quantity,
                $inv->received_date?->format('Y-m-d') ?? '',
                $ageing,
            ]) . "\n";
        }

        $filename = 'inventory-' . ($request->company_code ?? 'all') . '-' . date('Y-m-d') . '.csv';
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function inventoryAgeing(Request $request)
    {
        $inventory = Inventory::with('product.vendor', 'warehouse')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->where('quantity', '>', 0)
            ->whereNotNull('received_date')
            ->get()
            ->map(function ($inv) {
                $inv->ageing_days = now()->diffInDays($inv->received_date);
                $inv->ageing_bucket = match (true) {
                    $inv->ageing_days <= 30  => '0-30 days',
                    $inv->ageing_days <= 60  => '31-60 days',
                    $inv->ageing_days <= 90  => '61-90 days',
                    $inv->ageing_days <= 180 => '91-180 days',
                    default                  => '180+ days',
                };
                return $inv;
            });

        $buckets = $inventory->groupBy('ageing_bucket')->map(fn ($g) => [
            'count' => $g->count(),
            'qty'   => $g->sum('quantity'),
        ]);

        $warehouses = Warehouse::active()->get();
        return view('logistics.inventory.ageing', compact('inventory', 'buckets', 'warehouses'));
    }

    public function warehouseAllocation(Request $request)
    {
        $inventory = Inventory::with('product.vendor', 'warehouse')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->where('quantity', '>', 0)->get();

        $byWarehouse = $inventory->groupBy('warehouse_id')->map(fn ($group) => [
            'warehouse' => $group->first()->warehouse,
            'total_qty' => $group->sum('quantity'),
            'total_skus' => $group->count(),
            'items' => $group,
        ]);

        $warehouses = Warehouse::active()->get();
        return view('logistics.inventory.allocation', compact('byWarehouse', 'warehouses'));
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
            ->when($request->month, fn ($q, $v) => $q->where('charge_month', $v))
            ->when($request->year, fn ($q, $v) => $q->where('charge_year', $v))
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->when($request->charge_type, fn ($q, $v) => $q->where('charge_type', $v))
            ->latest()->paginate(30);

        $vendors = \App\Models\Vendor::active()->orderBy('company_name')->get();
        $warehouses = Warehouse::active()->get();
        return view('logistics.warehouse-charges.index', compact('charges', 'vendors', 'warehouses'));
    }

    public function uploadChargeReceipt(Request $request, WarehouseCharge $charge)
    {
        $request->validate(['receipt' => 'required|file|max:10240', 'actual_amount' => 'required|numeric']);
        $path = $request->file('receipt')->store('warehouse-receipts', 'public');
        $charge->update([
            'receipt_file'     => $path,
            'actual_amount'    => $request->actual_amount,
            'variance'         => $request->actual_amount - $charge->calculated_amount,
            'variance_comment' => $request->variance_comment,
            'status'           => 'receipt_uploaded',
            'uploaded_by'      => auth()->id(),
        ]);
        return back()->with('success', 'Receipt uploaded. Variance: $' . number_format(abs($charge->variance), 2));
    }

    public function calculateCharges(Request $request)
    {
        $request->validate(['vendor_id' => 'required', 'warehouse_id' => 'required', 'month' => 'required|integer', 'year' => 'required|integer']);
        $this->logisticsService->calculateWarehouseCharges($request->vendor_id, $request->month, $request->year, $request->warehouse_id);
        return back()->with('success', 'Warehouse charges calculated.');
    }

    // ─── RATE CARDS ──────────────────────────────────────────────
    public function rateCards(Request $request)
    {
        $warehouses = Warehouse::when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))->get();
        return view('logistics.rate-cards.index', compact('warehouses'));
    }

    public function updateRateCard(Request $request, Warehouse $warehouse)
    {
        $request->validate([
            'inward_rate_per_cbm'         => 'nullable|numeric|min:0',
            'storage_rate_per_cbm_month'  => 'nullable|numeric|min:0',
            'pick_pack_rate_per_unit'     => 'nullable|numeric|min:0',
            'consumable_rate_per_order'   => 'nullable|numeric|min:0',
            'last_mile_rate_per_order'    => 'nullable|numeric|min:0',
        ]);
        $warehouse->update($request->only([
            'inward_rate_per_cbm', 'storage_rate_per_cbm_month',
            'pick_pack_rate_per_unit', 'consumable_rate_per_order', 'last_mile_rate_per_order',
        ]));
        return back()->with('success', "Rate card updated for {$warehouse->name}.");
    }
}
