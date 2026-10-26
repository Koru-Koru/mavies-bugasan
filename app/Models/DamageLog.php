<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageLog extends Model
{
    protected $table = 'damage_logs';
    protected $primaryKey = 'damage_log_id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'sacks' => 'integer',
        'date_logged' => 'date',
        'created_at' => 'datetime',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_id', 'inventory_id');
    }
}
