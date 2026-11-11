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
        Schema::create('trainer_kit_inventories', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number', 100)->unique();
            $table->string('model_name', 100)->default('Bitanic Trainer Kit v1.0');
            $table->string('hardware_revision', 50)->nullable();
            $table->string('batch_number', 100)->nullable()->index();
            $table->string('status', 50)->default('registered')->index();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->timestamp('registered_at')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });

        Schema::create('trainer_kit_sale_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('trainer_kit_inventories')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('sale_reference', 100)->index();
            $table->timestamp('sold_at')->nullable();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'sold_at']);
        });

        Schema::create('trainer_kit_lifecycle_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('trainer_kit_inventories')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->foreignId('from_school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('to_school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('from_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('to_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->text('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['inventory_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_kit_lifecycle_logs');
        Schema::dropIfExists('trainer_kit_sale_records');
        Schema::dropIfExists('trainer_kit_inventories');
    }
};
