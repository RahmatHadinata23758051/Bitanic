<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add regional reference foreign keys to schools table.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('province_code', 10)->nullable()->after('province');
            $table->string('regency_code', 10)->nullable()->after('city');
            $table->string('district_code', 15)->nullable()->after('regency_code');

            $table->foreign('province_code')->references('code')->on('provinces')->nullOnDelete();
            $table->foreign('regency_code')->references('code')->on('regencies')->nullOnDelete();
            $table->foreign('district_code')->references('code')->on('districts')->nullOnDelete();

            $table->index(['province_code', 'regency_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropIndex(['province_code', 'regency_code']);
            $table->dropForeign(['district_code']);
            $table->dropForeign(['regency_code']);
            $table->dropForeign(['province_code']);
            $table->dropColumn(['district_code', 'regency_code', 'province_code']);
        });
    }
};
