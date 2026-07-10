<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory, HasUuids;

    public const GUEST = 'guest';

    public const LEGACY_GUESS = 'guess';

    public const USER = 'user';

    public const ADMIN = 'admin';

    protected $fillable = [
        'name',
        'description',
        'required_mfa_level',
    ];

    public static function default(): ?self
    {
        return self::query()
            ->whereIn('name', [self::GUEST, self::LEGACY_GUESS])
            ->orderByRaw('case when name = ? then 0 else 1 end', [self::GUEST])
            ->first();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
