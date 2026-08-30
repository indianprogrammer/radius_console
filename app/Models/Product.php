<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'category',        // one-time | recurring
        'default_amount',
        'unit',            // pcs, meter, month, etc.
        'is_active',
    ];

    protected $casts = [
        'default_amount'  => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function taxes(): BelongsToMany
    {
        return $this->belongsToMany(TaxRate::class, 'product_tax_rate')
            ->withTimestamps();
    }
}
