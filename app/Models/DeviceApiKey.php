<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeviceApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'key_hash',
        'key_prefix',
        'revoked_at',
        'last_used_at',
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
            'last_used_at' => 'datetime',
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
     * @return array{plain_key: string, api_key: DeviceApiKey}
     */
    public static function generateForDevice(Device $device): array
    {
        $plainKey = 'btk_'.bin2hex(random_bytes(24));
        $keyHash = hash('sha256', $plainKey);

        $apiKey = self::create([
            'device_id' => $device->id,
            'key_hash' => $keyHash,
            'key_prefix' => substr($plainKey, 0, 12),
            'revoked_at' => null,
        ]);

        return [
            'plain_key' => $plainKey,
            'api_key' => $apiKey,
        ];
    }

    /**
     * Safely rotate the API key for a device by revoking prior active keys and creating a new one.
     *
     * @return array{plain_key: string, api_key: DeviceApiKey}
     */
    public static function rotateForDevice(Device $device, ?User $actor = null): array
    {
        $school = $device->school ?? School::find($device->school_id);
        if (! $school || $school->status !== 'active') {
            throw new InvalidArgumentException('Rotasi kunci API ditolak: sekolah perangkat tidak aktif atau belum terdaftar.');
        }

        return DB::transaction(function () use ($device, $actor) {
            // Serialize rotations per device so concurrent requests cannot leave multiple active keys.
            Device::query()->whereKey($device->getKey())->lockForUpdate()->firstOrFail();

            $previousActiveKey = self::where('device_id', $device->id)
                ->whereNull('revoked_at')
                ->latest('id')
                ->first();
            $previousPrefix = $previousActiveKey?->key_prefix;

            // Revoke all existing unrevoked keys for this device
            self::where('device_id', $device->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $newKeyData = self::generateForDevice($device);

            if ($actor) {
                AuditLog::create([
                    'school_id' => $device->school_id,
                    'user_id' => $actor->id,
                    'actor_name' => $actor->name,
                    'actor_role' => $actor->role,
                    'action' => 'Rotasi Kunci API Perangkat',
                    'target_type' => Device::class,
                    'target_id' => (string) $device->id,
                    'diff' => [
                        'revoked_prefix' => $previousPrefix,
                        'new_prefix' => $newKeyData['api_key']->key_prefix,
                    ],
                    'details' => 'Kunci API untuk perangkat '.$device->code.' berhasil dirotasi. Kunci lama telah direvokasi.',
                    'ip_address' => request()?->ip() ?? '127.0.0.1',
                ]);
            }

            return $newKeyData;
        });
    }
}
