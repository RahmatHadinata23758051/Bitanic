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
        Schema::table('schools', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('district_code');
            $table->decimal('longitude', 11, 7)->nullable()->after('latitude');
            $table->string('coordinate_source', 50)->nullable()->after('longitude');
            $table->timestamp('coordinate_verified_at')->nullable()->after('coordinate_source');

            $table->index(['latitude', 'longitude'], 'schools_coordinates_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropIndex('schools_coordinates_idx');
            $table->dropColumn([
                'latitude',
                'longitude',
                'coordinate_source',
                'coordinate_verified_at',
            ]);
        });
    }
};
