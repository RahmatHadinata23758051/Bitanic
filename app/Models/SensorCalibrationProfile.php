<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class SensorCalibrationProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'sensor_model',
        'firmware_version',
        'profile_name',
        'version',
        'parameter',
        'method',
        'parameters',
        'unit',
        'register_address',
        'min_range',
        'max_range',
        'effective_from',
        'effective_until',
        'created_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'parameters' => 'array',
            'min_range' => 'decimal:4',
            'max_range' => 'decimal:4',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function telemetryReadings(): HasMany
    {
        return $this->hasMany(TelemetryReading::class, 'calibration_profile_id');
    }

    /**
     * Determine if this calibration profile is currently active at given point in time.
     */
    public function isActive(?CarbonInterface $at = null): bool
    {
        $moment = $at ?? now();

        if ($this->effective_from > $moment) {
            return false;
        }

        if ($this->effective_until !== null && $this->effective_until <= $moment) {
            return false;
        }

        return true;
    }

    /**
     * Calculate calibrated value from immutable raw sensor register input.
     */
    public function calibrate(float $rawValue): float
    {
        $min = (float) $this->min_range;
        $max = (float) $this->max_range;
        $params = $this->parameters ?? [];

        if ($this->method === 'offset') {
            $offset = (float) ($params['offset'] ?? 0.0);
            $calibrated = $rawValue + $offset;
        } elseif ($this->method === 'two_point_vector') {
            $rawLow = (float) ($params['raw_low'] ?? 0.0);
            $rawHigh = (float) ($params['raw_high'] ?? 0.0);
            $refLow = (float) ($params['ref_low'] ?? 0.0);
            $refHigh = (float) ($params['ref_high'] ?? 0.0);

            if (abs($rawHigh - $rawLow) < 0.000001) {
                throw new InvalidArgumentException('Two-point reference vector raw_low and raw_high must not be identical.');
            }

            $slope = ($refHigh - $refLow) / ($rawHigh - $rawLow);
            $calibrated = $refLow + (($rawValue - $rawLow) * $slope);
        } else {
            throw new InvalidArgumentException("Unsupported calibration method: {$this->method}");
        }

        // Clamp safely to documented physical sensor boundary
        return (float) max($min, min($max, round($calibrated, 2)));
    }

    /**
     * Validate calibration configuration against the documented sensor specifications.
     *
     * @param  array<string, mixed>  $parameters
     *
     * @throws InvalidArgumentException
     */
    public static function validateSpecification(
        string $sensorModel,
        string $parameter,
        string $method,
        array $parameters
    ): array {
        $models = config('sensors.models', []);

        if (! array_key_exists($sensorModel, $models)) {
            throw new InvalidArgumentException("Model sensor '{$sensorModel}' tidak terdaftar pada spesifikasi perangkat keras Bitanic.");
        }

        $spec = $models[$sensorModel];
        if (! array_key_exists($parameter, $spec['parameters'])) {
            throw new InvalidArgumentException("Parameter '{$parameter}' tidak didukung oleh spesifikasi sensor '{$sensorModel}'.");
        }

        $paramSpec = $spec['parameters'][$parameter];
        if (! in_array($method, $paramSpec['supported_methods'], true)) {
            throw new InvalidArgumentException("Metode kalibrasi '{$method}' tidak diizinkan untuk parameter '{$parameter}' pada sensor '{$sensorModel}'.");
        }

        if ($method === 'offset') {
            if (! array_key_exists('offset', $parameters) || ! is_numeric($parameters['offset'])) {
                throw new InvalidArgumentException("Parameter offset numerik wajib disediakan untuk metode 'offset'.");
            }

            $offset = (float) $parameters['offset'];
            [$minOffset, $maxOffset] = $paramSpec['default_offset_bounds'];
            if ($offset < $minOffset || $offset > $maxOffset) {
                throw new InvalidArgumentException("Nilai offset {$offset} berada di luar batas aman spesifikasi [{$minOffset}, {$maxOffset}].");
            }
        } elseif ($method === 'two_point_vector') {
            foreach (['raw_low', 'raw_high', 'ref_low', 'ref_high'] as $key) {
                if (! array_key_exists($key, $parameters) || ! is_numeric($parameters[$key])) {
                    throw new InvalidArgumentException("Parameter '{$key}' numerik wajib untuk metode 'two_point_vector'.");
                }
            }

            $rawLow = (float) $parameters['raw_low'];
            $rawHigh = (float) $parameters['raw_high'];
            $refLow = (float) $parameters['ref_low'];
            $refHigh = (float) $parameters['ref_high'];

            if ($rawHigh <= $rawLow) {
                throw new InvalidArgumentException("raw_high ({$rawHigh}) harus lebih besar dari raw_low ({$rawLow}).");
            }

            if ($refHigh <= $refLow) {
                throw new InvalidArgumentException("ref_high ({$refHigh}) harus lebih besar dari ref_low ({$refLow}).");
            }

            if ($refLow < (float) $paramSpec['min_range'] || $refHigh > (float) $paramSpec['max_range']) {
                throw new InvalidArgumentException("Nilai acuan referensi [{$refLow}, {$refHigh}] berada di luar rentang fisik sensor.");
            }
        }

        return $paramSpec;
    }
}
