<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivitySchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'classroom_id',
        'student_group_id',
        'device_id',
        'module_practicum_id',
        'title',
        'activity_date',
        'start_time',
        'end_time',
        'agenda',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ActivitySchedule $schedule) {
            if (empty($schedule->school_id)) {
                $schedule->school_id = School::where('slug', 'smk-ppn-1-sukaraja')->value('id') ?? School::first()?->id;
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function studentGroup(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class);
    }

    public function modulePracticum(): BelongsTo
    {
        return $this->belongsTo(ModulePracticum::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ScheduleParticipant::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Resolve the lead instructor/teacher for this schedule.
     */
    public function getLeadTeacherAttribute(): ?User
    {
        if ($this->relationLoaded('participants')) {
            $guruParticipant = $this->participants->firstWhere('role', 'guru');
            if ($guruParticipant && $guruParticipant->relationLoaded('user') && $guruParticipant->user) {
                return $guruParticipant->user;
            }
            if ($guruParticipant && $guruParticipant->user_id) {
                return User::find($guruParticipant->user_id);
            }
        } else {
            $guruParticipant = $this->participants()->where('role', 'guru')->with('user')->first();
            if ($guruParticipant?->user) {
                return $guruParticipant->user;
            }
        }

        return $this->classroom?->homeroomTeacher;
    }
}
