<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;

class Admin extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'admins';
    protected $primaryKey = 'admin_id';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = ['password_hash'];

    protected $casts = [
        'is_active' => 'boolean',
        'failed_login_count' => 'integer',
        'locked_until' => 'datetime',
        'last_login' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /** No remember_token column, so remember-me is disabled. */
    public function getRememberTokenName(): ?string
    {
        return null;
    }
}
