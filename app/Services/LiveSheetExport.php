<?php

namespace App\Services;

use Maatwebsite\Excel\Concerns\FromArray;

class LiveSheetExport implements FromArray
{
    protected $liveSheet;

    public function __construct($liveSheet)
    {
        $this->liveSheet = $liveSheet;
    }

    public function array(): array
    {
        $data = [];

        // Header row
        $data[] = [
            'S.no','Vendor SKU','SAP Code','Barcode','Product Name',
            'Product Description','HSN Code','Duty %',
            'Length','Width','Height','Weight',
            'Material','Other Material','Color','Finish',
            'Category','Sub Category',
            'Qty In Inner Pack','Inner L','Inner W','Inner H',
            'Qty In Master Pack','Master L','Master W','Master H','Master Weight',
            'Qty Offered','Vendor FOB','Target FOB',
            'Final Qty','Total Master Cartons','CBM','Shipment CBM',
            'Final FOB','Duty','Freight Factor','Freight','Landed Cost',
            'WSP Factor','WSP','Comments'
        ];

        foreach ($this->liveSheet->items as $idx => $item) {
            $p = $item->product;
            $d = $item->product_details ?? [];

            $data[] = [
                $idx + 1,
                $p->sku ?? '',
                '',
                '',
                $p->name ?? '',
                '',
                '',
                '',
                $d['length_inches'] ?? '',
                $d['width_inches'] ?? '',
                $d['height_inches'] ?? '',
                $d['weight_grams'] ?? '',
                $d['material'] ?? '',
                '',
                $d['color'] ?? '',
                $d['finish'] ?? '',
                $d['category'] ?? '',
                $d['sub_category'] ?? '',
                '', '', '', '',
                '', '', '', '', '',
                $item->quantity,
                $item->unit_price,
                '',
                '', '', '', '',
                '', '', '', '', '',
                '', '',
                '',
                ''
            ];
        }

        return $data;
    }
}
