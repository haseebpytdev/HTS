<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class IntegrationCredential extends Model
{
    protected $hidden = [
        'credential_value_encrypted',
        'secret',
    ];

    protected $fillable = [
        'integration_connection_id',
        'credential_key',
        'credential_value_encrypted',
        'is_secret',
        'key_name',
        'secret',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function getCredentialKeyAttribute(): ?string
    {
        return $this->attributes['credential_key'] ?? $this->attributes['key_name'] ?? null;
    }

    public function setCredentialKeyAttribute(?string $value): void
    {
        $this->attributes['credential_key'] = $value;
        $this->attributes['key_name'] = $value;
    }

    public function getCredentialValueEncryptedAttribute(): ?string
    {
        $raw = $this->attributes['credential_value_encrypted'] ?? $this->attributes['secret'] ?? null;
        if (! is_string($raw) || $raw === '') {
            return $raw;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            // Backward compatibility for legacy/plaintext rows.
            return $raw;
        }
    }

    public function setCredentialValueEncryptedAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['credential_value_encrypted'] = $value;
            $this->attributes['secret'] = $value;

            return;
        }

        $encrypted = Crypt::encryptString($value);
        $this->attributes['credential_value_encrypted'] = $encrypted;
        $this->attributes['secret'] = $encrypted;
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }
}
