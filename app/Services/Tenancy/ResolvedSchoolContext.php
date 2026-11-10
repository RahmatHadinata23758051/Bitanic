<?php

namespace App\Services\Tenancy;

use App\Models\School;
use Illuminate\Support\Collection;

class ResolvedSchoolContext
{
    /**
     * @param  Collection<int, School>  $schools
     */
    public function __construct(
        public readonly ?int $targetSchoolId,
        public readonly ?int $selectedSchoolId,
        public readonly Collection $schools,
        public readonly bool $isSuperuser,
        public readonly ?School $school = null,
        public readonly string $schoolName = 'Belum Dikonfigurasi'
    ) {}

    /**
     * Check if context is scoped to a specific school.
     */
    public function isScoped(): bool
    {
        return $this->targetSchoolId !== null;
    }

    /**
     * Check if context is multi-school global view.
     */
    public function isGlobal(): bool
    {
        return $this->targetSchoolId === null;
    }
}
