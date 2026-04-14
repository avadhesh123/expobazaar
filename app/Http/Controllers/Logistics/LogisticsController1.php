<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{Shipment, LiveSheet, Grn, GrnItem, Warehouse, WarehouseSubLocation, WarehouseCharge, Inventory, InventoryMovement, Product, Vendor};
use App\Services\{DashboardService, LogisticsService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogisticsController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected LogisticsService $logisticsService
    ) {
    }

    // =====================================================================
    //  DASHBOARD
    // =====================================================================

    public function dashboard(Request $request)
    {
        $companyCode = $request->get('company_code');
        $data = $this->dashboardService->getLogisticsDashboard($companyCode);

        // Extra dashboard data
        $data['inventory_ageing'] = $this->getInventoryAgeingSummary($companyCode);
        $data['recent_grns'] = Grn::with('shipment', 'warehouse')
            ->when($companyCode, fn ($q) => $q->where('company_code', $companyCode))
            ->latest()->take(5)->get();
        $data['warehouse_utilization'] = Warehouse::active()
            ->when($companyCode, fn ($q) => $q->where('company_code', $companyCode))
            ->withCount('inventory')->get();

        return view('logistics.dashboard', compact('data', 'companyCode'));
    }

    // =====================================================================
    //  CONTAINER PLANNING
    // =====================================================================

    public function containerPlanning(Request $request)
    {
        $liveSheets = LiveSheet::locked()->with('consignment.vendor')
            ->whereHas('consignment', function ($q) use ($request) {
                $q->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v));
            })->get();
        $totalCbm = $liveSheets->sum('total_cbm');
        $fclCapacity = config('expobazaar.fcl_capacity_cbm', 65);
        return view('logistics.container-planning', compact('liveSheets', 'totalCbm', 'fclCapacity'));
    }

    public function createShipment(Request $request)
    {
        $request->validate([
            'consignment_ids' => 'required|array|min:1',
            'shipment_type'   => 'required|in:FCL,LCL,AIR',
            'company_code'    => 'required|in:2000,2100,2200',
        ]);
        $shipment = $this->logisticsService->createShipment(
            $request->consignment_ids,
            $request->shipment_type,
            $request->company_code,
            $request->all()
        );
        $code = $shipment->shipment_code;
        return redirect()->route('logistics.shipments')->with('success', "Shipment created. Code: {$code}");
    }

    // =====================================================================
    //  SHIPMENTS
    // =====================================================================

    public function shipments(Request $request)
    {
        $shipments = Shipment::with('consignments.vendor', 'warehouse')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->type, fn ($q, $v) => $q->where('shipment_type', $v))
            ->latest()->paginate(20);
        return view('logistics.shipments.index', compact('shipments'));
    }

    public function showShipment(Shipment $shipment)
    {
        $shipment->load('consignments.vendor', 'consignments.liveSheet.items.product', 'asn', 'grn.items.product', 'warehouse');
        return view('logistics.shipments.show', compact('shipment'));
    }

    public function lockShipment(Request $request, Shipment $shipment)
    {
        $request->validate(['sailing_date' => 'required|date']);
        $this->logisticsService->lockShipment($shipment, $request->all(), auth()->user());
        return redirect()->route('logistics.shipments')->with('success', 'Shipment locked. ASN generated.');
    }

    // =====================================================================
    //  GRN — Goods Receipt Note
    //  Format: GRN-{CompanyCode}-{YYMM}-{Sequential} e.g. GRN-2100-2601-00042
    // =====================================================================

    public function grnList(Request $request)
    {
        $grns = Grn::with('shipment', 'warehouse', 'uploader')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->latest()->paginate(20);

        $warehouses = Warehouse::active()->get();

        // Shipments pending GRN
        $pendingShipments = Shipment::whereIn('status', ['arrived', 'asn_generated', 'in_transit'])
            ->doesntHave('grn')
            ->with('consignments.vendor', 'warehouse')
            ->latest()->get();

        return view('logistics.grn.index', compact('grns', 'warehouses', 'pendingShipments'));
    }

    public function showGrn(Grn $grn)
    {
        $grn->load('shipment.consignments.vendor', 'warehouse', 'items.product.vendor', 'uploader');
        $ageingDays = $grn->getAgeingDays();
        return view('logistics.grn.show', compact('grn', 'ageingDays'));
    }

    public function uploadGrn(Shipment $shipment)
    {
        $shipment->load('consignments.liveSheet.items.product');
        $warehouses = Warehouse::active()->byCompanyCode($shipment->company_code)->get();
        return view('logistics.grn.upload', compact('shipment', 'warehouses'));
    }

    public function storeGrn(Request $request, Shipment $shipment)
    {
        $request->validate([
            'warehouse_id'                => 'required|exists:warehouses,id',
            'receipt_date'                => 'required|date',
            'items'                       => 'required|array|min:1',
            'items.*.product_id'          => 'required|exists:products,id',
            'items.*.expected_quantity'    => 'required|integer|min:0',
            'items.*.received_quantity'   => 'required|integer|min:0',
            'items.*.damaged_quantity'    => 'nullable|integer|min:0',
            'items.*.missing_quantity'    => 'nullable|integer|min:0',
            'grn_file'                    => 'nullable|file|mimes:pdf,xlsx,csv|max:10240',
            'remarks'                     => 'nullable|string|max:1000',
        ]);

        $data = $request->all();
        if ($request->hasFile('grn_file')) {
            $data['grn_file'] = $request->file('grn_file')->store('grn-files/' . $shipment->company_code, 'public');
        }

        $this->logisticsService->uploadGrn($shipment, $data, $request->items);
        return redirect()->route('logistics.grn')->with('success', 'GRN uploaded successfully. Inventory updated automatically.');
    }

    // =====================================================================
    //  INVENTORY — View, Download, Transfer, Ageing, Warehouse Allocation
    // =====================================================================

    public function inventory(Request $request)
    {
        $query = Inventory::with('product.vendor', 'product.category', 'warehouse', 'subLocation', 'consignment')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($request->vendor_id, fn ($q, $v) => $q->whereHas('product', fn ($pq) => $pq->where('vendor_id', $v)))
            ->when($request->ageing, function ($q, $v) {
                return match($v) {
                    '0_30'   => $q->where('received_date', '>=', now()->subDays(30)),
                    '31_60'  => $q->whereBetween('received_date', [now()->subDays(60), now()->subDays(30)]),
                    '61_90'  => $q->whereBetween('received_date', [now()->subDays(90), now()->subDays(60)]),
                    '91_120' => $q->whereBetween('received_date', [now()->subDays(120), now()->subDays(90)]),
                    '120_plus' => $q->where('received_date', '<', now()->subDays(120)),
                    default  => $q,
                };
            })
            ->when($request->search, fn ($q, $v) => $q->whereHas('product', fn ($pq) => $pq->where('sku', 'like', "%{$v}%")->orWhere('name', 'like', "%{$v}%")));

        $inventory = $query->paginate(50)->appends($request->query());

        $warehouses = Warehouse::active()->get();
        $vendors = Vendor::active()->orderBy('company_name')->get();

        // Summary stats
        $stats = [
            'total_skus'   => Inventory::when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))->distinct('product_id')->count('product_id'),
            'total_units'  => Inventory::when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))->sum('quantity'),
            'available'    => Inventory::when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))->sum('available_quantity'),
            'reserved'     => Inventory::when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))->sum('reserved_quantity'),
        ];

        return view('logistics.inventory.index', compact('inventory', 'warehouses', 'vendors', 'stats'));
    }

    /**
     * Download inventory as CSV
     */
    public function downloadInventory(Request $request)
    {
        $records = Inventory::with('product.vendor', 'product.category', 'warehouse', 'subLocation')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->orderBy('company_code')->get();

        $csv = "SKU,Product Name,Vendor,Category,Warehouse,Sub-Location,Company Code,Quantity,Available,Reserved,Received Date,Ageing Days\n";
        foreach ($records as $r) {
            $ageing = $r->received_date ? now()->diffInDays($r->received_date) : 0;
            $csv .= implode(',', [
                $r->product->sku ?? '',
                '"' . str_replace('"', '""', $r->product->name ?? '') . '"',
                '"' . str_replace('"', '""', $r->product->vendor->company_name ?? '') . '"',
                $r->product->category->name ?? '',
                '"' . str_replace('"', '""', $r->warehouse->name ?? '') . '"',
                $r->subLocation->name ?? '',
                $r->company_code,
                $r->quantity,
                $r->available_quantity,
                $r->reserved_quantity,
                $r->received_date?->format('Y-m-d') ?? '',
                $ageing,
            ]) . "\n";
        }

        $filename = 'inventory-' . ($request->company_code ?? 'all') . '-' . date('Y-m-d') . '.csv';
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Transfer inventory between warehouses/sub-locations
     */
    public function transferInventory(Request $request)
    {
        $request->validate([
            'product_id'        => 'required|exists:products,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id'   => 'required|exists:warehouses,id',
            'quantity'          => 'required|integer|min:1',
            'from_sub_id'       => 'nullable|exists:warehouse_sub_locations,id',
            'to_sub_id'         => 'nullable|exists:warehouse_sub_locations,id',
        ]);

        // Validate sufficient stock
        $available = Inventory::where('product_id', $request->product_id)
            ->where('warehouse_id', $request->from_warehouse_id)
            ->sum('available_quantity');

        if ($available < $request->quantity) {
            return back()->with('error', "Insufficient stock. Available: {$available}, Requested: {$request->quantity}");
        }

        $this->logisticsService->transferInventory(
            $request->product_id,
            $request->from_warehouse_id,
            $request->to_warehouse_id,
            $request->quantity,
            $request->from_sub_id,
            $request->to_sub_id
        );

        return back()->with('success', 'Inventory transferred successfully.');
    }

    /**
     * Inventory ageing dashboard
     */
    public function inventoryAgeing(Request $request)
    {
        $companyCode = $request->get('company_code');

        $ageing = $this->getInventoryAgeingSummary($companyCode);

        // Detailed ageing by warehouse
        $byWarehouse = Warehouse::active()
            ->when($companyCode, fn ($q) => $q->where('company_code', $companyCode))
            ->get()->map(function ($wh) {
                return [
                    'warehouse' => $wh,
                    '0_30'      => Inventory::where('warehouse_id', $wh->id)->where('received_date', '>=', now()->subDays(30))->sum('quantity'),
                    '31_60'     => Inventory::where('warehouse_id', $wh->id)->whereBetween('received_date', [now()->subDays(60), now()->subDays(30)])->sum('quantity'),
                    '61_90'     => Inventory::where('warehouse_id', $wh->id)->whereBetween('received_date', [now()->subDays(90), now()->subDays(60)])->sum('quantity'),
                    '91_plus'   => Inventory::where('warehouse_id', $wh->id)->where('received_date', '<', now()->subDays(90))->sum('quantity'),
                ];
            });

        // GRN ageing (shipment-wise)
        $grnAgeing = Grn::with('shipment', 'warehouse')
            ->when($companyCode, fn ($q) => $q->where('company_code', $companyCode))
            ->orderBy('receipt_date')->get()->map(function ($grn) {
                $grn->ageing_days = $grn->getAgeingDays();
                return $grn;
            });

        return view('logistics.inventory.ageing', compact('ageing', 'byWarehouse', 'grnAgeing', 'companyCode'));
    }

    /**
     * Warehouse allocation — assign inventory to warehouses/sub-locations
     */
    public function warehouseAllocation(Request $request)
    {
        $companyCode = $request->get('company_code');

        $warehouses = Warehouse::with('subWarehouses', 'subLocations')
            ->active()
            ->when($companyCode, fn ($q) => $q->where('company_code', $companyCode))
            ->get();

        $inventoryByWarehouse = Warehouse::active()
            ->when($companyCode, fn ($q) => $q->where('company_code', $companyCode))
            ->withSum('inventory', 'quantity')
            ->withSum('inventory', 'available_quantity')
            ->withCount('inventory')
            ->get();

        // Movement history
        $movements = InventoryMovement::with('product', 'fromWarehouse', 'toWarehouse', 'performer')
            ->latest()->take(20)->get();

        return view('logistics.inventory.allocation', compact('warehouses', 'inventoryByWarehouse', 'movements', 'companyCode'));
    }

    // =====================================================================
    //  WAREHOUSE CHARGES
    // =====================================================================

    public function warehouseCharges(Request $request)
    {
        $charges = WarehouseCharge::with('vendor', 'warehouse')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->when($request->charge_type, fn ($q, $v) => $q->where('charge_type', $v))
            ->when($request->month, fn ($q, $v) => $q->where('charge_month', $v))
            ->when($request->year, fn ($q, $v) => $q->where('charge_year', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()->paginate(30);

        $vendors = Vendor::active()->orderBy('company_name')->get();
        $warehouses = Warehouse::active()->get();

        // Summary
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;
        $chargeSummary = [
            'inward'     => WarehouseCharge::where('charge_type', 'inward')->where('charge_month', $month)->where('charge_year', $year)->sum('calculated_amount'),
            'storage'    => WarehouseCharge::where('charge_type', 'storage')->where('charge_month', $month)->where('charge_year', $year)->sum('calculated_amount'),
            'pick_pack'  => WarehouseCharge::where('charge_type', 'pick_pack')->where('charge_month', $month)->where('charge_year', $year)->sum('calculated_amount'),
            'consumable' => WarehouseCharge::where('charge_type', 'consumable')->where('charge_month', $month)->where('charge_year', $year)->sum('calculated_amount'),
            'last_mile'  => WarehouseCharge::where('charge_type', 'last_mile')->where('charge_month', $month)->where('charge_year', $year)->sum('calculated_amount'),
            'total_variance' => WarehouseCharge::where('charge_month', $month)->where('charge_year', $year)->sum('variance'),
        ];

        return view('logistics.warehouse-charges.index', compact('charges', 'vendors', 'warehouses', 'chargeSummary'));
    }

    public function uploadChargeReceipt(Request $request, WarehouseCharge $charge)
    {
        $request->validate([
            'receipt'          => 'required|file|max:10240',
            'actual_amount'    => 'required|numeric|min:0',
            'variance_comment' => 'nullable|string|max:500',
        ]);

        $path = $request->file('receipt')->store('warehouse-receipts/' . $charge->company_code, 'public');
        $variance = $request->actual_amount - $charge->calculated_amount;
        $charge->update([
            'receipt_file'     => $path,
            'actual_amount'    => $request->actual_amount,
            'variance'         => $variance,
            'variance_comment' => $request->variance_comment,
            'status'           => 'receipt_uploaded',
            'uploaded_by'      => auth()->id(),
        ]);

        return back()->with('success', 'Receipt uploaded. Variance: $' . number_format(abs($variance), 2));
    }

    public function calculateCharges(Request $request)
    {
        $request->validate([
            'vendor_id'    => 'required|exists:vendors,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'month'        => 'required|integer|between:1,12',
            'year'         => 'required|integer|min:2020|max:2030',
        ]);
        $this->logisticsService->calculateWarehouseCharges($request->vendor_id, $request->month, $request->year, $request->warehouse_id);
        return back()->with('success', 'Warehouse charges calculated for the selected vendor/month.');
    }

    // =====================================================================
    //  ASN DOWNLOAD
    // =====================================================================

    public function downloadAsn(\App\Models\Asn $asn)
    {
        $asn->load('shipment.consignments.vendor');
        return response()->json($asn);
    }

    // =====================================================================
    //  HELPER — Inventory Ageing Summary
    // =====================================================================

    protected function getInventoryAgeingSummary(?string $companyCode = null): array
    {
        $q = fn () => Inventory::when($companyCode, fn ($q) => $q->where('company_code', $companyCode));
        return [
            '0_30'     => $q()->where('received_date', '>=', now()->subDays(30))->sum('quantity'),
            '31_60'    => $q()->whereBetween('received_date', [now()->subDays(60), now()->subDays(30)])->sum('quantity'),
            '61_90'    => $q()->whereBetween('received_date', [now()->subDays(90), now()->subDays(60)])->sum('quantity'),
            '91_120'   => $q()->whereBetween('received_date', [now()->subDays(120), now()->subDays(90)])->sum('quantity'),
            '120_plus' => $q()->where('received_date', '<', now()->subDays(120))->sum('quantity'),
        ];
    }
}
