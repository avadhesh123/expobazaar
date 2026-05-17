<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id', 'warehouse_id', 'company_code', 'sku',
        'action', 'description',
        'previous_quantity', 'change_quantity', 'updated_quantity',
        'previous_available', 'change_available', 'updated_available',
        'previous_reserved', 'change_reserved', 'updated_reserved',
        'reference_type', 'reference_id', 'reference_code',
        'performed_by', 'ip_address', 'metadata', 'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relationships ──

    public function product()   { return $this->belongsTo(Product::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function performer() { return $this->belongsTo(User::class, 'performed_by'); }

    // ── Scopes ──

    public function scopeForProduct($q, int $productId) { return $q->where('product_id', $productId); }
    public function scopeForWarehouse($q, int $warehouseId) { return $q->where('warehouse_id', $warehouseId); }
    public function scopeForSku($q, string $sku) { return $q->where('sku', $sku); }
    public function scopeOfAction($q, string $action) { return $q->where('action', $action); }
    public function scopeForReference($q, string $type, int $id) { return $q->where('reference_type', $type)->where('reference_id', $id); }

    // ── Static Logger ──

    /**
     * Log an inventory change
     *
     * @param Inventory $inventory  The inventory record being changed
     * @param string    $action     Action type (see ACTION_* constants)
     * @param int       $changeQty  Positive = increase, Negative = decrease
     * @param array     $options    Optional: reference_type, reference_id, reference_code, description, metadata, change_available, change_reserved
     */
    public static function record(
        Inventory $inventory,
        string $action,
        int $changeQty,
        array $options = []
    ): self {
        $prevQty       = intval($inventory->quantity);
        $prevAvailable = intval($inventory->available_quantity);
        $prevReserved  = intval($inventory->reserved_quantity);

        $changeAvail   = $options['change_available'] ?? $changeQty;
        $changeRes     = $options['change_reserved'] ?? 0;

        return static::create([
            'product_id'         => $inventory->product_id,
            'warehouse_id'       => $inventory->warehouse_id,
            'company_code'       => $inventory->company_code,
            'sku'                => $inventory->product->sku ?? null,
            'action'             => $action,
            'description'        => $options['description'] ?? self::defaultDescription($action, abs($changeQty)),
            'previous_quantity'  => $prevQty,
            'change_quantity'    => $changeQty,
            'updated_quantity'   => $prevQty + $changeQty,
            'previous_available' => $prevAvailable,
            'change_available'   => $changeAvail,
            'updated_available'  => $prevAvailable + $changeAvail,
            'previous_reserved'  => $prevReserved,
            'change_reserved'    => $changeRes,
            'updated_reserved'   => $prevReserved + $changeRes,
            'reference_type'     => $options['reference_type'] ?? null,
            'reference_id'       => $options['reference_id'] ?? null,
            'reference_code'     => $options['reference_code'] ?? null,
            'performed_by'       => $options['performed_by'] ?? (auth()->id() ?? null),
            'ip_address'         => request()->ip() ?? null,
            'metadata'           => $options['metadata'] ?? null,
            'created_at'         => now(),
        ]);
    }

    /**
     * Quick log without an Inventory model (for product-level stock changes)
     */
    public static function recordDirect(
        int $productId,
        ?int $warehouseId,
        string $action,
        int $prevQty,
        int $changeQty,
        array $options = []
    ): self {
        $product = Product::find($productId);
        return static::create([
            'product_id'         => $productId,
            'warehouse_id'       => $warehouseId,
            'company_code'       => $options['company_code'] ?? null,
            'sku'                => $product->sku ?? null,
            'action'             => $action,
            'description'        => $options['description'] ?? self::defaultDescription($action, abs($changeQty)),
            'previous_quantity'  => $prevQty,
            'change_quantity'    => $changeQty,
            'updated_quantity'   => $prevQty + $changeQty,
            'previous_available' => $options['previous_available'] ?? null,
            'change_available'   => $options['change_available'] ?? null,
            'updated_available'  => $options['updated_available'] ?? null,
            'previous_reserved'  => $options['previous_reserved'] ?? null,
            'change_reserved'    => $options['change_reserved'] ?? null,
            'updated_reserved'   => $options['updated_reserved'] ?? null,
            'reference_type'     => $options['reference_type'] ?? null,
            'reference_id'       => $options['reference_id'] ?? null,
            'reference_code'     => $options['reference_code'] ?? null,
            'performed_by'       => $options['performed_by'] ?? (auth()->id() ?? null),
            'ip_address'         => request()->ip() ?? null,
            'metadata'           => $options['metadata'] ?? null,
            'created_at'         => now(),
        ]);
    }

    private static function defaultDescription(string $action, int $qty): string
    {
        return match($action) {
            'grn_received'     => "Received {$qty} units via GRN",
            'order_placed'     => "Reserved {$qty} units for order",
            'order_shipped'    => "Shipped {$qty} units",
            'order_cancelled'  => "Released {$qty} units from cancelled order",
            'transfer_out'     => "Transferred out {$qty} units",
            'transfer_in'      => "Transferred in {$qty} units",
            'adjustment'       => "Manual adjustment: {$qty} units",
            'return'           => "Returned {$qty} units",
            'damaged'          => "Marked {$qty} units as damaged",
            'cycle_count'      => "Cycle count adjustment: {$qty} units",
            'reserved'         => "Reserved {$qty} units",
            'unreserved'       => "Released {$qty} reserved units",
            default            => "{$action}: {$qty} units",
        };
    }

    // ── Action Constants ──

    const ACTION_GRN_RECEIVED    = 'grn_received';
    const ACTION_ORDER_PLACED    = 'order_placed';
    const ACTION_ORDER_SHIPPED   = 'order_shipped';
    const ACTION_ORDER_CANCELLED = 'order_cancelled';
    const ACTION_TRANSFER_OUT    = 'transfer_out';
    const ACTION_TRANSFER_IN     = 'transfer_in';
    const ACTION_ADJUSTMENT      = 'adjustment';
    const ACTION_RETURN          = 'return';
    const ACTION_DAMAGED         = 'damaged';
    const ACTION_CYCLE_COUNT     = 'cycle_count';
    const ACTION_RESERVED        = 'reserved';
    const ACTION_UNRESERVED      = 'unreserved';
}
