<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'user_id',
        'actor_name',
        'actor_role',
        'action',
        'target_type',
        'target_id',
        'diff',
        'correlation_id',
        'details',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'diff' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('AuditLog is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('AuditLog is append-only and cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
