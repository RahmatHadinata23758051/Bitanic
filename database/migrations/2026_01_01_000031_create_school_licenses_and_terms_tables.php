<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Canonical School LMS Licenses Table (One School = One License)
        Schema::create('school_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')
                ->constrained('schools')
                ->cascadeOnDelete();
            $table->string('license_number', 100)->unique();
            $table->string('status', 50)->default('pending'); // pending, active, expired
            $table->string('entitlement_type', 50)->default('first_year_included'); // first_year_included, standard, enterprise
            $table->date('start_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('last_authorized_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('last_authorized_at')->nullable();
            $table->timestamps();

            $table->unique('school_id');
            $table->index(['status', 'expiration_date']);
        });

        // 2. Append-Only School License Terms History
        Schema::create('school_license_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')
                ->constrained('school_licenses')
                ->cascadeOnDelete();
            $table->unsignedInteger('term_number')->default(1);
            $table->string('term_type', 50)->default('initial_included'); // initial_included, annual_renewal, term_correction
            $table->date('start_date');
            $table->date('expiration_date');
            $table->string('status', 50)->default('active'); // active, expired, pending, superseded
            $table->foreignId('authorized_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['license_id', 'term_number']);
            $table->index('start_date');
            $table->index('expiration_date');
        });

        // 3. Append-Only School License Audit Logs
        Schema::create('school_license_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')
                ->constrained('school_licenses')
                ->cascadeOnDelete();
            $table->string('action', 100);
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->date('old_start_date')->nullable();
            $table->date('new_start_date')->nullable();
            $table->date('old_expiration_date')->nullable();
            $table->date('new_expiration_date')->nullable();
            $table->text('details')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['license_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_license_logs');
        Schema::dropIfExists('school_license_terms');
        Schema::dropIfExists('school_licenses');
    }
};
