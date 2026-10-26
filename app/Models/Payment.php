<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $table = 'payments';
    protected $primaryKey = 'payment_id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'received_at' => 'datetime',
        'voided_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id', 'payment_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
