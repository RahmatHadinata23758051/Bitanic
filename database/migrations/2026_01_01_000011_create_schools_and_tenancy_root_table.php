<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for multi-school ready architecture root tenancy schema.
     */
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('npsn', 20)->nullable()->unique();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->jsonb('settings')->nullable();
            $table->timestamps();
        });

        // Insert default baseline vocational school
        $defaultSchoolId = DB::table('schools')->insertGetId([
            'npsn' => '20200001',
            'name' => 'SMK Pertanian Pembangunan Negeri 1 Sukaraja',
            'slug' => 'smk-ppn-1-sukaraja',
            'address' => 'Jl. Raya Sukaraja No. 45',
            'city' => 'Sukabumi',
            'province' => 'Jawa Barat',
            'status' => 'active',
            'settings' => json_encode(['lab_name' => 'Laboratorium Smart Greenhouse & ATPH']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table) use ($defaultSchoolId) {
            $table->foreignId('school_id')->nullable()->default($defaultSchoolId)->after('id')->constrained('schools')->nullOnDelete();
        });

        Schema::table('devices', function (Blueprint $table) use ($defaultSchoolId) {
            $table->foreignId('school_id')->nullable()->default($defaultSchoolId)->after('id')->constrained('schools')->nullOnDelete();
        });

        Schema::table('activity_schedules', function (Blueprint $table) use ($defaultSchoolId) {
            $table->foreignId('school_id')->nullable()->default($defaultSchoolId)->after('id')->constrained('schools')->nullOnDelete();
        });

        // Backfill any existing users, devices, or schedules to the default vocational school
        DB::table('users')->whereNull('school_id')->update(['school_id' => $defaultSchoolId]);
        DB::table('devices')->whereNull('school_id')->update(['school_id' => $defaultSchoolId]);
        DB::table('activity_schedules')->whereNull('school_id')->update(['school_id' => $defaultSchoolId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_schedules', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });

        Schema::dropIfExists('schools');
    }
};
