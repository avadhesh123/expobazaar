<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{Shipment, Consignment, Vendor, Grn, GrnItem, Inventory, InventoryMovement, WarehouseCharge, Warehouse, LiveSheet};
use App\Services\{DashboardService, LogisticsService};
use Illuminate\Http\Request;
use App\Models\ActivityLog;
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

        try {
            $shipment = $this->logisticsService->createShipment($request->consignment_ids, $request->shipment_type, $request->company_code, $request->all());
            return redirect()->route('logistics.shipments')->with('success', 'Shipment created. Code: ' . $shipment->shipment_code);
        } catch (\Exception $e) {
            \Log::error('Shipment creation failed: ' . $e->getMessage());
            return back()->with('error', 'Shipment creation failed: ' . $e->getMessage())->withInput();
        }
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
        $ageingDays = $grn->receipt_date ? now()->diffInDays($grn->receipt_date) : 0;
        return view('logistics.grn.show', compact('grn', 'ageingDays'));
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
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($request->vendor_id, fn ($q, $v) => $q->whereHas('product', fn ($pq) => $pq->where('vendor_id', $v)))
            ->when($request->search, fn ($q, $v) => $q->whereHas('product', fn ($pq) => $pq->where('sku', 'like', "%{$v}%")->orWhere('name', 'like', "%{$v}%")))
            ->where('quantity', '>', 0);

        $inventory = $query->paginate(50)->appends($request->query());

        // Add ageing_days to each item for the view
        $inventory->getCollection()->transform(function ($inv) {
            $inv->ageing_days = $inv->received_date ? now()->diffInDays($inv->received_date) : 0;
            return $inv;
        });

        $warehouses = Warehouse::active()->get();
        $vendors = \App\Models\Vendor::active()->orderBy('company_name')->get();

        $stats = [
            'total_skus'  => Inventory::when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))->where('quantity', '>', 0)->count(),
            'total_units' => Inventory::when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))->sum('quantity'),
            'available'   => Inventory::when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))->sum('available_quantity'),
            'reserved'    => Inventory::when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))->sum('reserved_quantity'),
        ];

        return view('logistics.inventory.index', compact('inventory', 'warehouses', 'vendors', 'stats'));
    }

    public function downloadInventory(Request $request)
    {
        $items = Inventory::with('product.vendor', 'product.category', 'warehouse')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
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
        $allInventory = Inventory::when($companyCode, fn ($q, $v) => $q->where('company_code', $v))
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
            ->when($companyCode, fn ($q, $v) => $q->where('company_code', $v))
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
            ->when($companyCode, fn ($q, $v) => $q->where('company_code', $v))
            ->latest()->get()->map(function ($grn) {
                $grn->ageing_days = $grn->receipt_date ? now()->diffInDays($grn->receipt_date) : 0;
                return $grn;
            });

        return view('logistics.inventory.ageing', compact('ageing', 'byWarehouse', 'grnAgeing', 'companyCode'));
    }

    public function warehouseAllocation(Request $request)
    {
        $inventoryByWarehouse = Warehouse::active()
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->withCount(['inventory' => fn ($q) => $q->where('quantity', '>', 0)])
            ->withSum(['inventory' => fn ($q) => $q->where('quantity', '>', 0)], 'quantity')
            ->withSum(['inventory' => fn ($q) => $q->where('quantity', '>', 0)], 'available_quantity')
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
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $category = $request->get('category', '');

        $charges = WarehouseCharge::with('warehouse', 'vendor', 'items')
            ->byMonth($month, $year)
            ->when($category, fn ($q, $v) => $q->where('charge_category', $v))
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->latest()
            ->paginate(30)->withQueryString();

        $warehouses = Warehouse::active()->orderBy('name')->get();
        $vendors = \App\Models\Vendor::active()->orderBy('company_name')->get();

        $baseQ = WarehouseCharge::byMonth($month, $year);
        $stats = [
            'total_payable'     => (float) (clone $baseQ)->payable()->sum('calculated_amount'),
            'total_receivable'  => (float) (clone $baseQ)->receivable()->sum('calculated_amount'),
            'total_invoiced'    => (float) (clone $baseQ)->payable()->whereNotNull('actual_amount')->sum('actual_amount'),
            'total_variance'    => (float) (clone $baseQ)->payable()->whereNotNull('actual_amount')->sum('variance'),
            'pending_invoices'  => (int) (clone $baseQ)->payable()->whereNull('actual_amount')->count(),
            'deducted_count'    => (int) (clone $baseQ)->receivable()->where('deducted_from_payout', true)->count(),
        ];

        return view('logistics.warehouse-charges.index', compact('charges', 'warehouses', 'vendors', 'stats', 'month', 'year', 'category'));
    }

    public function runMonthlyCharges(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2024',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $month = $request->month;
        $year = $request->year;
        $warehouse = Warehouse::findOrFail($request->warehouse_id);
        $whRates = $warehouse->rate_card ?? [];
        if (is_string($whRates)) {
            $whRates = json_decode($whRates, true) ?? [];
        }

        $inventoryItems = Inventory::with('product.vendor')
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity', '>', 0)
            ->get();

        $vendorGroups = $inventoryItems->groupBy(fn ($inv) => $inv->product->vendor_id ?? 0);
        $created = 0;

        try {
            \DB::beginTransaction();

            // A. WAREHOUSE PAYABLE
            $existingPayable = WarehouseCharge::byMonth($month, $year)
                ->where('warehouse_id', $warehouse->id)
                ->payable()->whereNull('vendor_id')->first();

            if (!$existingPayable) {
                $totalQty = $inventoryItems->sum('quantity');

                $payableCharge = WarehouseCharge::create([
                    'warehouse_id' => $warehouse->id, 'company_code' => $warehouse->company_code,
                    'charge_month' => $month, 'charge_year' => $year,
                    'charge_type' => 'monthly', 'charge_category' => 'payable',
                    'calculated_amount' => 0, 'status' => 'calculated', 'uploaded_by' => auth()->id(),
                ]);

                $payableTotal = 0;
                $chargeLines = [
                    ['key' => 'storage_pallet', 'qty' => ceil($totalQty / 48)],
                    ['key' => 'inward_unloading', 'qty' => 1],
                    ['key' => 'others_wms', 'qty' => 1],
                ];
                foreach ($chargeLines as $line) {
                    $rate = floatval($whRates[$line['key'] . '_rate'] ?? 0);
                    if ($rate <= 0) {
                        continue;
                    }
                    $amount = round($line['qty'] * $rate, 2);
                    $payableCharge->items()->create([
                        'charge_key' => $line['key'],
                        'charge_label' => $whRates[$line['key'] . '_label'] ?? $line['key'],
                        'uom' => $whRates[$line['key'] . '_uom'] ?? '',
                        'quantity' => $line['qty'], 'rate' => $rate, 'amount' => $amount,
                    ]);
                    $payableTotal += $amount;
                }
                $payableCharge->update(['calculated_amount' => $payableTotal]);
                $created++;
            }

            // B. VENDOR RECEIVABLE
            foreach ($vendorGroups as $vendorId => $vendorItems) {
                if (!$vendorId) {
                    continue;
                }

                $existing = WarehouseCharge::byMonth($month, $year)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('vendor_id', $vendorId)->receivable()->first();
                if ($existing) {
                    continue;
                }

                $vendorRates = \App\Models\VendorRateCard::where('vendor_id', $vendorId)
                    ->where(fn ($q) => $q->where('warehouse_id', $warehouse->id)->orWhereNull('warehouse_id'))
                    ->active()->effectiveOn(now()->startOfMonth()->toDateString())
                    ->get()->keyBy('charge_key');

                $vendorQty = $vendorItems->sum('quantity');

                $recoveryCharge = WarehouseCharge::create([
                    'warehouse_id' => $warehouse->id, 'vendor_id' => $vendorId,
                    'company_code' => $warehouse->company_code,
                    'charge_month' => $month, 'charge_year' => $year,
                    'charge_type' => 'monthly', 'charge_category' => 'receivable',
                    'calculated_amount' => 0, 'status' => 'calculated', 'uploaded_by' => auth()->id(),
                ]);

                $recoveryTotal = 0;
                $recoveryLines = [
                    ['key' => 'storage_pallet', 'qty' => ceil($vendorQty / 48)],
                    ['key' => 'inward_checkin', 'qty' => $vendorQty],
                    ['key' => 'outward_pickpack', 'qty' => $vendorQty],
                ];
                foreach ($recoveryLines as $line) {
                    $vr = $vendorRates->get($line['key']);
                    $rate = $vr ? floatval($vr->rate) : floatval($whRates[$line['key'] . '_rate'] ?? 0);
                    if ($rate <= 0) {
                        continue;
                    }
                    $amount = round($line['qty'] * $rate, 2);
                    $recoveryCharge->items()->create([
                        'charge_key' => $line['key'],
                        'charge_label' => $vr ? $vr->charge_label : ($whRates[$line['key'] . '_label'] ?? $line['key']),
                        'uom' => $vr ? $vr->uom : ($whRates[$line['key'] . '_uom'] ?? ''),
                        'quantity' => $line['qty'], 'rate' => $rate, 'amount' => $amount,
                    ]);
                    $recoveryTotal += $amount;
                }
                $recoveryCharge->update(['calculated_amount' => $recoveryTotal]);
                $created++;
            }

            \DB::commit();
            ActivityLog::log('calculated', 'warehouse_charges', $warehouse, null, ['month' => $month, 'year' => $year, 'records' => $created], "Monthly charges run for {$warehouse->name}");
            return back()->with('success', "{$created} charge record(s) calculated for {$warehouse->name} ({$month}/{$year}).");
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Run charges failed: ' . $e->getMessage());
            return back()->with('error', 'Failed: ' . $e->getMessage());
        }
    }

    public function uploadWarehouseInvoice(Request $request, WarehouseCharge $charge)
    {
        $request->validate([
            'actual_amount'  => 'required|numeric|min:0',
            'invoice_number' => 'required|string|max:100',
            'invoice_date'   => 'required|date',
            'invoice_file'   => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        $data = [
            'actual_amount' => $request->actual_amount,
            'invoice_number' => $request->invoice_number,
            'invoice_date' => $request->invoice_date,
            'reason_code' => $request->reason_code,
            'variance' => floatval($request->actual_amount) - floatval($charge->calculated_amount),
            'variance_comment' => $request->variance_comment,
            'status' => 'invoiced',
        ];
        if ($request->hasFile('invoice_file')) {
            $data['invoice_file'] = $request->file('invoice_file')->store("warehouse-invoices/{$charge->warehouse_id}", 'public');
        }
        $charge->update($data);
        ActivityLog::log('invoiced', 'warehouse_charge', $charge, null, $data, "Invoice #{$request->invoice_number} uploaded");
        $varLabel = $data['variance'] > 0 ? 'over' : ($data['variance'] < 0 ? 'under' : 'exact');
        return back()->with('success', "Invoice uploaded. Variance: $" . number_format(abs($data['variance']), 2) . " ({$varLabel})");
    }

    public function approveCharge(Request $request, WarehouseCharge $charge)
    {
        $charge->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        ActivityLog::log('approved', 'warehouse_charge', $charge);
        return back()->with('success', 'Charge approved.');
    }

    public function varianceReport(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $charges = WarehouseCharge::with('warehouse', 'items')->payable()
            ->byMonth($month, $year)->whereNotNull('actual_amount')->get();
        $warehouses = Warehouse::active()->orderBy('name')->get();
        $totals = [
            'calculated' => $charges->sum('calculated_amount'),
            'actual' => $charges->sum('actual_amount'),
            'variance' => $charges->sum('variance'),
        ];
        return view('logistics.warehouse-charges.variance', compact('charges', 'warehouses', 'totals', 'month', 'year'));
    }

    public function vendorRateCards(Request $request)
    {
        $rateCards = \App\Models\VendorRateCard::with('vendor', 'warehouse')
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->active()->orderBy('vendor_id')->orderBy('charge_key')
            ->paginate(50)->withQueryString();
      //  $vendors =  Vendor::active()->orderBy('company_name')->get();
        
$vendors =  Vendor::orderBy('company_name')->get();

       // print_r($vendors->toArray());exit;
        $warehouses = Warehouse::active()->orderBy('name')->get();
        $chargeKeys = [
            'inward_unloading' => 'Unloading', 'inward_putaway' => 'Put Away', 'inward_checkin' => 'Check In',
            'storage_pallet' => 'Pallet/Week', 'storage_cft' => 'CFT/Month',
            'outward_order' => 'Order Processing', 'outward_pickpack' => 'Pick Pack',
            'outward_label' => 'Label', 'outward_material' => 'Material Cost',
            'others_vas' => 'VAS', 'others_setup' => 'Setup', 'others_wms' => 'WMS Monthly',
        ];
        return view('logistics.warehouse-charges.vendor-rate-cards', compact('rateCards', 'vendors', 'warehouses', 'chargeKeys'));
    }

    public function storeVendorRateCard(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'charge_key' => 'required|string|max:100',
            'charge_label' => 'required|string|max:200',
            'rate' => 'required|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);
        \App\Models\VendorRateCard::create(array_merge(
            $request->only(['vendor_id', 'warehouse_id', 'charge_key', 'charge_label', 'charge_type', 'uom', 'rate', 'effective_from', 'effective_to']),
            ['is_active' => true, 'created_by' => auth()->id()]
        ));
        return back()->with('success', 'Vendor rate card created.');
    }

    public function updateVendorRateCard(Request $request, \App\Models\VendorRateCard $vendorRateCard)
    {
        $request->validate(['rate' => 'required|numeric|min:0']);
        $vendorRateCard->update($request->only(['rate', 'effective_from', 'effective_to', 'is_active']));
        return back()->with('success', 'Rate updated.');
    }

    public function downloadChargesReport(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $charges = WarehouseCharge::with('warehouse', 'vendor', 'items')->byMonth($month, $year)->get();
        $csv = "Category,Warehouse,Vendor,Period,Calculated,Actual,Variance,Invoice #,Status\n";
        foreach ($charges as $c) {
            $csv .= implode(',', [
                $c->charge_category, '"'.($c->warehouse->name ?? '').'"', '"'.($c->vendor->company_name ?? 'N/A').'"',
                $c->period, number_format(floatval($c->calculated_amount), 2),
                number_format(floatval($c->actual_amount ?? 0), 2), number_format(floatval($c->variance ?? 0), 2),
                $c->invoice_number ?? '', $c->status,
            ])."\n";
        }
        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"charges-{$month}-{$year}.csv\""]);
    }

    public function vendorChargeAllocation(Request $request)
    {
        $month = $request->get('month', date('n'));
        $year = $request->get('year', date('Y'));
        $companyCode = $request->get('company_code');

        $charges = WarehouseCharge::with('vendor', 'warehouse')
            ->where('charge_month', $month)
            ->where('charge_year', $year)
            ->when($companyCode, fn ($q, $v) => $q->where('company_code', $v))
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
        $warehouses = Warehouse::when($companyCode, fn ($q, $v) => $q->where('company_code', $v))
            ->where('is_active', true)
            ->get();

        // Define rate card structure matching Warehouse Cost Format
        $rateStructure = [
            'inward' => [
                ['key' => 'unloading',   'label' => 'Unloading',   'charge_type' => 'One time', 'uom' => 'Per Shipment'],
                ['key' => 'put_away',    'label' => 'Put Away',    'charge_type' => 'One time', 'uom' => 'Per Master Carton'],
                ['key' => 'check_in',    'label' => 'Check In',    'charge_type' => 'One time', 'uom' => 'Per Unit'],
            ],
            'storage' => [
                ['key' => 'pallet_weekly',  'label' => 'Per Pallet Per Week', 'charge_type' => 'Per week',  'uom' => 'Pallet'],
                ['key' => 'cft_monthly',    'label' => 'CFT Per Month',       'charge_type' => 'Per Month', 'uom' => 'Total CFT'],
            ],
            'outward' => [
                ['key' => 'order_processing', 'label' => 'Order Processing',    'charge_type' => 'Per Order', 'uom' => 'Per Order'],
                ['key' => 'pick_pack',        'label' => 'Pick & Pack',         'charge_type' => 'Per Unit',  'uom' => 'Per Unit'],
                ['key' => 'labelling',        'label' => 'Labelling',           'charge_type' => 'Per Unit',  'uom' => 'Per Unit'],
                ['key' => 'material_cost',    'label' => 'Material Cost (Actual)', 'charge_type' => 'Per Order', 'uom' => 'Per Order'],
            ],
            'others' => [
                ['key' => 'vas',             'label' => 'Value Added Services', 'charge_type' => 'Requirement basis', 'uom' => 'As required'],
                ['key' => 'setup_charges',   'label' => 'One Time Setup',      'charge_type' => 'One Time',          'uom' => 'At opening'],
                ['key' => 'wms_monthly',     'label' => 'Monthly WMS Charges', 'charge_type' => 'Per Month',         'uom' => 'Fixed'],
            ],
        ];

        return view('logistics.rate-cards.index', compact('warehouses', 'companyCode', 'rateStructure'));
    }

    public function updateRateCard(Request $request, Warehouse $warehouse)
    {
        $request->validate([
            'rates'   => 'required|array',
            'rates.*' => 'nullable|numeric|min:0',
        ]);

        // Save as JSON in rate_card column
        $currentRates = $warehouse->rate_card ?? [];
        if (is_string($currentRates)) {
            $currentRates = json_decode($currentRates, true) ?? [];
        }
        $newRates = array_merge($currentRates, array_filter($request->rates, fn ($v) => $v !== null && $v !== ''));

        $warehouse->update(['rate_card' => $newRates]);

        \App\Models\ActivityLog::log('updated', 'warehouse_rate_card', $warehouse, null, $newRates, "Rate card updated for {$warehouse->name}");

        return back()->with('success', "Rate card updated for {$warehouse->name}.");
    }
}
