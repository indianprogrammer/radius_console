<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tenant-scoped stock item. Laravel would pluralise the class to
 * "inventories", but the migration creates the singular `inventory` table,
 * so the name is pinned explicitly.
 */
class Inventory extends Model
{
    use HasFactory;

    /** Categories offered in the picker; keys are stored in the enum column. */
    public const CATEGORIES = [
        'physical'  => 'Physical',
        'digital'   => 'Digital',
        'service'   => 'Service',
        'accessory' => 'Accessory',
    ];

    protected $table = 'inventory';

    protected $fillable = [
        'tenant_id',
        'sku',
        'name',
        'description',
        'category',
        'unit',
        'stock_quantity',
        'reorder_point',
        'cost_price',
        'sale_price',
        'is_active',
    ];

    protected $casts = [
        'stock_quantity'       => 'decimal:2',
        'reorder_point'        => 'decimal:2',
        'cost_price'           => 'decimal:2',
        'sale_price'           => 'decimal:2',
        'is_active'            => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    /** Stock at or below its reorder point. */
    public function scopeLowStock($query): void
    {
        $query->whereColumn('stock_quantity', '<=', 'reorder_point');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function isOutOfStock(): bool
    {
        return (float) $this->stock_quantity <= 0;
    }

    /**
     * At or below the reorder threshold. Out-of-stock counts as low so a single
     * check covers "needs restocking".
     */
    public function isLowStock(): bool
    {
        return (float) $this->stock_quantity <= (float) $this->reorder_point;
    }

    /** Value of the held quantity at cost. */
    public function stockValue(): float
    {
        return round((float) $this->stock_quantity * (float) $this->cost_price, 2);
    }
}