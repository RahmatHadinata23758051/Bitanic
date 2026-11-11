<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Indonesian administrative region reference hierarchy.
     */
    public function up(): void
    {
        // 1. Provinces (Provinsi)
        Schema::create('provinces', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('name', 100)->index();
            $table->timestamps();
        });

        // 2. Regencies / Cities (Kabupaten / Kota)
        Schema::create('regencies', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('province_code', 10)->index();
            $table->string('name', 100)->index();
            $table->timestamps();

            $table->foreign('province_code')
                ->references('code')
                ->on('provinces')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index(['province_code', 'name']);
        });

        // 3. Districts (Kecamatan)
        Schema::create('districts', function (Blueprint $table) {
            $table->string('code', 15)->primary();
            $table->string('regency_code', 10)->index();
            $table->string('name', 100)->index();
            $table->timestamps();

            $table->foreign('regency_code')
                ->references('code')
                ->on('regencies')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index(['regency_code', 'name']);
        });

        // 4. Region Dataset Metadata
        Schema::create('region_dataset_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('source_name', 255);
            $table->string('dataset_version', 50);
            $table->unsignedInteger('total_provinces')->default(0);
            $table->unsignedInteger('total_regencies')->default(0);
            $table->unsignedInteger('total_districts')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('region_dataset_metadata');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('regencies');
        Schema::dropIfExists('provinces');
    }
};
