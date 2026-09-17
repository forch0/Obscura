<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use App\Traits\UsesUuid;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, UsesUuid;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
        'public_key',
        'encrypted_private_key',
        'encrypted_private_key_recovery',
        'recovery_code_hash',
        'recovery_code_salt',
        'recovery_code_used_at',
        'keypair_salt',
        'keypair_created_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'encrypted_private_key',
        'encrypted_private_key_recovery',
        'recovery_code_hash',
        'recovery_code_salt',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'recovery_code_used_at' => 'datetime',
            'keypair_created_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    public function hasKeypair(): bool
    {
        return $this->public_key !== null
            && $this->encrypted_private_key !== null
            && $this->keypair_salt !== null;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }
}
