<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $table = 'stock_movements';
    protected $primaryKey = 'movement_id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'qty' => 'integer',
        'created_at' => 'datetime',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_id', 'inventory_id');
    }
}
