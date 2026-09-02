<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

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

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'subscriber_id', 'id')->where('type', 'one-time');
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    public function scopeLowStock($query): void
    {
        $query->where('stock_quantity', '<=', 'reorder_point');
    }
}