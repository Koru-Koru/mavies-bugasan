<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $table = 'inventory';
    protected $primaryKey = 'inventory_id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'archived_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'inventory_id', 'inventory_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'inventory_id', 'inventory_id');
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->low_stock_threshold;
    }
}
