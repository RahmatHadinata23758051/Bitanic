<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacySchoolOwnershipMigrationTest extends TestCase
{
    public function test_legacy_rows_remain_unowned_when_school_ownership_is_added(): void
    {
        $connectionName = 'ownership_migration_test';
        $originalDefault = config('database.default');

        config([
            "database.connections.{$connectionName}" => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge($connectionName);
        DB::setDefaultConnection($connectionName);

        try {
            Schema::create('schools', function (Blueprint $table): void {
                $table->id();
                $table->string('slug')->unique();
            });
            Schema::create('school_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('school_name');
                $table->string('academic_year');
                $table->string('semester');
                $table->string('course_name');
                $table->string('lead_teacher');
                $table->json('sensor_thresholds')->nullable();
                $table->timestamps();
            });
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('actor_name')->nullable();
                $table->string('actor_role')->nullable();
                $table->string('action');
                $table->text('details')->nullable();
                $table->timestamps();
            });

            DB::table('schools')->insert([
                'slug' => 'smk-ppn-1-sukaraja',
            ]);

            foreach (['Legacy School A', 'Legacy School B'] as $name) {
                DB::table('school_settings')->insert([
                    'school_name' => $name,
                    'academic_year' => '2025/2026',
                    'semester' => 'Ganjil',
                    'course_name' => 'ATPH',
                    'lead_teacher' => 'Unmapped legacy teacher',
                ]);
            }

            DB::table('audit_logs')->insert([
                'actor_name' => 'Legacy actor',
                'actor_role' => 'admin',
                'action' => 'LEGACY_ACTION',
                'details' => 'Legacy audit ownership is unknown.',
            ]);

            $migration = require database_path('migrations/2026_01_01_000021_enhance_school_settings_and_audit_logs_tables.php');
            $migration->up();

            $settings = DB::table('school_settings')->orderBy('id')->get();
            $this->assertCount(2, $settings);
            $this->assertSame(['Legacy School A', 'Legacy School B'], $settings->pluck('school_name')->all());
            $this->assertNull($settings[0]->school_id);
            $this->assertNull($settings[1]->school_id);

            $audit = DB::table('audit_logs')->first();
            $this->assertNotNull($audit);
            $this->assertNull($audit->school_id);
            $this->assertSame('LEGACY_ACTION', $audit->action);
        } finally {
            DB::disconnect($connectionName);
            DB::purge($connectionName);
            DB::setDefaultConnection($originalDefault);
        }
    }
}
