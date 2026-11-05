<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add school ownership to media records.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();
            $table->index(['school_id', 'created_at']);
        });

        // Additive backfill from explicit ownership only. Orphaned legacy rows remain
        // school_id = NULL so access fails closed rather than falsely attributing data.
        DB::statement('
            UPDATE media
            SET school_id = (SELECT school_id FROM devices WHERE devices.id = media.device_id)
            WHERE school_id IS NULL
              AND device_id IS NOT NULL
              AND EXISTS (SELECT 1 FROM devices WHERE devices.id = media.device_id AND devices.school_id IS NOT NULL)
        ');

        DB::statement('
            UPDATE media
            SET school_id = (SELECT school_id FROM users WHERE users.id = media.uploaded_by)
            WHERE school_id IS NULL
              AND uploaded_by IS NOT NULL
              AND EXISTS (SELECT 1 FROM users WHERE users.id = media.uploaded_by AND users.school_id IS NOT NULL)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'created_at']);
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });
    }
};
