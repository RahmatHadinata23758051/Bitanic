<?php

namespace Tests\Feature;

use App\Http\Controllers\Academic\ActivityScheduleController as AcademicActivityScheduleController;
use App\Http\Controllers\Academic\ClassroomController as AcademicClassroomController;
use App\Http\Controllers\Academic\CompetencyAssessmentController as AcademicCompetencyAssessmentController;
use App\Http\Controllers\Academic\ModulePracticumController as AcademicModulePracticumController;
use App\Http\Controllers\Academic\PracticumReportController as AcademicPracticumReportController;
use App\Http\Controllers\Academic\UserDirectoryController as AcademicUserDirectoryController;
use App\Http\Controllers\ActivityScheduleController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CompetencyAssessmentController;
use App\Http\Controllers\IotMonitoringController;
use App\Http\Controllers\ModulePracticumController;
use App\Http\Controllers\PlantingMediaController;
use App\Http\Controllers\PracticumReportController;
use App\Http\Controllers\RawLogsDebuggerController;
use App\Http\Controllers\SettingsAuditController;
use App\Http\Controllers\TelemetryHistoryController;
use App\Http\Controllers\TrainerKitManagerController;
use App\Http\Controllers\UserDirectoryController;
use App\Http\Controllers\UserImportController;
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

    public function test_iot_routes_keep_their_names_and_use_domain_controllers(): void
    {
        $expectedRoutes = [
            'iot.index' => \App\Http\Controllers\IoT\IotMonitoringController::class,
            'iot-monitoring.index' => \App\Http\Controllers\IoT\IotMonitoringController::class,
            'telemetry.history' => \App\Http\Controllers\IoT\TelemetryHistoryController::class,
            'telemetry.history.export' => \App\Http\Controllers\IoT\TelemetryHistoryController::class,
            'kits.index' => \App\Http\Controllers\IoT\TrainerKitManagerController::class,
            'kits.reboot' => \App\Http\Controllers\IoT\TrainerKitManagerController::class,
            'kits.calibration' => \App\Http\Controllers\IoT\TrainerKitManagerController::class,
            'kits.api-key.rotate' => \App\Http\Controllers\IoT\TrainerKitManagerController::class,
            'rawlogs.index' => \App\Http\Controllers\IoT\RawLogsDebuggerController::class,
            'raw-logs.index' => \App\Http\Controllers\IoT\RawLogsDebuggerController::class,
            'rawlogs.inject' => \App\Http\Controllers\IoT\RawLogsDebuggerController::class,
        ];

        foreach ($expectedRoutes as $routeName => $controllerClass) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route [{$routeName}] must remain registered.");
            $this->assertSame($controllerClass, $route->getControllerClass(), "Route [{$routeName}] must use its IoT domain controller.");
        }
    }

    public function test_legacy_iot_controller_class_names_remain_compatible(): void
    {
        $legacyControllers = [
            IotMonitoringController::class => \App\Http\Controllers\IoT\IotMonitoringController::class,
            RawLogsDebuggerController::class => \App\Http\Controllers\IoT\RawLogsDebuggerController::class,
            TelemetryHistoryController::class => \App\Http\Controllers\IoT\TelemetryHistoryController::class,
            TrainerKitManagerController::class => \App\Http\Controllers\IoT\TrainerKitManagerController::class,
        ];

        foreach ($legacyControllers as $legacyClass => $domainClass) {
            $this->assertTrue(class_exists($domainClass), "Domain controller [{$domainClass}] must be autoloadable.");
            $this->assertTrue(class_exists($legacyClass), "Legacy controller [{$legacyClass}] must remain autoloadable.");
            $this->assertTrue(is_subclass_of($legacyClass, $domainClass), "Legacy controller [{$legacyClass}] must extend the domain controller.");
        }
    }

    public function test_agronomy_routes_keep_their_names_and_use_domain_controllers(): void
    {
        $expectedRoutes = [
            'analytics.index' => \App\Http\Controllers\Agronomy\AnalyticsController::class,
            'media.index' => \App\Http\Controllers\Agronomy\PlantingMediaController::class,
            'media.create' => \App\Http\Controllers\Agronomy\PlantingMediaController::class,
            'media.store' => \App\Http\Controllers\Agronomy\PlantingMediaController::class,
            'media.edit' => \App\Http\Controllers\Agronomy\PlantingMediaController::class,
            'media.update' => \App\Http\Controllers\Agronomy\PlantingMediaController::class,
            'media.destroy' => \App\Http\Controllers\Agronomy\PlantingMediaController::class,
        ];

        foreach ($expectedRoutes as $routeName => $controllerClass) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route [{$routeName}] must remain registered.");
            $this->assertSame($controllerClass, $route->getControllerClass(), "Route [{$routeName}] must use its Agronomy domain controller.");
        }

        $this->assertNull(Route::getRoutes()->getByName('media.show'), 'Route [media.show] must not be registered when the controller has no show method.');
    }

    public function test_legacy_agronomy_controller_class_names_remain_compatible(): void
    {
        $legacyControllers = [
            AnalyticsController::class => \App\Http\Controllers\Agronomy\AnalyticsController::class,
            PlantingMediaController::class => \App\Http\Controllers\Agronomy\PlantingMediaController::class,
        ];

        foreach ($legacyControllers as $legacyClass => $domainClass) {
            $this->assertTrue(class_exists($domainClass), "Domain controller [{$domainClass}] must be autoloadable.");
            $this->assertTrue(class_exists($legacyClass), "Legacy controller [{$legacyClass}] must remain autoloadable.");
            $this->assertTrue(is_subclass_of($legacyClass, $domainClass), "Legacy controller [{$legacyClass}] must extend the domain controller.");
        }
    }

    public function test_admin_routes_keep_their_names_and_use_domain_controllers(): void
    {
        $expectedRoutes = [
            'settings.index' => \App\Http\Controllers\Admin\SettingsAuditController::class,
            'settings.update' => \App\Http\Controllers\Admin\SettingsAuditController::class,
            'users.import.preview' => \App\Http\Controllers\Admin\UserImportController::class,
            'users.import.execute' => \App\Http\Controllers\Admin\UserImportController::class,
        ];

        foreach ($expectedRoutes as $routeName => $controllerClass) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route [{$routeName}] must remain registered.");
            $this->assertSame($controllerClass, $route->getControllerClass(), "Route [{$routeName}] must use its Admin domain controller.");
        }
    }

    public function test_legacy_admin_controller_class_names_remain_compatible(): void
    {
        $legacyControllers = [
            SettingsAuditController::class => \App\Http\Controllers\Admin\SettingsAuditController::class,
            UserImportController::class => \App\Http\Controllers\Admin\UserImportController::class,
        ];

        foreach ($legacyControllers as $legacyClass => $domainClass) {
            $this->assertTrue(class_exists($domainClass), "Domain controller [{$domainClass}] must be autoloadable.");
            $this->assertTrue(class_exists($legacyClass), "Legacy controller [{$legacyClass}] must remain autoloadable.");
            $this->assertTrue(is_subclass_of($legacyClass, $domainClass), "Legacy controller [{$legacyClass}] must extend the domain controller.");
        }
    }
}
