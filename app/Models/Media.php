<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'name',
        'device_id',
        'activity_schedule_id',
        'plants',
        'plants_condition',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'caption',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'plants' => 'array',
            'plants_condition' => 'array',
            'file_size' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function activitySchedule(): BelongsTo
    {
        return $this->belongsTo(ActivitySchedule::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
