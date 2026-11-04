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
        Schema::table('device_api_keys', function (Blueprint $table) {
            $table->string('key_prefix', 16)->nullable()->after('key_hash');
            $table->timestamp('last_used_at')->nullable()->after('revoked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_api_keys', function (Blueprint $table) {
            $table->dropColumn(['key_prefix', 'last_used_at']);
        });
    }
};
