<?php

namespace Tests\Feature;

use App\Http\Controllers\Academic\ActivityScheduleController as AcademicActivityScheduleController;
use App\Http\Controllers\Academic\ClassroomController as AcademicClassroomController;
use App\Http\Controllers\Academic\CompetencyAssessmentController as AcademicCompetencyAssessmentController;
use App\Http\Controllers\Academic\ModulePracticumController as AcademicModulePracticumController;
use App\Http\Controllers\Academic\PracticumReportController as AcademicPracticumReportController;
use App\Http\Controllers\Academic\UserDirectoryController as AcademicUserDirectoryController;
use App\Http\Controllers\ActivityScheduleController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CompetencyAssessmentController;
use App\Http\Controllers\ModulePracticumController;
use App\Http\Controllers\PracticumReportController;
use App\Http\Controllers\UserDirectoryController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ControllerDomainNamespaceTest extends TestCase
{
    public function test_academic_routes_keep_their_names_and_use_domain_controllers(): void
    {
        $expectedRoutes = [
            'schedules.index' => AcademicActivityScheduleController::class,
            'schedules.store' => AcademicActivityScheduleController::class,
            'schedules.destroy' => AcademicActivityScheduleController::class,
            'classes.index' => AcademicClassroomController::class,
            'classes.store' => AcademicClassroomController::class,
            'classes.update' => AcademicClassroomController::class,
            'classes.destroy' => AcademicClassroomController::class,
            'classes.groups.store' => AcademicClassroomController::class,
            'groups.update' => AcademicClassroomController::class,
            'groups.destroy' => AcademicClassroomController::class,
            'students.allocate' => AcademicClassroomController::class,
            'assessment.index' => AcademicCompetencyAssessmentController::class,
            'assessment.export' => AcademicCompetencyAssessmentController::class,
            'modules.index' => AcademicModulePracticumController::class,
            'modules.show' => AcademicModulePracticumController::class,
            'modules.submit-lks' => AcademicModulePracticumController::class,
            'modules.grade-report' => AcademicModulePracticumController::class,
            'modules.request-revision' => AcademicModulePracticumController::class,
            'reports.index' => AcademicPracticumReportController::class,
            'users.index' => AcademicUserDirectoryController::class,
        ];

        foreach ($expectedRoutes as $routeName => $controllerClass) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route [{$routeName}] must remain registered.");
            $this->assertSame($controllerClass, $route->getControllerClass(), "Route [{$routeName}] must use its Academic domain controller.");
        }
    }

    public function test_legacy_controller_class_names_remain_compatible(): void
    {
        $legacyControllers = [
            ActivityScheduleController::class => AcademicActivityScheduleController::class,
            ClassroomController::class => AcademicClassroomController::class,
            CompetencyAssessmentController::class => AcademicCompetencyAssessmentController::class,
            ModulePracticumController::class => AcademicModulePracticumController::class,
            PracticumReportController::class => AcademicPracticumReportController::class,
            UserDirectoryController::class => AcademicUserDirectoryController::class,
        ];

        foreach ($legacyControllers as $legacyClass => $domainClass) {
            $this->assertTrue(class_exists($domainClass), "Domain controller [{$domainClass}] must be autoloadable.");
            $this->assertTrue(class_exists($legacyClass), "Legacy controller [{$legacyClass}] must remain autoloadable.");
            $this->assertTrue(is_subclass_of($legacyClass, $domainClass), "Legacy controller [{$legacyClass}] must extend the domain controller.");
        }
    }
}
