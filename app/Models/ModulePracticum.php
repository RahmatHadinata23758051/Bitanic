<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModulePracticum extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_number',
        'title',
        'subtitle',
        'duration_minutes',
        'badge',
        'capaian_pembelajaran',
        'tujuan_pembelajaran',
        'alat_bahan',
        'theory_summary',
        'wiring_pinout',
        'steps',
        'rubric',
        'lks_questions',
    ];

    protected function casts(): array
    {
        return [
            'meeting_number' => 'integer',
            'duration_minutes' => 'integer',
            'tujuan_pembelajaran' => 'array',
            'alat_bahan' => 'array',
            'wiring_pinout' => 'array',
            'steps' => 'array',
            'rubric' => 'array',
            'lks_questions' => 'array',
        ];
    }

    public function studentReports(): HasMany
    {
        return $this->hasMany(StudentReport::class, 'module_id');
    }
}
