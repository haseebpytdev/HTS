<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Scopes\TenantScope;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'agency_id',
        'tenant_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (User $user): void {
            if ($user->tenant_id !== null) {
                return;
            }
            if ($user->agency_id) {
                $user->tenant_id = Agency::query()->whereKey($user->agency_id)->value('tenant_id');
            }
            if ($user->tenant_id === null) {
                $user->tenant_id = Tenant::defaultId();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function assignedInquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'assigned_to');
    }

    public function quotationRevisionRequests(): HasMany
    {
        return $this->hasMany(QuotationRevisionRequest::class);
    }

    public function bookingIntents(): HasMany
    {
        return $this->hasMany(BookingIntent::class);
    }

    public function exportHistories(): HasMany
    {
        return $this->hasMany(ExportHistory::class);
    }

    public function uploadedBookingDocuments(): HasMany
    {
        return $this->hasMany(BookingDocument::class, 'uploaded_by_user_id');
    }

    public function validatedBookingDocuments(): HasMany
    {
        return $this->hasMany(BookingDocument::class, 'validated_by_user_id');
    }

    public function hasAnyRole(array $roles): bool
    {
        if (! $this->role instanceof UserRole) {
            return false;
        }

        return in_array($this->role->value, $roles, true);
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        $roleValue = $this->role instanceof UserRole ? $this->role->value : (string) $this->role;
        $matrix = config('permissions.role_matrix', []);
        $permissions = $matrix[$roleValue] ?? [];

        return is_array($permissions) ? array_values($permissions) : [];
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions();
        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }
}
