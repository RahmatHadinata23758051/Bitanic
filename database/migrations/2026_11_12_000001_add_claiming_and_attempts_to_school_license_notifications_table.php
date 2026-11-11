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
        Schema::table('school_license_notifications', function (Blueprint $table) {
            $table->timestamp('claimed_at')->nullable()->after('scheduled_for');
            $table->unsignedTinyInteger('attempts')->default(0)->after('claimed_at');

            $table->index(['status', 'claimed_at'], 'idx_notif_status_claimed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_license_notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notif_status_claimed');
            $table->dropColumn(['claimed_at', 'attempts']);
        });
    }
};
