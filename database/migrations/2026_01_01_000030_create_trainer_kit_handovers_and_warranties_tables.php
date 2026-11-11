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
        // 1. Additive columns to trainer_kit_inventories
        Schema::table('trainer_kit_inventories', function (Blueprint $table) {
            $table->date('handover_date')->nullable()->after('notes');
            $table->date('warranty_start_date')->nullable()->after('handover_date');
            $table->date('warranty_expiration_date')->nullable()->after('warranty_start_date');

            $table->index('warranty_expiration_date');
            $table->index(['school_id', 'warranty_expiration_date']);
        });

        // 2. Physical Trainer Kit Handover Records (BAST)
        Schema::create('trainer_kit_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')
                ->constrained('trainer_kit_inventories')
                ->cascadeOnDelete();
            $table->foreignId('school_id')
                ->constrained('schools')
                ->cascadeOnDelete();
            $table->string('bast_number', 100);
            $table->date('handover_date');
            $table->string('recipient_name', 150);
            $table->string('recipient_title', 150)->nullable();
            $table->string('recipient_phone', 50)->nullable();
            $table->date('warranty_start_date')->nullable();
            $table->date('warranty_expiration_date')->nullable();
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('bast_number');
            $table->index(['school_id', 'handover_date']);
        });

        // 3. Warranty Modification and Correction Audit Trail
        Schema::create('trainer_kit_warranty_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')
                ->constrained('trainer_kit_inventories')
                ->cascadeOnDelete();
            $table->foreignId('handover_id')
                ->nullable()
                ->constrained('trainer_kit_handovers')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->date('old_start_date')->nullable();
            $table->date('new_start_date');
            $table->date('old_expiration_date')->nullable();
            $table->date('new_expiration_date');
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['inventory_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_kit_warranty_logs');
        Schema::dropIfExists('trainer_kit_handovers');

        Schema::table('trainer_kit_inventories', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'warranty_expiration_date']);
            $table->dropIndex(['warranty_expiration_date']);

            $table->dropColumn([
                'handover_date',
                'warranty_start_date',
                'warranty_expiration_date',
            ]);
        });
    }
};
