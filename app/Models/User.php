<?php

namespace App\Models;

use App\Enums\AdminLevel;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
        // NOTE: admin_level is deliberately NOT fillable — it grants
        // SuperAdmin/SiteAdmin. Set it explicitly (AdminUserController) or via
        // forceFill (seeders) so it can never be mass-assigned from request input.
        'avatar_path',
        'locale',
        'timezone',
        'last_login_at',
        'original_email',
        'email_verification_code',
        'email_verification_code_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'frozen_at' => 'datetime',
            'email_verification_code_expires_at' => 'datetime',
            'password' => 'hashed',
            'admin_level' => AdminLevel::class,
        ];
    }

    /**
     * Store emails canonically lowercased so accounts are case-insensitive —
     * users may register/sign in with any casing. Covers every write path
     * (registration, profile update, factories, seeders).
     */
    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = is_string($value)
            ? mb_strtolower(trim($value))
            : $value;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid7();
            }
        });

        static::deleting(function (self $model) {
            if ($model->admin_level === AdminLevel::SuperAdmin) {
                throw new \RuntimeException('Super admin accounts cannot be deleted.');
            }
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->admin_level === AdminLevel::SuperAdmin;
    }

    public function isSiteAdmin(): bool
    {
        return $this->admin_level === AdminLevel::SiteAdmin;
    }

    public function isAnyAdmin(): bool
    {
        return $this->admin_level !== null;
    }

    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    // Suppress Laravel's default link-based verification email.
    // Our code-based flow handles notification in VerificationCodeController.
    public function sendEmailVerificationNotification(): void {}

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function skuFeedback(): HasMany
    {
        return $this->hasMany(SkuFeedback::class);
    }
}
