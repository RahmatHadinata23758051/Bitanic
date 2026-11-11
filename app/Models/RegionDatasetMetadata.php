<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegionDatasetMetadata extends Model
{
    use HasFactory;

    protected $table = 'region_dataset_metadata';

    protected $fillable = [
        'source_name',
        'dataset_version',
        'total_provinces',
        'total_regencies',
        'total_districts',
        'checksum',
        'imported_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_provinces' => 'integer',
            'total_regencies' => 'integer',
            'total_districts' => 'integer',
            'imported_at' => 'datetime',
        ];
    }
}
