<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'key_hash',
        'revoked_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'key_hash',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function isValid(): bool
    {
        return is_null($this->revoked_at);
    }

    /**
     * Revoke this API key.
     */
    public function revoke(): bool
    {
        $this->revoked_at = now();
        return $this->save();
    }

    /**
     * Generate a new cryptographically secure API key for a device.
     *
     * @param Device $device
     * @return array{plain_key: string, api_key: DeviceApiKey}
     */
    public static function generateForDevice(Device $device): array
    {
        $plainKey = 'btk_' . bin2hex(random_bytes(24));
        $keyHash = hash('sha256', $plainKey);

        $apiKey = self::create([
            'device_id' => $device->id,
            'key_hash' => $keyHash,
            'revoked_at' => null,
        ]);

        return [
            'plain_key' => $plainKey,
            'api_key' => $apiKey,
        ];
    }
}
