<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolLicenseLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    public const ACTION_INITIALIZED = 'LICENSE_INITIALIZED';

    public const ACTION_ACTIVATED = 'ENTITLEMENT_ACTIVATED';

    public const ACTION_RENEWED = 'LICENSE_RENEWED';

    public const ACTION_DATES_CORRECTED = 'DATES_CORRECTED';

    public const ACTION_STATUS_CHANGED = 'STATUS_CHANGED';

    protected $fillable = [
        'license_id',
        'action',
        'user_id',
        'old_start_date',
        'new_start_date',
        'old_expiration_date',
        'new_expiration_date',
        'details',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'old_start_date' => 'date',
        'new_start_date' => 'date',
        'old_expiration_date' => 'date',
        'new_expiration_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(SchoolLicense::class, 'license_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
