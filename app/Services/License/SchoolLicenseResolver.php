<?php

namespace App\Services\License;

use App\Models\SchoolLicense;
use App\Models\SchoolLicenseTerm;
use Carbon\Carbon;

class SchoolLicenseResolver
{
    /**
     * Resolve effective entitlement for a license on a specific date using authorized term intervals.
     * Enforces [start_date, expiration_date) boundary semantics and gap detection.
     *
     * @return array{
     *     status: string,
     *     is_active: bool,
     *     active_term: ?SchoolLicenseTerm,
     *     days_remaining: int,
     *     continuous_expiration_date: ?Carbon,
     * }
     */
    public function resolve(SchoolLicense $license, ?Carbon $date = null): array
    {
        $targetDate = ($date ? $date->copy() : Carbon::today())->startOfDay();

        // Load terms in chronological sequence
        $terms = $license->relationLoaded('terms')
            ? $license->terms->sortBy('term_number')->values()
            : $license->terms()->orderBy('term_number', 'asc')->get();

        if ($terms->isEmpty()) {
            if ($license->start_date && $license->expiration_date) {
                $start = Carbon::parse($license->start_date)->startOfDay();
                $exp = Carbon::parse($license->expiration_date)->startOfDay();

                if ($targetDate->gte($start) && $targetDate->lt($exp)) {
                    return [
                        'status' => SchoolLicense::STATUS_ACTIVE,
                        'is_active' => true,
                        'active_term' => null,
                        'days_remaining' => max(0, (int) $targetDate->diffInDays($exp, false)),
                        'continuous_expiration_date' => $exp,
                    ];
                }

                if ($targetDate->gte($exp)) {
                    return [
                        'status' => SchoolLicense::STATUS_EXPIRED,
                        'is_active' => false,
                        'active_term' => null,
                        'days_remaining' => 0,
                        'continuous_expiration_date' => null,
                    ];
                }
            }

            return [
                'status' => SchoolLicense::STATUS_PENDING,
                'is_active' => false,
                'active_term' => null,
                'days_remaining' => 0,
                'continuous_expiration_date' => null,
            ];
        }

        // 1. Find active term covering targetDate: [start_date, expiration_date)
        /** @var SchoolLicenseTerm|null $activeTerm */
        $activeTerm = null;
        $activeTermIndex = null;

        foreach ($terms as $index => $term) {
            $start = Carbon::parse($term->start_date)->startOfDay();
            $exp = Carbon::parse($term->expiration_date)->startOfDay();

            if ($targetDate->gte($start) && $targetDate->lt($exp)) {
                $activeTerm = $term;
                $activeTermIndex = $index;
                break;
            }
        }

        // 2. If active term found: resolve continuous chained expiration
        if ($activeTerm) {
            $continuousExp = Carbon::parse($activeTerm->expiration_date)->startOfDay();

            // Look ahead for seamless chaining (next term start_date <= current continuousExp)
            for ($i = $activeTermIndex + 1; $i < $terms->count(); $i++) {
                $nextTerm = $terms[$i];
                $nextStart = Carbon::parse($nextTerm->start_date)->startOfDay();
                $nextExp = Carbon::parse($nextTerm->expiration_date)->startOfDay();

                if ($nextStart->lte($continuousExp) && $nextExp->gt($continuousExp)) {
                    $continuousExp = $nextExp;
                } else {
                    break;
                }
            }

            $daysRemaining = (int) $targetDate->diffInDays($continuousExp, false);

            return [
                'status' => SchoolLicense::STATUS_ACTIVE,
                'is_active' => true,
                'active_term' => $activeTerm,
                'days_remaining' => max(0, $daysRemaining),
                'continuous_expiration_date' => $continuousExp,
            ];
        }

        // 3. No active term today: determine if pending or expired
        // Check if there was any prior term that expired before or on targetDate
        $hasPriorExpiredTerm = false;
        foreach ($terms as $term) {
            $exp = Carbon::parse($term->expiration_date)->startOfDay();
            if ($targetDate->gte($exp)) {
                $hasPriorExpiredTerm = true;
                break;
            }
        }

        // If previously entitled but no valid current term exists: EXPIRED
        if ($hasPriorExpiredTerm) {
            return [
                'status' => SchoolLicense::STATUS_EXPIRED,
                'is_active' => false,
                'active_term' => null,
                'days_remaining' => 0,
                'continuous_expiration_date' => null,
            ];
        }

        // If all terms are in the future: PENDING
        return [
            'status' => SchoolLicense::STATUS_PENDING,
            'is_active' => false,
            'active_term' => null,
            'days_remaining' => 0,
            'continuous_expiration_date' => null,
        ];
    }

    /**
     * Resolve string status (active, pending, expired).
     */
    public function resolveStatus(SchoolLicense $license, ?Carbon $date = null): string
    {
        return $this->resolve($license, $date)['status'];
    }

    /**
     * Check if license is currently active on date.
     */
    public function isActive(SchoolLicense $license, ?Carbon $date = null): bool
    {
        return $this->resolve($license, $date)['is_active'];
    }
}
