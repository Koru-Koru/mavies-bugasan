<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'customers';
    protected $primaryKey = 'customer_id';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = ['password_hash'];

    protected $casts = [
        'email_verified' => 'boolean',
        'is_active' => 'boolean',
        'is_walk_in' => 'boolean',
        'blacklisted' => 'boolean',
        'verified_at' => 'datetime',
        'blacklisted_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
        'last_login' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id', 'customer_id');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function canLogIn(): bool
    {
        return $this->email !== null && $this->password_hash !== null && $this->is_active;
    }

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
