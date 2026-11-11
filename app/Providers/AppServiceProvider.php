<?php

namespace App\Providers;

use App\Models\ActivitySchedule;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\Media;
use App\Models\School;
use App\Models\StudentReport;
use App\Models\TrainerKitInventory;
use App\Policies\ActivitySchedulePolicy;
use App\Policies\ClassroomPolicy;
use App\Policies\DevicePolicy;
use App\Policies\PlantingMediaPolicy;
use App\Policies\SchoolPolicy;
use App\Policies\StudentReportPolicy;
use App\Policies\TrainerKitInventoryPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Classroom::class, ClassroomPolicy::class);
        Gate::policy(StudentReport::class, StudentReportPolicy::class);
        Gate::policy(ActivitySchedule::class, ActivitySchedulePolicy::class);
        Gate::policy(Media::class, PlantingMediaPolicy::class);
        Gate::policy(Device::class, DevicePolicy::class);
        Gate::policy(School::class, SchoolPolicy::class);
        Gate::policy(TrainerKitInventory::class, TrainerKitInventoryPolicy::class);
    }
}
