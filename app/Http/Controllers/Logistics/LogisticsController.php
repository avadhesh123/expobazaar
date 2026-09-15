<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{OrderItem, Shipment, Consignment, Vendor, Grn, GrnItem, Inventory, InventoryMovement, WarehouseCharge, Warehouse, LiveSheet, WarehouseRateCard};
use App\Services\{DashboardService, LogisticsService};
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Helpers\FileStorage;

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
        $data = $this->dashboardService->getLogisticsDashboard();
        return view('logistics.dashboard', compact('data', 'companyCode'));
    }

    // ─── CONTAINER PLANNING ──────────────────────────────────────
    public function containerPlanning(Request $request)
    {
        $user = auth()->user();

        $activeCode = session('active_company');

        $consignments = Consignment::with('vendor', 'liveSheet')
            ->whereIN('status', ['created', 'live_sheet_locked'])
            ->where('company_code', $activeCode)
            //  ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->whereDoesntHave('shipments')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->get();

        $consignments->each(function ($con) {
            $items = $con->liveSheet?->items ?? collect();
            $con->stats = [
                'total_skus'     => $items->unique('product_id')->count(),
                'total_qty'      => $items->sum('quantity'),
                'total_fob'      => $items->sum('total_price'),
                'total_net_wt' => $items->sum(fn ($i) => (($i->product_details['weight'] ?? 0)) * ($i->product_details['final_qty'] ?? 0)),
                'total_gross_wt' => $items->sum(fn ($i) => ($i->product_details['master_carton_weight'] ?? 0) * ($i->product_details['no_of_master_carton'] ?? 0)),
                'total_master_cartons' => $items->sum(fn ($i) => ($i->product_details['no_of_master_carton'] ?? 0)),
                'master_weight_kg' => $items->sum(fn ($i) => ($i->product_details['master_carton_weight'] ?? 0)),
                'total_cbm'      => floatval($con->total_cbm ?? $items->sum('total_cbm')),
            ];
        });

        //  print '<pre>';
        //  print_r($consignments->toArray());
        //  print '</pre>';

        $totalCbm = $consignments->sum('total_cbm');

        return view('logistics.container-planning', compact('consignments', 'totalCbm', 'activeCode'));
    }

    public function createShipment(Request $request)
    {
        $request->validate([
            'consignment_ids' => 'required|array|min:1',
            'shipment_type'   => 'required|in:FCL,LCL,AIR',
            'company_code'    => 'required|in:2000,2100,2200,2400',
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
        $user = auth()->user();

        $activeCode = session('active_company');

        $shipments = Shipment::with('consignments.vendor')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when(!$user->isAdmin() && !empty($activeCode), function ($q) use ($activeCode) {
                return $q->where('company_code', $activeCode);
            })
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
    public function uploadEntrySummary(Request $request, Shipment $shipment)
    {
        $request->validate([
            'entry_summary_file'   => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,xlsx,xls',
            'entry_summary_number' => 'required|string|max:100',
            'entry_summary_date'   => 'nullable|date',
        ]);

        try {
            $path = $request->file('entry_summary_file')->store("shipments/{$shipment->id}/documents", FileStorage::disk());
            $shipment->update([
                'entry_summary_file'        => $path,
                'entry_summary_number'      => $request->entry_summary_number,
                'entry_summary_date'        => $request->entry_summary_date ?: now()->toDateString(),
                'entry_summary_upload_by'   => auth()->id(),
                'entry_summary_upload_date' => now()->toDateString(),
            ]);

            ActivityLog::log('uploaded', 'entry_summary', $shipment, null, [
                'number' => $request->entry_summary_number,
            ], "Entry Summary #{$request->entry_summary_number} uploaded for {$shipment->shipment_code}");

            return back()->with('success', "Entry Summary uploaded for {$shipment->shipment_code}.");
        } catch (\Exception $e) {
            \Log::error('Entry summary upload failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage())->withInput();
        }
    }
    public function saveLogistics(Request $request, Shipment $shipment)
    {
        $request->validate(['sailing_date' => 'required|date']);
        $this->logisticsService->saveLogistics($shipment, $request->all(), auth()->user());
        // return redirect()->route('logistics.shipments')->with('success', 'Logistic information of shipment is saved.');

        return back()->with('success', 'Logistic information of shipment is saved.');
    }
    public function lockShipment(Request $request, Shipment $shipment)
    {
        // $request->validate(['sailing_date' => 'required|date']);
        $this->logisticsService->lockShipment($shipment, $request->all(), auth()->user());
        return redirect()->route('logistics.shipments')->with('success', 'Shipment locked. ASN generated.');
    }

    public function updateShipmentStatus(Request $request, \App\Models\Shipment $shipment)
    {
        $request->validate([
            'status' => 'required|in:planning,shipment,consolidated,locked,asn_generated,in_transit,arrived,grn_pending,grn_completed,cancelled,delivered',
        ]);

        $oldStatus = $shipment->shipment_status;

        $shipment->update([
            'status'             => $request->status,
            'status_changed_at'  => now(),
            'status_changed_by'  => auth()->id(),
        ]);


        \App\Models\ShipmentLog::record($shipment->id, 'shipment_status', $oldStatus, $request->shipment_status);

        // Optional: Log the change
        // \Log::info("Shipment {$shipment->shipment_code} status changed to {$request->status} by " . auth()->user()->name);

        return response()->json([
            'success'     => true,
            'message'     => 'Shipment status updated successfully.',
            'status'      => $request->status,
            'changed_at'  => now()->format('d M Y H:i'),
            'changed_by'  => auth()->user()->name ?? 'System',
        ]);
    }
    // ─── GRN ─────────────────────────────────────────────────────
    public function grnList(Request $request)
    {
        $user = auth()->user();
        $activeCompany = session('active_company');

        $grns = Grn::with('shipment', 'warehouse')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->where('company_code', $activeCompany)
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->latest()->paginate(20);

        $pendingShipments = Shipment::whereIn('status', ['arrived', 'grn_pending', 'locked', 'asn_generated', 'in_transit', 'consolidated'])
            ->whereDoesntHave('grn')
            ->with('consignments.vendor', 'warehouse')
            ->where('company_code', $activeCompany)
            ->latest()->get();

        $warehouses = Warehouse::active()
            ->where('company_code', $activeCompany)
            ->get();
        return view('logistics.grn.index', compact('grns', 'pendingShipments', 'warehouses'));
    }

    public function showGrn(Grn $grn)
    {
        $grn->load('shipment.consignments.vendor', 'warehouse', 'items.product');
        $ageingDays = $grn->received_date
            ? round(abs(now()->diffInRealHours($grn->received_date) / 24), 1)
            : 0;
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
            'warehouse_id'              => 'required|exists:warehouses,id',
            'receipt_date'              => 'required|date',
            'grn_file'                  => 'nullable|file|max:10240|mimes:pdf,xlsx,csv',
            'items'                     => 'required|array|min:1',
            'items.*.product_id'        => 'required|exists:products,id',
            'items.*.expected_quantity'  => 'required|integer|min:0',
            'items.*.received_quantity' => 'required|integer|min:0',
            'items.*.damaged_quantity'  => 'nullable|integer|min:0',
            'items.*.missing_quantity'  => 'nullable|integer|min:0',
            'items.*.excess_quantity'   => 'nullable|integer|min:0',
        ]);

        $data = $request->only(['warehouse_id', 'receipt_date', 'remarks','custom_date']);

        if ($request->hasFile('grn_file')) {
            $data['grn_file'] = $request->file('grn_file')->store("grn/{$shipment->id}", FileStorage::disk());
        }

        try {
            $this->logisticsService->uploadGrn($shipment, $data, $request->items);
            return redirect()->route('logistics.grn')->with('success', 'GRN uploaded. Inventory updated automatically.');
        } catch (\Exception $e) {
            return back()->with('error', 'GRN upload failed: ' . $e->getMessage())->withInput();
        }
    }

    // ─── ASN ─────────────────────────────────────────────────────
    public function downloadAsn(\App\Models\Asn $asn)
    {
        $asn->load('shipment.consignments.vendor', 'shipment.consignments.liveSheet.items.product');
        $shipment = $asn->shipment;

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('ASN');

        $headers = [
            'Row#',
            'Invoice #',
            'SKU#',
            'Qty',
            'No. Of cartons',
            'Qty per carton',
            'Description',
            'ASN',
            'Container#',
            'Booking#',
            'Type of container',
            'Carrier',
            'ATD',
            'ETA',
            'Est. Truck Delivery Date',
            'Vendors Name'
        ];
        foreach ($headers as $col => $header) {
            $cell = chr(65 + $col) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        $row = 2;
        if ($shipment && $shipment->consignments) {
            foreach ($shipment->consignments as $consignment) {
                if (!$consignment->liveSheet) {
                    continue;
                }
                $vendorName = $consignment->vendor->company_name ?? '';

                foreach ($consignment->liveSheet->items as $lsItem) {
                    if (!$lsItem->product) {
                        continue;
                    }
                    $d = $lsItem->product_details ?? [];

                    $qty = intval($lsItem->quantity ?? 0);
                    $qtyPerCarton = intval($d['qty_master_pack'] ?? $d['qty_per_carton'] ?? 0);
                    $totalCartons = intval($d['total_master_cartons'] ?? ($qtyPerCarton > 0 ? ceil($qty / $qtyPerCarton) : 0));

                    $sheet->setCellValue("A{$row}", $row - 1);
                    $sheet->setCellValue("B{$row}", $consignment->consignment_number ?? '');
                    $sheet->setCellValue("C{$row}", $lsItem->product->sku ?? '');
                    $sheet->setCellValue("D{$row}", $qty);
                    $sheet->setCellValue("E{$row}", $totalCartons);
                    $sheet->setCellValue("F{$row}", $qtyPerCarton);
                    $sheet->setCellValue("G{$row}", $lsItem->product->name ?? '');
                    $sheet->setCellValue("H{$row}", $asn->asn_number ?? '');
                    $sheet->setCellValue("I{$row}", $shipment->container_number ?? '');
                    $sheet->setCellValue("J{$row}", $shipment->bill_of_lading ?? '');
                    $sheet->setCellValue("K{$row}", $shipment->shipment_type ?? '');
                    $sheet->setCellValue("L{$row}", $shipment->shipping_line ?? '');
                    $sheet->setCellValue("M{$row}", $shipment->sailing_date?->format('d M Y') ?? '');
                    $sheet->setCellValue("N{$row}", $shipment->eta_date?->format('d M Y') ?? '');
                    $sheet->setCellValue("O{$row}", '');
                    $sheet->setCellValue("P{$row}", $vendorName);

                    $row++;
                }
            }
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
        $user = auth()->user();
        $activeCode = session('active_company');

        $query = Inventory::with('product.vendor', 'product.category', 'warehouse')
            ->when(!$user->isAdmin() && !empty($activeCode), function ($q) use ($activeCode) {
                return $q->where('company_code', $activeCode);
            })
            // ->whereHas('product', function ($pq) use ($activeCode) {
            //     $pq->where('company_code', $activeCode);
            // })
                ->whereHas('product')

            ->whereHas('warehouse', function ($wq) use ($activeCode) {
                $wq->where('company_code', $activeCode);
            })
            // ->where('grn_id', '!=', null)
            ->when($request->inventory_type, function ($q, $v) {
                if ($v === 'consignment_inventory') {
                    $q->whereNotNull('consignment_id');
                } elseif ($v === 'dropship_inventory') {
                    $q->whereNull('consignment_id');
                }
            })
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($request->vendor_id, fn ($q, $v) => $q->whereHas('product', fn ($pq) => $pq->where('vendor_id', $v)))
            ->when($request->search, fn ($q, $v) => $q->whereHas('product', fn ($pq) => $pq->where('sku', 'like', "%{$v}%")->orWhere('name', 'like', "%{$v}%"))); //->where('quantity', '>', 0);


           //print_r($query->toSql());exit;
        $inventory = $query->paginate(50)->appends($request->query());

        // print_r($inventory->toArray());
        // exit;

        // Add ageing_days to each item for the view
        $inventory->getCollection()->transform(function ($inv) {
            $inv->ageing_days = $inv->received_date ? now()->diffInDays($inv->received_date) : 0;
            return $inv;
        });
        $warehouses = Warehouse::active()->get();
        $vendors = \App\Models\Vendor::active()
            ->when(!$user->isAdmin() && !empty($activeCode), function ($q) use ($activeCode) {
                return $q->where('company_code', $activeCode);
            })->orderBy('company_name')->get();

        $baseQuery = Inventory::when($activeCode, fn ($q) => $q->where('inventory.company_code', $activeCode))
            ->when($request->inventory_type, function ($q, $v) {
                if ($v === 'grn_inventory') {
                    $q->whereNotNull('grn_id');
                } elseif ($v === 'dropship_inventory') {
                    $q->whereNull('grn_id');
                }
            });

        $stats = [
            'total_skus'  => (clone $baseQuery)->count(),
            'total_units' => (clone $baseQuery)->sum('quantity'),
            'available'   => (clone $baseQuery)->sum('available_quantity'),
            'reserved'    => (clone $baseQuery)->sum('reserved_quantity'),
            'total_sales' => OrderItem::whereHas('order', function ($q) use ($activeCode) {
                $q->when($activeCode, fn ($q2) => $q2->where('orders.company_code', $activeCode))
                    ->whereNotIn('status', ['cancelled']);
            })->sum(\DB::raw('quantity')),
        ];
        /*
        $stats = [
            'total_skus'  => Inventory::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
                ->when(!$user->isAdmin() && !empty($activeCode), function ($q) use ($activeCode) {
                    return $q->where('company_code', $activeCode);
                })
                ->whereHas('product', fn($pq) => $pq->where('company_code', $activeCode))
                ->where('grn_id', '!=', null)->count(),
            'total_units' => Inventory::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
                ->when(!$user->isAdmin() && !empty($activeCode), function ($q) use ($activeCode) {
                    return $q->where('company_code', $activeCode);
                })
                ->whereHas('product', fn($pq) => $pq->where('company_code', $activeCode))
                ->sum('quantity'),
            'available'   => Inventory::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
                ->when(!$user->isAdmin() && !empty($activeCode), function ($q) use ($activeCode) {
                    return $q->where('company_code', $activeCode);
                })
                ->where('grn_id', '!=', null)
                ->whereHas('product', fn($pq) => $pq->where('company_code', $activeCode))
                ->sum('available_quantity'),
            'reserved'    => Inventory::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
                ->when(!$user->isAdmin() && !empty($activeCode), function ($q) use ($activeCode) {
                    return $q->where('company_code', $activeCode);
                })
                ->where('grn_id', '!=', null)
                ->whereHas('product', fn($pq) => $pq->where('company_code', $activeCode))
                ->sum('reserved_quantity'),
        ];
*/
        return view('logistics.inventory.index', compact('inventory', 'warehouses', 'vendors', 'stats'));
    }

    public function downloadInventory(Request $request)
    {
        $activeCode = session('active_company');
        $items = Inventory::with('product.vendor', 'product.category', 'warehouse')
            ->when(!$request->user()->isAdmin() && !empty($activeCode), function ($q) use ($activeCode) {
                return $q->where('company_code', $activeCode);
            })
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->where('quantity', '>', 0)->get();

        $csv = "SKU,Product Name,Category,Vendor,Warehouse,Company,Quantity,Available,Reserved,Received Date,Ageing (Days)\n";
        foreach ($items as $inv) {
            $ageing = $inv->received_date ? now()->diffInDays($inv->received_date) : 0;
            $csv .= implode(',', [
                $inv->product->sku ?? '',
                '"' . str_replace('"', '""', $inv->product->name ?? '') . '"',
                '"' . ($inv->product->category->name ?? '') . '"',
                '"' . ($inv->product->vendor->company_name ?? '') . '"',
                '"' . ($inv->warehouse->name ?? '') . '"',
                $inv->company_code,
                $inv->quantity,
                $inv->available_quantity,
                $inv->reserved_quantity,
                $inv->received_date?->format('Y-m-d') ?? '',
                $ageing,
            ]) . "\n";
        }
        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="inventory-' . date('Y-m-d') . '.csv"']);
    }

    public function inventoryAgeing(Request $request)
    {
        // $companyCode = $request->get('company_code');

        $activeCode = $companyCode = session('active_company');

        // Build flat ageing summary for KPI cards
        $allInventory = Inventory::when($activeCode, fn ($q, $v) => $q->where('company_code', $v))
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
            ->when($activeCode, fn ($q, $v) => $q->where('company_code', $v))
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
            ->when($activeCode, fn ($q, $v) => $q->where('company_code', $v))
            ->latest()->get()->map(function ($grn) {
                $grn->ageing_days = $grn->receipt_date ? now()->diffInDays($grn->receipt_date) : 0;
                return $grn;
            });

        return view('logistics.inventory.ageing', compact('ageing', 'byWarehouse', 'grnAgeing', 'companyCode'));
    }

    public function warehouseAllocation(Request $request)
    {
        $activeCode = session('active_company');

        $inventoryByWarehouse = Warehouse::active()
            ->when($activeCode, fn ($q, $v) => $q->where('company_code', $v))
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

    public function transferInventoryMaY(Request $request)
    {

        $request->validate([
            'product_id'        => 'required|exists:products,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id'   => 'required|exists:warehouses,id|different:from_warehouse_id',
            'quantity'          => 'required|integer|min:1',
            'transportation_cost' => 'required|numeric|min:0',

        ]);
        $this->logisticsService->transferInventory(
            $request->product_id,
            $request->from_warehouse_id,
            $request->to_warehouse_id,
            $request->quantity,
            null,
            null,
            $request->transportation_cost
        );
        return back()->with('success', 'Inventory transferred.');
    }

    public function transferInventory(Request $request)
    {
        $request->validate([
            'from_warehouse_id'   => 'required|exists:warehouses,id',
            'to_warehouse_id'     => 'required|exists:warehouses,id|different:from_warehouse_id',
            'transportation_cost' => 'required|numeric|min:0',
            'pick_pack_cost'      => 'required|numeric|min:0',
            'reference_no'        => 'required|string|max:100',
            'transfer_inv_file'   => 'required|file|max:10240',
        ]);

        $file = $request->file('transfer_inv_file');
        $ext = strtolower($file->getClientOriginalExtension());

        try {
            $fullPath = $file->getRealPath();
            if (in_array($ext, ['xlsx', 'xls'])) {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $reader->setReadDataOnly(false);
                $rows = $reader->load($fullPath)->getActiveSheet()->toArray(null, true, true, false);
            } else {
                $rows = [];
                if (($handle = fopen($fullPath, 'r')) !== false) {
                    while (($row = fgetcsv($handle)) !== false) {
                        $rows[] = $row;
                    }
                    fclose($handle);
                }
            }

            if (count($rows) < 2) {
                return back()->with('error', 'File is empty.');
            }

            //   $header = array_map(fn($h) => strtolower(trim($h ?? '')), $rows[0]);

            $header = array_map(function ($h) {
                $h = trim($h ?? '');
                $h = preg_replace('/[\x{FEFF}\x{200B}]/u', '', $h); // Remove BOM and zero-width chars
                return strtolower($h);
            }, $rows[0]);

            $skuCol = $qtyCol = null;
            foreach ($header as $i => $h) {
                if (in_array($h, ['sku', 'vendor sku', 'style code'])) {
                    $skuCol = $i;
                }
                if (in_array($h, ['qty', 'quantity', 'transfer qty'])) {
                    $qtyCol = $i;
                }
            }

            if ($skuCol === null || $qtyCol === null) {
                return back()->with('error', 'File must have "SKU" and "Qty" columns.');
            }

            $fromWarehouseId = $request->from_warehouse_id;
            $toWarehouseId = $request->to_warehouse_id;
            $companyCode = session('active_company');
            $errors = [];
            $items = [];

            // ── Step 1: Validate ALL rows first ──
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $rowNum = $i + 1;
                $sku = trim($row[$skuCol] ?? '');
                $qty = intval($row[$qtyCol] ?? 0);

                if (empty($sku)) {
                    continue;
                }
                if ($qty < 1) {
                    $errors[] = "Row {$rowNum}: Invalid quantity for SKU '{$sku}'.";
                    continue;
                }

                $product = \App\Models\Product::withoutGlobalScopes()->where('sku', $sku)->first();
                if (!$product) {
                    $errors[] = "Row {$rowNum}: SKU '{$sku}' not found.";
                    continue;
                }

                $availableStock = \App\Models\Inventory::where('product_id', $product->id)
                    ->where('warehouse_id', $fromWarehouseId)
                    ->when($companyCode, fn ($q) => $q->where('company_code', $companyCode))
                    ->sum('available_quantity');

                if ($availableStock < $qty) {
                    $errors[] = "TRANSFER CANCELLED — Row {$rowNum}: SKU '{$sku}' insufficient stock. Available: {$availableStock}, Requested: {$qty}.";
                    return back()->with('error', "Transfer cancelled. SKU '{$sku}' has only {$availableStock} available but {$qty} requested.")->with('transfer_errors', $errors);
                }

                $items[] = ['product' => $product, 'qty' => $qty, 'sku' => $sku, 'row' => $rowNum];
            }

            if (empty($items)) {
                return back()->with('error', 'No valid items found in file.');
            }

            // ── Step 2: All validated — execute transfers ──

            // ── Step 2: All validated — execute transfers ──
            \DB::beginTransaction();
            $transferred = 0;
            $batchId = 'TRF-' . now()->format('YmdHis') . '-' . mt_rand(1000, 9999);

            // Split costs evenly across items
            $itemCount = count($items);
            $transportPerItem = $itemCount > 0 ? round(floatval($request->transportation_cost) / $itemCount, 2) : 0;
            $pickPackPerItem = $itemCount > 0 ? round(floatval($request->pick_pack_cost) / $itemCount, 2) : 0;

            foreach ($items as $item) {
                $this->logisticsService->transferInventory(
                    $item['product']->id,
                    $fromWarehouseId,
                    $toWarehouseId,
                    $item['qty'],
                    null,                        // fromSubId
                    null,                        // toSubId
                    $transportPerItem,           // transportationCost
                    $pickPackPerItem,            // pickPackCost
                    $request->reference_no,      // referenceNo
                    $batchId                     // batchId
                );
                $transferred++;
            }

            \App\Models\ActivityLog::log('transferred', 'inventory_batch', auth()->user(), null, [
                'from_warehouse' => $fromWarehouseId,
                'to_warehouse'   => $toWarehouseId,
                'items_count'    => $transferred,
                'reference_no'   => $request->reference_no,
                'batch_id'       => $batchId,
                'transport_cost' => $request->transportation_cost,
                'pick_pack_cost' => $request->pick_pack_cost,
            ], "Batch transfer: {$transferred} SKUs, Ref: {$request->reference_no}");

            \DB::commit();


            return back()->with('success', "{$transferred} SKU(s) transferred successfully. Reference: {$request->reference_no}");
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Inventory transfer failed: ' . $e->getMessage());
            return back()->with('error', 'Transfer failed: ' . $e->getMessage());
        }
    }
    public function downloadGrn(\App\Models\Grn $grn)
    {
        $grn->load('items.product.vendor', 'warehouse', 'shipment');

        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM
        $csv .= "GRN Number,Receipt Date,Warehouse,Shipment,Status\n";
        $csv .= "\"{$grn->grn_number}\",\"{$grn->receipt_date?->format('Y-m-d')}\",\"" . ($grn->warehouse->name ?? '') . "\",\"" . ($grn->shipment->shipment_code ?? '') . "\",\"{$grn->status}\"\n\n";
        $csv .= "S.No,Vendor SKU,SAP Code,Product Name,Vendor Name,Expected Qty,Received Qty,Damaged Qty,Missing Qty,Excess Qty,Remarks\n";

        foreach ($grn->items as $idx => $item) {
            $p = $item->product;
            $excess = max(0, intval($item->received_quantity) - intval($item->expected_quantity));
            $csv .= implode(',', [
                $idx + 1,
                '"' . ($p->sku ?? '') . '"',
                '"' . ($p->sap_code ?? '') . '"',
                '"' . str_replace('"', '""', $p->name ?? '') . '"',
                '"' . str_replace('"', '""', $p->vendor->company_name ?? '') . '"',
                intval($item->expected_quantity),
                intval($item->received_quantity),
                intval($item->damaged_quantity ?? 0),
                intval($item->missing_quantity ?? 0),
                $excess,
                '"' . str_replace('"', '""', $item->remarks ?? '') . '"',
            ]) . "\n";
        }

        $filename = "GRN-{$grn->grn_number}.csv";
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
    public function downloadTransferTemplate()
    {
        $csv = "\xEF\xBB\xBF";
        $csv .= "SKU,Qty\n";
        $csv .= "EB-SKU-001,50\n";
        $csv .= "EB-SKU-002,30\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="Transfer-Inventory-Template.csv"',
        ]);
    }
    // ─── WAREHOUSE CHARGES ───────────────────────────────────────
    public function warehouseCharges(Request $request)
    {

        $user = auth()->user();
        $activeCode = session('active_company');

        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $category = $request->get('category', '');

        $charges = WarehouseCharge::with('warehouse', 'vendor', 'items')
            ->byMonth($month, $year)
            ->where('company_code', $activeCode)
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


        $whRates = WarehouseRateCard::where('warehouse_id', $request->warehouse_id)
            ->where('effective_from', '<=', now())
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->where('effective_to', '>=', now())
                    ->orWhereNull('effective_to');
            })
            ->get();


        // $whRates = $warehouse->rate_card ?? [];
        // if (is_string($whRates)) {
        //     $whRates = json_decode($whRates, true) ?? [];
        // }
        if ($whRates) {
            print_r($whRates->toArray());
        }
        exit;
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
                    'warehouse_id' => $warehouse->id,
                    'company_code' => $warehouse->company_code,
                    'charge_month' => $month,
                    'charge_year' => $year,
                    'charge_type' => 'monthly',
                    'charge_category' => 'payable',
                    'calculated_amount' => 0,
                    'status' => 'calculated',
                    'uploaded_by' => auth()->id(),
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
                        'quantity' => $line['qty'],
                        'rate' => $rate,
                        'amount' => $amount,
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
                    'warehouse_id' => $warehouse->id,
                    'vendor_id' => $vendorId,
                    'company_code' => $warehouse->company_code,
                    'charge_month' => $month,
                    'charge_year' => $year,
                    'charge_type' => 'monthly',
                    'charge_category' => 'receivable',
                    'calculated_amount' => 0,
                    'status' => 'calculated',
                    'uploaded_by' => auth()->id(),
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
                        'quantity' => $line['qty'],
                        'rate' => $rate,
                        'amount' => $amount,
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
            $data['invoice_file'] = $request->file('invoice_file')->store("warehouse-invoices/{$charge->warehouse_id}", FileStorage::disk());
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

        $user = auth()->user();
        $activeCode = session('active_company');
        // Show warehouse rate cards as the base template
        $warehouseRateCards = \App\Models\WarehouseRateCard::with('warehouse')
            ->approved()
            ->when(!$user->isAdmin() && !empty($activeCode), function ($q) use ($activeCode) {
                return $q->where('company_code', $activeCode);
            })
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->orderBy('warehouse_id')
            ->get();

        // Existing vendor rate cards
        $vendorRateCards = \App\Models\VendorRateCard::with('vendor', 'creator')
            ->when(!$user->isAdmin() && !empty($activeCode), function ($q) use ($activeCode) {
                return $q->where('company_code', $activeCode);
            })
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->orderByDesc('created_at')
            ->paginate(30)->withQueryString();

        $vendors = \App\Models\Vendor::orderBy('company_name')
            ->when(!$user->isAdmin() && !empty($activeCode), function ($q) use ($activeCode) {
                return $q->where('company_code', $activeCode);
            })
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->get();
        $warehouses = Warehouse::active()->orderBy('name')->get();

        return view('logistics.warehouse-charges.vendor-rate-cards', compact('warehouseRateCards', 'vendorRateCards', 'vendors', 'warehouses'));
    }

    public function storeVendorRateCard(Request $request)
    {
        $request->validate([
            'vendor_id'                 => 'required|exists:vendors,id',
            'inward_rate_per_carton'    => 'required|numeric|min:0',
            'storage_rate_per_cft'      => 'required|numeric|min:0',
            'fulfillment_rate_small'    => 'required|numeric|min:0',
            'fulfillment_rate_large'    => 'required|numeric|min:0',
            'fulfillment_qty_threshold' => 'required|integer|min:1',
            'pick_pack_rate_per_unit'   => 'required|numeric|min:0',
            'effective_from'            => 'nullable|date',
        ]);

        $vendor = \App\Models\Vendor::findOrFail($request->vendor_id);

        $activeCode = session('active_company');

        $currency = match ($activeCode ?? $vendor->company_code) {
            '2000' => 'INR',
            '2100' => 'EUR',
            '2200' => 'USD',
            '2400' => 'GBP',
            default => 'USD'   // fallback
        };

        $maxV = \App\Models\VendorRateCard::where(['vendor_id' => $vendor->id, 'company_code' => $activeCode])->max('version') ?? 0;

        $newEffectiveFrom = $request->effective_from ?? now()->toDateString();

        \App\Models\VendorRateCard::where(['vendor_id' => $vendor->id, 'company_code' => $activeCode])
            ->where('status', 'approved')
            ->whereNull('effective_to')
            ->update([
                'effective_to' => \Carbon\Carbon::parse($newEffectiveFrom)->subDay()->toDateString(),
            ]);

        $rc = \App\Models\VendorRateCard::create([
            'vendor_id'                 => $vendor->id,
            'company_code'              => $activeCode ?? $vendor->company_code ?? '2100',
            'currency'                  => $currency,
            'inward_rate_per_carton'    => $request->inward_rate_per_carton,
            'storage_rate_per_cft'      => $request->storage_rate_per_cft,
            'fulfillment_rate_small'    => $request->fulfillment_rate_small,
            'fulfillment_rate_large'    => $request->fulfillment_rate_large,
            'fulfillment_qty_threshold' => $request->fulfillment_qty_threshold,
            'pick_pack_rate_per_unit'   => $request->pick_pack_rate_per_unit,
            'effective_from'            => $request->effective_from ?? now()->toDateString(),
            'version'                   => $maxV + 1,
            'status'                    => 'approved',
            'created_by'                => auth()->id(),
            'approved_by'               => auth()->id(),
            'approved_at'               => now(),
        ]);

        ActivityLog::log('created', 'vendor_rate_card', $rc, null, $rc->toArray(), "Vendor rate card assigned to {$vendor->company_name}");
        return back()->with('success', "Rate card v{$rc->version} assigned to {$vendor->company_name} and auto-approved.");
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
                $c->charge_category,
                '"' . ($c->warehouse->name ?? '') . '"',
                '"' . ($c->vendor->company_name ?? 'N/A') . '"',
                $c->period,
                number_format(floatval($c->calculated_amount), 2),
                number_format(floatval($c->actual_amount ?? 0), 2),
                number_format(floatval($c->variance ?? 0), 2),
                $c->invoice_number ?? '',
                $c->status,
            ]) . "\n";
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
        $path = $request->file('receipt')->store('warehouse-receipts', FileStorage::disk());
        $charge->update([
            'receipt_file' => $path,
            'actual_amount' => $request->actual_amount,
            'variance' => $request->actual_amount - $charge->calculated_amount,
            'variance_comment' => $request->variance_comment,
            'status' => 'receipt_uploaded',
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
    // ═══ WAREHOUSE RATE CARD (Company pays Warehouse) ═══

    public function warehouseRateCards(Request $request)
    {

        $user = auth()->user();
        $activeCode = session('active_company');

        $rateCards = \App\Models\WarehouseRateCard::with('warehouse', 'creator', 'approver')
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            // ->when(!$user->isAdmin() && !empty($activeCode), function ($q) use ($activeCode) {
            //     return $q->where('company_code', $activeCode);
            // })
            ->where('company_code', $activeCode)
            ->orderByDesc('created_at')->paginate(30)->withQueryString();
        $warehouses = Warehouse::active()->where('company_code', $activeCode)->orderBy('name')->get();
        return view('logistics.warehouse-rate-cards.index', compact('rateCards', 'warehouses'));
    }

    public function storeWarehouseRateCard(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'effective_from' => 'required|date',
        ]);
        // echo '<pre>';
        // print_r($request->toArray());
        // exit;
        $wh = Warehouse::findOrFail($request->warehouse_id);
        $currency = match ($wh->company_code) {
            '2000' => 'INR',
            '2100' => 'USD',
            '2200' => 'EUR',
            '2400' => 'GBP',
            default => 'USD'   // fallback
        };

        $maxV = \App\Models\WarehouseRateCard::where('warehouse_id', $wh->id)->max('version') ?? 0;

        \App\Models\WarehouseRateCard::where('warehouse_id', $wh->id)->where('status', 'approved')
            ->whereNull('effective_to')->update(['effective_to' => now()->subDay()->toDateString(), 'status' => 'expired']);

        $rc = \App\Models\WarehouseRateCard::create(array_merge($request->only([
            'warehouse_id',
            'unloading_fcl',
            'unloading_lcl_palletize',
            'unloading_carton',
            'put_away_per_carton',
            'checkin_per_qty',
            'checkin_per_hours',
            'storage_per_pallet',
            'storage_per_cft',
            'order_processing_palletize',
            'order_processing_non_palletize',
            'pick_pack',
            'fulfillment_rate_small',
            'fulfillment_rate_large',
            'fulfillment_qty_threshold',
            'manpower_cost',
            'return_inward_per_qty',
            'return_inward_per_carton',
            'effective_from',
        ]), ['company_code' => $wh->company_code, 'currency' => $currency, 'version' => $maxV + 1, 'status' => 'draft', 'created_by' => auth()->id()]));

        ActivityLog::log('created', 'warehouse_rate_card', $rc, null, $rc->toArray(), "WH Rate card v{$rc->version} for {$wh->name}");
        return back()->with('success', "Rate card v{$rc->version} created for {$wh->name}.");
    }

    public function submitWarehouseRateCard(\App\Models\WarehouseRateCard $warehouseRateCard)
    {
        // if (!$warehouseRateCard->isComplete()) return back()->with('error', 'All rate fields must be filled.');
        $warehouseRateCard->update(['status' => 'pending_approval']);
        return back()->with('success', 'Submitted for approval.');
    }

    public function approveWarehouseRateCard(\App\Models\WarehouseRateCard $warehouseRateCard)
    {
        // die('ddddddddd');
        $warehouseRateCard->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        ActivityLog::log('approved', 'warehouse_rate_card', $warehouseRateCard);
        return back()->with('success', 'Rate card approved and active.');
    }

    // ═══ WAREHOUSE MONTHLY CHARGES (Calculation + Invoice + Variance) ═══

    /**
     * REPLACE these methods in LogisticsController.php
     *
     * Methods updated:
     * 1. warehouseMonthlyCharges() - passes strategy to view
     * 2. runWarehouseCharges() - shows strategy in success message
     * 3. enterWarehouseInvoice() - supports 6 charge heads (US & EU)
     * 4. approveWarehouseCharge() - checks 6 heads for variance
     * 5. saveVarianceExplanations() - checks 6 heads
     */

    // ─── LIST WAREHOUSE MONTHLY CHARGES ──────────────────────────

    public function warehouseMonthlyCharges(Request $request)
    {
        $activeCode = session('active_company');
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $charges = \App\Models\WarehouseMonthlyCharge::with(['warehouse', 'rateCard'])
            ->where('company_code', $activeCode)
            ->byMonth($month, $year)
            ->orderBy('warehouse_id')
            ->get();

        $warehouses = Warehouse::active()
            ->when($activeCode, fn ($q) => $q->where('company_code', $activeCode))
            ->orderBy('name')->get();

        // Charge heads vary by strategy — pass to view
        $chargeHeads = [
            'unloading'        => 'Unloading',
            'putaway'          => 'Putaway',
            'storage'          => 'Storage',
            'order_processing' => 'Order Processing',
            'pick_pack'        => 'Pick & Pack',
            'return_inward'    => 'Return Inward',
            'transfer'         => 'Inventory Transfer',   // ← add

        ];

        return view('logistics.warehouse-monthly-charges.index', compact('charges', 'warehouses', 'month', 'year', 'chargeHeads'));
    }

    // ─── CALCULATE CHARGES ───────────────────────────────────────

    public function runWarehouseCharges(Request $request)
    {
        $request->validate([
            'month'        => 'required|integer|min:1|max:12',
            'year'         => 'required|integer|min:2024',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $service = new \App\Services\WarehouseChargeCalculationService();
        $result = $service->calculateMonthlyCharges(
            $request->warehouse_id,
            $request->month,
            $request->year,
            auth()->id(),
            (bool) $request->dry_run
        );

        if ($result['success']) {
            $currency = $result['currency'] ?? '$';
            $strategy = strtoupper($result['strategy'] ?? 'unknown');
            $details = $result['details'] ?? [];

            // Build breakdown message
            $parts = [];
            foreach ($details as $key => $val) {
                $amount = is_array($val) ? ($val['charge'] ?? 0) : $val;
                if ($amount > 0) {
                    $parts[] = ucwords(str_replace('_', ' ', $key)) . ": {$currency}" . number_format($amount, 2);
                }
            }

            $msg = "Calculated ({$strategy} strategy). Total: {$currency}" . number_format($result['expected_total'], 2);
            if (!empty($parts)) {
                $msg .= " — " . implode(', ', $parts);
            }

            return back()->with('success', $msg);
        }

        return back()->with('error', $result['error']);
    }

    // ─── ENTER WAREHOUSE INVOICE ─────────────────────────────────

    public function enterWarehouseInvoice(Request $request, \App\Models\WarehouseMonthlyCharge $warehouseMonthlyCharge)
    {
        $request->validate([
            'invoice_number'           => 'required|string|max:100',
            'invoice_date'             => 'required|date',
            'actual_unloading'         => 'required|numeric|min:0',
            'actual_putaway'           => 'required|numeric|min:0',
            'actual_storage'           => 'required|numeric|min:0',
            'actual_order_processing'  => 'required|numeric|min:0',
            'actual_pick_pack'         => 'required|numeric|min:0',
            'actual_return_inward'     => 'required|numeric|min:0',
            'actual_other'             => 'nullable|numeric|min:0',
            'invoice_file'             => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        $chargeFields = ['actual_unloading', 'actual_putaway', 'actual_storage', 'actual_order_processing', 'actual_pick_pack', 'actual_return_inward', 'actual_other'];
        $data = $request->only(array_merge(['invoice_number', 'invoice_date'], $chargeFields));

        // Also map to old fields for backward compatibility
        $data['actual_inward'] = floatval($data['actual_unloading'] ?? 0) + floatval($data['actual_putaway'] ?? 0);
        $data['actual_fulfillment'] = floatval($data['actual_order_processing'] ?? 0);

        // Calculate total
        $data['actual_total'] = floatval($data['actual_unloading'] ?? 0)
            + floatval($data['actual_putaway'] ?? 0)
            + floatval($data['actual_storage'] ?? 0)
            + floatval($data['actual_order_processing'] ?? 0)
            + floatval($data['actual_pick_pack'] ?? 0)
            + floatval($data['actual_return_inward'] ?? 0)
            + floatval($data['actual_other'] ?? 0);

        $data['status'] = 'invoice_entered';
        $data['invoice_entered_by'] = auth()->id();
        $data['remarks'] = $request->remarks;

        if ($request->hasFile('invoice_file')) {
            $data['invoice_file'] = $request->file('invoice_file')->store("warehouse-invoices/{$warehouseMonthlyCharge->warehouse_id}", FileStorage::disk());
        }

        $warehouseMonthlyCharge->update($data);

        // Calculate variances for all 6 heads
        $variances = [];
        $varTotal = 0;
        foreach (['unloading', 'putaway', 'storage', 'order_processing', 'pick_pack', 'return_inward'] as $head) {
            $exp = floatval($warehouseMonthlyCharge->{'expected_' . $head} ?? 0);
            $act = floatval($warehouseMonthlyCharge->{'actual_' . $head} ?? 0);
            $var = round($act - $exp, 2);
            $variances[$head] = $var;
            $varTotal += $var;
        }
        $warehouseMonthlyCharge->update([
            'variance_total' => round($varTotal, 2),
        ]);

        $currency = match ($warehouseMonthlyCharge->company_code) {
            '2000' => '₹',
            '2200' => '€',
            '2400' => '£',
            default => '$',
        };

        ActivityLog::log(
            'invoice_entered',
            'warehouse_monthly_charge',
            $warehouseMonthlyCharge,
            null,
            $data,
            "Invoice #{$request->invoice_number} entered. Variance: {$currency}" . number_format(abs($varTotal), 2)
        );

        return back()->with('success', "Invoice entered. Total: {$currency}" . number_format($data['actual_total'], 2) . " | Variance: {$currency}" . number_format($varTotal, 2));
    }

    // ─── APPROVE WAREHOUSE CHARGE ────────────────────────────────

    public function approveWarehouseCharge(\App\Models\WarehouseMonthlyCharge $warehouseMonthlyCharge)
    {
        $explanations = $warehouseMonthlyCharge->variance_explanations ?? [];
        $chargeHeads = ['unloading', 'putaway', 'storage', 'order_processing', 'pick_pack', 'return_inward'];

        // Check all over-limit variances have explanations
        foreach ($chargeHeads as $head) {
            $exp = floatval($warehouseMonthlyCharge->{'expected_' . $head} ?? 0);
            $act = floatval($warehouseMonthlyCharge->{'actual_' . $head} ?? 0);
            $isOver = $exp > 0 && abs($act - $exp) > ($exp * 0.1);

            if ($isOver && empty($explanations[$head])) {
                $label = ucwords(str_replace('_', ' ', $head));
                return back()->with('error', "Over-limit variance on '{$label}' requires an explanation before approval.");
            }
        }

        $warehouseMonthlyCharge->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        ActivityLog::log('approved', 'warehouse_monthly_charge', $warehouseMonthlyCharge);
        return back()->with('success', 'Reconciliation approved and locked.');
    }

    // ─── SAVE VARIANCE EXPLANATIONS ──────────────────────────────

    public function saveVarianceExplanations(Request $request, \App\Models\WarehouseMonthlyCharge $warehouseMonthlyCharge)
    {
        $request->validate(['explanations' => 'required|array']);

        $warehouseMonthlyCharge->update([
            'variance_explanations' => $request->explanations,
            'status'                => 'under_review',
            'reviewed_by'           => auth()->id(),
        ]);

        return back()->with('success', 'Variance explanations saved. Ready for approval.');
    }
    public function warehouseMonthlyCharges1(Request $request)
    {

        $user = auth()->user();
        $activeCode =  session('active_company');
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        // $charges = \App\Models\WarehouseMonthlyCharge::with('warehouse', 'grnDetails.grn', 'rateCard')
        //     ->where('company_code', $activeCode)
        //     ->byMonth($month, $year)->orderBy('warehouse_id')->get();

        $charges = \App\Models\WarehouseMonthlyCharge::with([
            'warehouse',
            'rateCard',
            'grnDetails.grn' => fn ($q) => $q->where('company_code', $activeCode),
        ])
            ->where('company_code', $activeCode)
            ->byMonth($month, $year)
            ->orderBy('warehouse_id')
            ->get();

        $warehouses = Warehouse::active()
            ->when($activeCode, fn ($q) => $q->where('company_code', $activeCode))
            ->orderBy('name')->get();

        //  $warehouses = Warehouse::active()->orderBy('name')->get();
        return view('logistics.warehouse-monthly-charges.index', compact('charges', 'warehouses', 'month', 'year'));
    }

    public function runWarehouseCharges1(Request $request)
    {
        $request->validate(['month' => 'required|integer|min:1|max:12', 'year' => 'required|integer|min:2024', 'warehouse_id' => 'required|exists:warehouses,id']);
        $service = new \App\Services\WarehouseChargeCalculationService();
        $result = $service->calculateMonthlyCharges($request->warehouse_id, $request->month, $request->year, auth()->id(), (bool)$request->dry_run);

        if ($result['success']) {
            $currency = $result['currency'] ?? '$';
            $grnCount = $result['details']['unloading']['grn_count'] ?? 0;
            return back()->with('success', "Calculated. Expected total: {$currency} " . number_format($result['expected_total'], 2) . " ({$grnCount} GRNs processed).");
        }

        return back()->with('error', $result['error']);
    }


    public function enterWarehouseInvoice1(Request $request, \App\Models\WarehouseMonthlyCharge $warehouseMonthlyCharge)
    {
        $request->validate([
            'invoice_number'     => 'required|string|max:100',
            'invoice_date'       => 'required|date',
            'actual_inward'      => 'required|numeric|min:0',
            'actual_storage'     => 'required|numeric|min:0',
            'actual_fulfillment' => 'required|numeric|min:0',
            'actual_pick_pack'   => 'required|numeric|min:0',
            'actual_other'       => 'nullable|numeric|min:0',
            'invoice_file'       => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        $data = $request->only(['invoice_number', 'invoice_date', 'actual_inward', 'actual_storage', 'actual_fulfillment', 'actual_pick_pack', 'actual_other']);
        $data['actual_total'] = floatval($data['actual_inward']) + floatval($data['actual_storage']) + floatval($data['actual_fulfillment']) + floatval($data['actual_pick_pack']) + floatval($data['actual_other'] ?? 0);
        $data['status'] = 'invoice_entered';
        $data['invoice_entered_by'] = auth()->id();
        $data['remarks'] = $request->remarks;

        if ($request->hasFile('invoice_file')) {
            $data['invoice_file'] = $request->file('invoice_file')->store("warehouse-invoices/{$warehouseMonthlyCharge->warehouse_id}", FileStorage::disk());
        }

        $warehouseMonthlyCharge->update($data);
        $warehouseMonthlyCharge->calculateVariances();
        $warehouseMonthlyCharge->save();

        ActivityLog::log('invoice_entered', 'warehouse_monthly_charge', $warehouseMonthlyCharge, null, $data, "Invoice #{$request->invoice_number} entered");
        return back()->with('success', "Invoice entered. Variance: \$" . number_format(abs(floatval($warehouseMonthlyCharge->variance_total)), 2));
    }

    public function approveWarehouseCharge1(\App\Models\WarehouseMonthlyCharge $warehouseMonthlyCharge)
    {
        // Check all over-limit variances have explanations
        $explanations = $warehouseMonthlyCharge->variance_explanations ?? [];
        foreach (['inward', 'storage', 'fulfillment', 'pick_pack'] as $field) {
            if ($warehouseMonthlyCharge->isOverLimit($field) && empty($explanations[$field])) {
                return back()->with('error', "Over-limit variance on '{$field}' requires an explanation before approval.");
            }
        }
        $warehouseMonthlyCharge->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        ActivityLog::log('approved', 'warehouse_monthly_charge', $warehouseMonthlyCharge);
        return back()->with('success', 'Reconciliation approved and locked.');
    }

    public function saveVarianceExplanations1(Request $request, \App\Models\WarehouseMonthlyCharge $warehouseMonthlyCharge)
    {
        $request->validate(['explanations' => 'required|array']);
        $warehouseMonthlyCharge->update(['variance_explanations' => $request->explanations, 'status' => 'under_review', 'reviewed_by' => auth()->id()]);
        return back()->with('success', 'Variance explanations saved. Ready for approval.');
    }

    // ─── RATE CARDS ──────────────────────────────────────────────
    public function rateCards(Request $request)
    {
        // $companyCode = $request->get('company_code');

        $companyCode =  session('active_company');

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

    public function downloadLiveSheet(\App\Models\Consignment $consignment)
    {
        $activeCompany = session('active_company');
        $isUS = ($activeCompany === '2100');

        $currency = trim(config('app.active_currency_symbol')); // $, ₹, €

        $lwhUnit = $isUS ? 'Inches' : 'CM';
        $weightUnit = $isUS ? 'LBS' : 'KG';   // You can change to LBS if needed for 2100

        $consignment->load('vendor', 'liveSheet.items.product');

        $liveSheet = $consignment->liveSheet;
        if (!$liveSheet) {
            return back()->with('error', 'No live sheet found for this consignment.');
        }

        $items = $liveSheet->items;
        $vendorName = preg_replace('/[^A-Za-z0-9\-]/', '', $consignment->vendor->company_name ?? 'Vendor');
        $filename = "LiveSheet-{$liveSheet->live_sheet_number}-{$vendorName}.xlsx";
        $outputPath = storage_path("app/temp/{$filename}");
        @mkdir(storage_path('app/temp'), 0755, true);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Live Sheet');


        $headers = [
            'S.No',
            'Vendor Name',
            'Vendor SKU',
            'SAP Code',
            'Product Name',
            'Barcode',
            'Category',
            'Material Composition',
            'Color / Finish',
            "Length ({$lwhUnit})",
            "Width ({$lwhUnit})",
            "Height ({$lwhUnit})",
            "Weight ({$weightUnit})",
            'CBM per Unit',
            'Order Qty',
            'Unit Price',
            'Total Price',
            'Total CBM',
            "Total Weight ({$weightUnit})",
            'Master Cartons',
            "Net Weight ({$weightUnit})",
            "Gross Weight ({$weightUnit})",
            'Factory Location',
            'Goods Ready Date',
            'Consignment Number',
            'Live Sheet Number'
        ];

        // Header styling
        $hdrFill = new \PhpOffice\PhpSpreadsheet\Style\Fill();
        $hdrFill->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A5F');

        $hdrFont = new \PhpOffice\PhpSpreadsheet\Style\Font();
        $hdrFont->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'))->setSize(10)->setName('Arial');

        foreach ($headers as $col => $header) {
            $cell = $ws->getCell([$col + 1, 1]);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'))->setSize(10)->setName('Arial');
            $cell->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A5F');
            $cell->getStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        }

        // Data rows
        $altFill = 'F8FAFC';
        foreach ($items as $idx => $item) {
            $p = $item->product;
            $d = $item->product_details ?? [];
            $row = $idx + 2;

            $netWeight =  ($d['weight'] ?? 0) * ($d['final_qty'] ?? 0);
            $grossWeight = ($d['master_carton_weight'] ?? 0) * ($d['no_of_master_carton'] ?? 0);

            $rowData = [
                $idx + 1,
                $consignment->vendor->company_name ?? '',
                $p->sku ?? '',
                $p->sap_code ?? '',
                $p->name ?? '',
                $p->barcode ?? $d['barcode'] ?? '',
                $d['category'] ?? '',
                $d['material'] ?? $p->material ?? '',
                $d['color'] ?? $p->color ?? '',
                floatval($d['length'] ?? $p->length ?? 0),
                floatval($d['width'] ?? $p->width ?? 0),
                floatval($d['height'] ?? $p->height ?? 0),
                floatval($d['weight'] ?? ($p->weight ?? 0)),
                floatval($item->cbm_per_unit ?? 0),
                intval($item->quantity),
                floatval($item->unit_price ?? 0),
                floatval($item->total_price ?? 0),
                floatval($item->total_cbm ?? 0),
                floatval($item->total_weight ?? 0),
                intval($d['no_of_master_carton'] ?? 0),
                floatval($netWeight ?? 0),
                floatval($grossWeight ?? 0),
                $d['factory_location'] ?? $liveSheet->factory_location ?? '',
                $d['goods_ready_date'] ?? $liveSheet->goods_ready_date ?? '',
                $consignment->consignment_number ?? '',
                $liveSheet->live_sheet_number ?? '',
            ];

            foreach ($rowData as $col => $val) {
                $cell = $ws->getCell([$col + 1, $row]);
                $cell->setValue($val);
                $cell->getStyle()->getFont()->setSize(9)->setName('Arial');

                // Alternating row fill
                if ($idx % 2 === 0) {
                    $cell->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($altFill);
                }

                // Borders
                $cell->getStyle()->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
            }

            // Number formats
            $ws->getCell([14, $row])->getStyle()->getNumberFormat()->setFormatCode('0.000000'); // CBM/Unit
            $ws->getCell([16, $row])->getStyle()->getNumberFormat()->setFormatCode('#,##0.00'); // Unit Price
            $ws->getCell([17, $row])->getStyle()->getNumberFormat()->setFormatCode('#,##0.00'); // Total Price
            $ws->getCell([18, $row])->getStyle()->getNumberFormat()->setFormatCode('0.0000');   // Total CBM
            $ws->getCell([19, $row])->getStyle()->getNumberFormat()->setFormatCode('#,##0.00'); // Total Weight
            $ws->getCell([21, $row])->getStyle()->getNumberFormat()->setFormatCode('#,##0.00'); // Net Weight
            $ws->getCell([22, $row])->getStyle()->getNumberFormat()->setFormatCode('#,##0.00'); // Gross Weight
        }

        // Column widths
        $widths = [
            'A' => 5,
            'B' => 22,
            'C' => 16,
            'D' => 12,
            'E' => 35,
            'F' => 16,
            'G' => 14,
            'H' => 12,
            'I' => 10,
            'J' => 8,
            'K' => 8,
            'L' => 8,
            'M' => 10,
            'N' => 10,
            'O' => 6,
            'P' => 10,
            'Q' => 12,
            'R' => 10,
            'S' => 12,
            'T' => 10,
            'U' => 10,
            'V' => 10,
            'W' => 18,
            'X' => 14,
            'Y' => 16,
            'Z' => 16
        ];
        foreach ($widths as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }

        // Auto-filter and freeze
        $ws->setAutoFilter("A1:Z1");
        $ws->freezePane('A2');

        // Header borders
        $ws->getStyle("A1:Z1")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('0D1B2A');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();

        return response()->download($outputPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
    public function updatePallets(Request $request, \App\Models\Shipment $shipment)
    {
        // $request->validate([
        //     'no_of_pallets' => 'nullable|integer|min:0|max:9999',
        // ]);

        if (isset($request->manpower_no_of_hours)) {

            $oldValue = $shipment->manpower_no_of_hours;
            $newValue = $request->manpower_no_of_hours;
            //  $shipment->update(['no_of_pallets' => $newValue]);

            if ($oldValue != $newValue) {
                $shipment->update(['manpower_no_of_hours' => $newValue]);
                \App\Models\ShipmentLog::record($shipment->id, 'manpower_no_of_hours', $oldValue, $newValue);
            }
        } else {
            $oldValue = $shipment->no_of_pallets;
            $newValue = $request->no_of_pallets;
            //  $shipment->update(['no_of_pallets' => $newValue]);

            if ($oldValue != $newValue) {
                $shipment->update(['no_of_pallets' => $newValue]);
                \App\Models\ShipmentLog::record($shipment->id, 'no_of_pallets', $oldValue, $newValue);
            }
        }

        return response()->json(['success' => true, 'value' => $newValue]);
        //        return back()->with('success', 'Pallets updated.');
    }


    // ─── WAREHOUSE PALLET DETAILS ─────────────────────────────────

    public function warehousePallets(Request $request)
    {
        $activeCode = session('active_company');

        // AJAX lookup for existing entry
        if ($request->has('lookup') && $request->ajax()) {
            $existing = \App\Models\WarehousePalletLog::where('warehouse_id', $request->warehouse_id)
                ->where('entry_date', $request->entry_date)
                ->first();
            return response()->json([
                'found'         => (bool) $existing,
                'no_of_pallets' => $existing?->no_of_pallets,
                'remarks'       => $existing?->remarks,
            ]);
        }

        $warehouses = \App\Models\Warehouse::when($activeCode, fn ($q) => $q->where('company_code', $activeCode))
            ->orderBy('name')->get();

        $logs = \App\Models\WarehousePalletLog::with('warehouse', 'creator')
            ->when($activeCode, fn ($q) => $q->where('company_code', $activeCode))
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($request->date_from, fn ($q, $v) => $q->where('entry_date', '>=', $v))
            ->when($request->date_to, fn ($q, $v) => $q->where('entry_date', '<=', $v))
            ->latest('entry_date')
            ->paginate(30)
            ->withQueryString();

        // Today's entries
        $todayEntries = \App\Models\WarehousePalletLog::where('entry_date', today())
            ->when($activeCode, fn ($q) => $q->where('company_code', $activeCode))
            ->with('warehouse')
            ->get()
            ->keyBy('warehouse_id');

        // Stats
        $stats = [
            'total_entries'    => \App\Models\WarehousePalletLog::when($activeCode, fn ($q) => $q->where('company_code', $activeCode))->count(),
            'today_entries'    => $todayEntries->count(),
            'today_pallets'    => $todayEntries->sum('no_of_pallets'),
            'warehouses_count' => $warehouses->count(),
        ];

        return view('logistics.warehouse-pallets', compact('warehouses', 'logs', 'todayEntries', 'stats'));
    }

    public function storeWarehousePallet(Request $request)
    {
        $request->validate([
            'warehouse_id'  => 'required|exists:warehouses,id',
            'no_of_pallets' => 'required|integer|min:0|max:99999',
            'entry_date'    => 'required|date',
            'remarks'       => 'nullable|string|max:500',
        ]);

        $activeCode = session('active_company');
        $warehouse = \App\Models\Warehouse::findOrFail($request->warehouse_id);

        // Upsert — update if same warehouse+date, create if new
        $existing = \App\Models\WarehousePalletLog::where('warehouse_id', $request->warehouse_id)
            ->where('entry_date', $request->entry_date)
            ->first();

        $oldValue = $existing?->no_of_pallets;

        $log = \App\Models\WarehousePalletLog::updateOrCreate(
            ['warehouse_id' => $request->warehouse_id, 'entry_date' => $request->entry_date],
            [
                'company_code'  => $activeCode ?? $warehouse->company_code,
                'no_of_pallets' => $request->no_of_pallets,
                'remarks'       => $request->remarks,
                'created_by'    => auth()->id(),
            ]
        );

        \App\Models\ActivityLog::log($existing ? 'updated' : 'created', 'warehouse_pallet', $log, null, [
            'warehouse'  => $warehouse->name,
            'old_value'  => $oldValue,
            'new_value'  => $request->no_of_pallets,
            'entry_date' => $request->entry_date,
        ], "Pallets for {$warehouse->name} on {$request->entry_date}: " . ($existing ? "{$oldValue} → {$request->no_of_pallets}" : $request->no_of_pallets));

        if ($request->ajax()) {
            return response()->json([
                'success'    => true,
                'is_update'  => (bool) $existing,
                'old_value'  => $oldValue,
                'new_value'  => $request->no_of_pallets,
            ]);
        }

        return back()->with(
            'success',
            ($existing ? 'Updated' : 'Recorded') . ": {$warehouse->name} — {$request->no_of_pallets} pallets on {$request->entry_date}."
        );
    }
    public function changeShipmentStatus(Request $request, \App\Models\Shipment $shipment)
    {
        $request->validate([
            'status' => 'required|in:created,locked,cancelled,reopened',
        ]);

        $oldStatus = $shipment->status;
        $newStatus = $request->status;

        // If reopening — unlink consignments so they appear in container planning
        if ($newStatus === 'reopened') {
            $shipment->consignments()->update(['status' => 'created']);
            $newStatus = 'cancelled';
        }

        $shipment->update([
            'status' => $newStatus,
            'shipment_status' => $newStatus,
        ]);

        // Log the change
        \App\Models\ShipmentLog::record($shipment->id, 'status', $oldStatus, $newStatus);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
        }

        return back()->with('success', "Shipment {$shipment->shipment_code} status changed: {$oldStatus} → {$newStatus}.");
    }

    /**
      *
     * Routes (logistics group):
     *   Route::post('grn/{grn}/adjust', [LogisticsController::class, 'adjustGrnQty'])->name('grn.adjust');
     */

    public function adjustGrnQty(Request $request, \App\Models\Grn $grn)
    {
        // Permission check
        if (!auth()->user()->isAdmin() && !\App\Services\PermissionService::can(auth()->user(), 'logistics.grn.adjust')) {
            abort(403, 'You do not have permission to adjust GRN quantities.');
        }

        $request->validate([
            'items'                     => 'required|array|min:1',
            'items.*.grn_item_id'       => 'required|exists:grn_items,id',
            'items.*.received_quantity' => 'required|integer|min:0',
            'items.*.damaged_quantity'  => 'nullable|integer|min:0',
            'items.*.excess_quantity'   => 'nullable|integer|min:0',
            'adjustment_reason'         => 'required|string|max:500',
        ]);

        $updated = 0;
        $logs = [];

        \DB::beginTransaction();
        try {
            foreach ($request->items as $row) {
                $item = \App\Models\GrnItem::where('id', $row['grn_item_id'])
                    ->where('grn_id', $grn->id)
                    ->first();
                if (!$item) {
                    continue;
                }

                $oldReceived = intval($item->received_quantity);
                $oldDamaged = intval($item->damaged_quantity ?? 0);
                $oldExcess = intval($item->excess_quantity ?? 0);

                $newReceived = intval($row['received_quantity']);
                $newDamaged = intval($row['damaged_quantity'] ?? 0);
                $newExcess = intval($row['excess_quantity'] ?? 0);

                // Skip if nothing changed
                if ($oldReceived === $newReceived && $oldDamaged === $newDamaged && $oldExcess === $newExcess) {
                    continue;
                }

                // Calculate inventory impact
                // Old good qty = received - damaged
                // New good qty = received - damaged
                $oldGoodQty = $oldReceived - $oldDamaged;
                $newGoodQty = $newReceived - $newDamaged;
                $qtyDiff = $newGoodQty - $oldGoodQty;

                // Update GRN item
                $item->update([
                    'received_quantity' => $newReceived,
                    'damaged_quantity'  => $newDamaged,
                    'excess_quantity'   => $newExcess,
                ]);

                // Update inventory
                if ($qtyDiff !== 0 && $item->product_id) {
                    $inventory = \App\Models\Inventory::where('product_id', $item->product_id)
                        ->where('warehouse_id', $grn->warehouse_id)
                        ->first();

                    if ($inventory) {
                        $inventory->update([
                            'quantity'           => max(0, intval($inventory->quantity) + $qtyDiff),
                            'available_quantity' => max(0, intval($inventory->available_quantity) + $qtyDiff),
                        ]);
                    }
                }

                $sku = $item->product->sku ?? '—';

                // Log this change
                $changeLog = [
                    'grn_item_id' => $item->id,
                    'product_id'  => $item->product_id,
                    'sku'         => $sku,
                    'old'         => ['received' => $oldReceived, 'damaged' => $oldDamaged, 'excess' => $oldExcess],
                    'new'         => ['received' => $newReceived, 'damaged' => $newDamaged, 'excess' => $newExcess],
                    'inventory_impact' => $qtyDiff,
                ];

                $logs[] = $changeLog;

                // Log per item
                \Log::channel('daily')->info("[GRN Adjust] {$grn->grn_number} SKU {$sku}: " .
                    "Received {$oldReceived}→{$newReceived}, Damaged {$oldDamaged}→{$newDamaged}, Excess {$oldExcess}→{$newExcess}, " .
                    "Inventory impact: {$qtyDiff}");

                $updated++;
            }

            // Save adjustment log to GRN
            $adjustmentHistory = $grn->adjustment_history ?? [];
            $adjustmentHistory[] = [
                'adjusted_at' => now()->toISOString(),
                'adjusted_by' => auth()->user()->name,
                'user_id'     => auth()->id(),
                'reason'      => $request->adjustment_reason,
                'items'       => $logs,
            ];

            $grn->update([
                'adjustment_history' => $adjustmentHistory,
            ]);

            // Activity log
            \App\Models\ActivityLog::log('adjusted', 'grn', $grn, null, [
                'grn_number'  => $grn->grn_number,
                'items_adjusted' => $updated,
                'reason'      => $request->adjustment_reason,
                'changes'     => array_slice($logs, 0, 20),
                'adjusted_by' => auth()->user()->name,
            ], "GRN {$grn->grn_number}: {$updated} item(s) adjusted by " . auth()->user()->name . " — {$request->adjustment_reason}");

            \DB::commit();

            return back()->with('success', "{$updated} item(s) adjusted in {$grn->grn_number}. Inventory updated.");

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("[GRN Adjust] Failed: {$e->getMessage()}");
            return back()->with('error', 'Adjustment failed: ' . $e->getMessage());
        }
    }

}
