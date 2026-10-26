<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionLog extends Model
{
    protected $table = 'transaction_log';
    protected $primaryKey = 'log_id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'entity_id' => 'integer',
        'actor_id' => 'integer',
        'created_at' => 'datetime',
    ];
}
