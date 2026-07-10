<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebauthnCredential extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'credential_id_hash',
        'credential_id',
        'public_key',
        'sign_count',
        'transports',
        'last_used_at',
    ];

    protected $hidden = [
        'credential_id',
        'credential_id_hash',
        'public_key',
    ];

    protected $casts = [
        'credential_id' => 'encrypted',
        'transports' => 'array',
        'last_used_at' => 'datetime',
    ];

    public static function makeCredentialIdHash(string $credentialId): string
    {
        return hash('sha512', $credentialId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
