<?php

namespace App\Http\Controllers\Hod;

use App\Http\Controllers\Controller;
use App\Models\{Asn, PlatformPricing, SalesChannel};
use App\Services\{DashboardService, PricingService};
use Illuminate\Http\Request;

class HodController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected PricingService $pricingService
    ) {}

    public function dashboard(Request $request)
    {
        $data = $this->dashboardService->getHodDashboard();
        $data['pricing_stats'] = [
            'pending_preparation'  => Asn::whereIn('status', ['generated', 'locked'])->count(),
            'submitted_to_finance' => PlatformPricing::where('status', 'submitted')->distinct('asn_id')->count('asn_id'),
            'finance_approved'     => PlatformPricing::where('status', 'finance_approved')->distinct('asn_id')->count('asn_id'),
            'finalized'            => PlatformPricing::where('status', 'approved')->distinct('asn_id')->count('asn_id'),
            'rejected'             => PlatformPricing::where('status', 'rejected')->distinct('asn_id')->count('asn_id'),
        ];
        $data['recent_asns'] = Asn::with('shipment')->latest()->take(5)->get();
        return view('hod.dashboard', compact('data'));
    }

    public function asnList(Request $request)
    {
        $asns = Asn::with('shipment', 'platformPricing')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->latest()->paginate(20);

        $stats = [
            'total'         => Asn::count(),
            'needs_pricing' => Asn::whereIn('status', ['generated', 'locked'])->count(),
            'pricing_done'  => Asn::where('status', 'pricing_done')->count(),
            'finalized'     => Asn::where('status', 'finalized')->count(),
        ];

        return view('hod.asn.index', compact('asns', 'stats'));
    }

    public function preparePricing(Asn $asn)
    {
        $asn->load('shipment.consignments.vendor', 'shipment.consignments.liveSheet.items.product.category');
        $channels = SalesChannel::active()->orderBy('name')->get();
        $existingPricing = PlatformPricing::where('asn_id', $asn->id)
            ->get()
            ->keyBy(fn($p) => $p->product_id);

        // Collect all items from all consignments in this ASN's shipment
        $items = collect();
        if ($asn->shipment && $asn->shipment->consignments) {
            foreach ($asn->shipment->consignments as $consignment) {
                if (!$consignment->liveSheet) continue;
                foreach ($consignment->liveSheet->items as $lsItem) {
                    if (!$lsItem->product) continue;
                    $d = $lsItem->product_details ?? [];
                    $items->push([
                        'product_id'   => $lsItem->product_id,
                        'sku'          => $lsItem->product->sku ?? '',
                        'sap_code'     => $lsItem->product->sap_code ?? '',
                        'vendor_name'  => $consignment->vendor->company_name ?? '',
                        'quantity'     => $lsItem->quantity ?? 0,
                        'fob'          => floatval($d['final_fob'] ?? $lsItem->unit_price ?? 0),
                        'wsp'          => floatval($d['wsp'] ?? 1),
                        'product_name' => $lsItem->product->name ?? '',
                        'category'     => $lsItem->product->category->name ?? '',
                        'existing'     => $existingPricing->get($lsItem->product_id),
                    ]);
                }
            }
        }

        // Default channel pricing factors
        $channelFactors = [];
        // print_r($channels->toArray());exit;
        foreach ($channels as $ch) {
            //   print_r($ch->toArray()); 

            $channelFactors[$ch->id] = [
                'name'   => $ch->name,
                'factor' => $ch->pricing_factors ?? 1.0,
            ];
        }
        return view('hod.pricing.prepare', compact('asn', 'channels', 'items', 'channelFactors', 'existingPricing'));
    }

    public function storePricing(Request $request, Asn $asn)
    {


        $request->validate([
            'pricing'                    => 'required|array|min:1',
            'pricing.*.product_id'       => 'required|exists:products,id',
            'pricing.*.channels.*.sales_channel_id' => 'required|exists:sales_channels,id',
            'pricing.*.last_mile'                   => 'nullable|numeric|min:0',
        ]);

        $this->pricingService->preparePricing($asn, $request->pricing, auth()->user());
        return redirect()->route('hod.asn-list')->with('success', 'Pricing submitted to Finance for review.');
    }

    public function pricingStatus(Asn $asn)
    {
        $asn->load('shipment');
        $pricings = PlatformPricing::where('asn_id', $asn->id)
            ->with('product', 'salesChannel', 'preparer', 'financeReviewer', 'approver')
            ->get();

        $byChannel = $pricings->groupBy('sales_channel_id');

        $summary = [
            'total_items'      => $pricings->count(),
            'total_cost'       => $pricings->sum('cost_price'),
            'total_selling'    => $pricings->sum('selling_price'),
            'avg_margin'       => $pricings->avg('margin_percent'),
            'submitted'        => $pricings->where('status', 'submitted')->count(),
            'finance_approved' => $pricings->where('status', 'finance_approved')->count(),
            'approved'         => $pricings->where('status', 'approved')->count(),
            'rejected'         => $pricings->where('status', 'rejected')->count(),
        ];

        return view('hod.pricing.status', compact('asn', 'pricings', 'byChannel', 'summary'));
    }
    public function downloadPricing(Asn $asn)
    {
        $asn->load(
            'shipment.consignments.vendor',
            'shipment.consignments.liveSheet.items.product.category'
        );

        $channels = SalesChannel::active()->orderBy('name')->get();

        $existingPricing = PlatformPricing::where('asn_id', $asn->id)
            ->get()
            ->keyBy('product_id');

        $channelFactors = [];

        // Header
        $csv = "SKU,SAP,Vendor Name,Qty,FOB,WSP,Last Mile,Retail Price";

        foreach ($channels as $ch) {
            $csv .= "," . $ch->name;

            $channelFactors[$ch->id] = [
                'name'   => $ch->name,
                'factor' => is_numeric($ch->pricing_factors)
                    ? (float) $ch->pricing_factors
                    : 1.0, // fallback
            ];
        }
        $csv .= "\n";

        // Data rows
        if ($asn->shipment && $asn->shipment->consignments) {
            foreach ($asn->shipment->consignments as $consignment) {
                if (!$consignment->liveSheet) continue;

                foreach ($consignment->liveSheet->items as $lsItem) {
                    if (!$lsItem->product) continue;

                    $d = $lsItem->product_details ?? [];

                    $fob = (float) ($d['final_fob'] ?? $lsItem->unit_price ?? 0);
                    $wsp = (float) ($d['wsp'] ?? 0);

                    $ex = $existingPricing->get($lsItem->product_id);

                    $lastMile = $ex ? (float) ($ex->last_mile ?? 0) : 0;
                    $retailPrice = $wsp + $lastMile;

                    $csv .= '"' . ($lsItem->product->sku ?? '') . '"';
                    $csv .= ',"' . ($lsItem->product->sap_code ?? '') . '"';
                    $csv .= ',"' . ($consignment->vendor->company_name ?? '') . '"';
                    $csv .= ',' . ($lsItem->quantity ?? 0);
                    $csv .= ',' . number_format($fob, 2);
                    $csv .= ',' . number_format($wsp, 2);
                    $csv .= ',' . number_format($lastMile, 2);
                    $csv .= ',' . number_format($retailPrice, 2);

                    // Channel pricing
                    foreach ($channelFactors as $factorData) {
                        $factor = $factorData['factor'];
                        $csv .= ',' . number_format($wsp * $factor, 2);
                    }

                    $csv .= "\n";
                }
            }
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"Pricing-{$asn->asn_number}.csv\"",
        ]);
    }
    /**
     * Download Last Mile template CSV (SKU + Last Mile columns only)
     */
    public function downloadLastMileTemplate(Asn $asn)
    {
        $asn->load('shipment.consignments.vendor', 'shipment.consignments.liveSheet.items.product');
        $existingPricing = PlatformPricing::where('asn_id', $asn->id)->get()->keyBy('product_id');

        $csv = "SKU,Vendor Name,WSP,Inner Length,Inner Width,Inner Height,Weight (kg),Last Mile\n";

        if ($asn->shipment && $asn->shipment->consignments) {
            foreach ($asn->shipment->consignments as $consignment) {
                if (!$consignment->liveSheet) continue;
                foreach ($consignment->liveSheet->items as $lsItem) {
                    if (!$lsItem->product) continue;
                    $d = $lsItem->product_details ?? [];
                    $wsp = floatval($d['wsp'] ?? 0);
                    $ex = $existingPricing->get($lsItem->product_id);

                    $csv .= '"' . ($lsItem->product->sku ?? '') . '"';
                    $csv .= ',"' . ($consignment->vendor->company_name ?? '') . '"';
                    $csv .= ',' . number_format($wsp, 2);
                    $csv .= ',' . ($d['inner_length'] ?? $d['product_length'] ?? '');
                    $csv .= ',' . ($d['inner_width'] ?? $d['product_width'] ?? '');
                    $csv .= ',' . ($d['inner_height'] ?? $d['product_height'] ?? '');
                    $csv .= ',' . ($d['weight_per_unit'] ?? $d['product_weight'] ?? '');
                    $csv .= ',' . ($ex ? number_format(floatval($ex->last_mile ?? 0), 2) : '');
                    $csv .= "\n";
                }
            }
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"LastMile-Template-{$asn->asn_number}.csv\"",
        ]);
    }

    /**
     * Upload CSV to bulk update Last Mile values
     * CSV format: SKU, SAP Code, Product Name, Last Mile
     */
    public function uploadLastMile(Request $request, Asn $asn)
    {
        $request->validate([
            'last_mile_file' => 'required|file|max:5120',
        ], [
            'last_mile_file.required' => 'Please select a CSV file to upload.',
        ]);

        $file = $request->file('last_mile_file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'txt', 'xlsx'])) {
            return back()->with('error', 'File must be CSV or XLSX format.');
        }

        try {
            $asn->load('shipment.consignments.liveSheet.items.product');
            $channels = SalesChannel::active()->orderBy('name')->get();

            // Build SKU → product_id map from this ASN
            $skuMap = [];
            if ($asn->shipment && $asn->shipment->consignments) {
                foreach ($asn->shipment->consignments as $consignment) {
                    if (!$consignment->liveSheet) continue;
                    foreach ($consignment->liveSheet->items as $lsItem) {
                        if (!$lsItem->product) continue;
                        $skuMap[strtolower(trim($lsItem->product->sku))] = [
                            'product_id' => $lsItem->product_id,
                            'wsp' => floatval(($lsItem->product_details ?? [])['wsp'] ?? 0),
                            'fob' => floatval(($lsItem->product_details ?? [])['final_fob'] ?? $lsItem->unit_price ?? 0),
                        ];
                    }
                }
            }

            // Parse CSV
            $filePath = $file->store('temp', 'local');
            $fullPath = storage_path('app/' . $filePath);

            if ($ext === 'xlsx') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($fullPath);
                $rows = $spreadsheet->getActiveSheet()->toArray();
            } else {
                $rows = [];
                if (($handle = fopen($fullPath, 'r')) !== false) {
                    while (($row = fgetcsv($handle)) !== false) {
                        $rows[] = $row;
                    }
                    fclose($handle);
                }
            }

            @unlink($fullPath);

            if (count($rows) < 2) {
                return back()->with('error', 'CSV file is empty or has no data rows.');
            }

            // Find SKU and Last Mile column indexes from header
            $header = array_map(fn($h) => strtolower(trim($h ?? '')), $rows[0]);
            $skuCol = null;
            $lmCol = null;
            foreach ($header as $i => $h) {
                if (in_array($h, ['sku', 'vendor sku', 'product sku'])) $skuCol = $i;
                if (in_array($h, ['last mile', 'last_mile', 'lastmile'])) $lmCol = $i;
            }

            if ($skuCol === null) return back()->with('error', 'CSV must have a "SKU" column.');
            if ($lmCol === null) return back()->with('error', 'CSV must have a "Last Mile" column.');

            $updated = 0;
            $skipped = 0;
            $errors = [];

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $sku = strtolower(trim($row[$skuCol] ?? ''));
                $lastMile = trim($row[$lmCol] ?? '');

                if (!$sku || $lastMile === '') {
                    $skipped++;
                    continue;
                }

                if (!is_numeric($lastMile)) {
                    $errors[] = "Row " . ($i + 1) . ": Last Mile '{$lastMile}' is not a number for SKU '{$row[$skuCol]}'.";
                    continue;
                }

                $lastMile = floatval($lastMile);
                $productData = $skuMap[$sku] ?? null;

                if (!$productData) {
                    $skipped++;
                    continue;
                }

                $wsp = $productData['wsp'];
                $fob = $productData['fob'];
                $retailPrice = $wsp + $lastMile;

                // Update all PlatformPricing records for this product in this ASN
                $existingRecords = PlatformPricing::where('asn_id', $asn->id)
                    ->where('product_id', $productData['product_id'])
                    ->get();

                if ($existingRecords->isEmpty()) {
                    // Create per channel if no records exist yet
                    foreach ($channels as $ch) {
                        $factor = floatval(($ch->pricing_factors ?? [])['pricing_factor'] ?? 1.0);
                        $channelPrice = round($wsp * $factor, 2);
                        PlatformPricing::create([
                            'asn_id'          => $asn->id,
                            'product_id'      => $productData['product_id'],
                            'sales_channel_id' => $ch->id,
                            'company_code'    => $asn->company_code,
                            'cost_price'      => $fob,
                            'fob_price'       => $fob,
                            'wsp_price'       => $wsp,
                            'last_mile'       => $lastMile,
                            'retail_price'    => $retailPrice,
                            'pricing_factor'  => $factor,
                            'channel_price'   => $channelPrice,
                            'platform_price'  => $channelPrice,
                            'selling_price'   => $channelPrice,
                            'margin_percent'  => $channelPrice > 0 ? round((($channelPrice - $fob) / $channelPrice) * 100, 2) : 0,
                            'status'          => 'submitted',
                            'prepared_by'     => auth()->id(),
                        ]);
                    }
                } else {
                    // Update existing records
                    foreach ($existingRecords as $rec) {
                        $factor = floatval($rec->pricing_factor ?: 1.0);
                        $channelPrice = round($wsp * $factor, 2);
                        $rec->update([
                            'last_mile'     => $lastMile,
                            'retail_price'  => $retailPrice,
                            'channel_price' => $channelPrice,
                            'selling_price' => $channelPrice,
                            'margin_percent' => $channelPrice > 0 ? round((($channelPrice - $fob) / $channelPrice) * 100, 2) : 0,
                        ]);
                    }
                }
                $updated++;
            }

            \App\Models\ActivityLog::log('uploaded', 'last_mile_bulk', $asn, null, [
                'updated' => $updated,
                'skipped' => $skipped,
            ], "Last Mile CSV uploaded: {$updated} updated, {$skipped} skipped");

            $msg = "{$updated} SKU(s) updated.";
            if ($skipped > 0) $msg .= " {$skipped} skipped (no match or empty).";
            if (!empty($errors)) $msg .= " Errors: " . implode('; ', array_slice($errors, 0, 3));

            return back()->with($updated > 0 ? 'success' : 'error', $msg);
        } catch (\Exception $e) {
            \Log::error('Last Mile upload failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }
    public function finalizePricing(Asn $asn)
    {
        $pending = PlatformPricing::where('asn_id', $asn->id)
            ->whereNotIn('status', ['finance_approved', 'approved'])
            ->count();

        if ($pending > 0) {
            return back()->with('error', "Cannot finalize — {$pending} item(s) still pending Finance approval.");
        }

        $this->pricingService->finalizePricing($asn, auth()->user());
        return redirect()->route('hod.asn-list')->with('success', 'Pricing finalized and sent to Cataloguing Team.');
    }
}
