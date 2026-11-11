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
        Schema::create('school_license_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')
                ->constrained('school_licenses')
                ->cascadeOnDelete();
            $table->foreignId('term_id')
                ->nullable()
                ->constrained('school_license_terms')
                ->nullOnDelete();
            $table->foreignId('school_id')
                ->constrained('schools')
                ->cascadeOnDelete();
            $table->string('recipient_email', 255);
            $table->string('recipient_name', 255)->nullable();
            $table->string('notification_type', 50)->default('reminder_h14');
            $table->string('channel', 50)->default('mail');
            $table->string('status', 50)->default('queued'); // queued, sent, failed, skipped
            $table->date('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(
                ['license_id', 'term_id', 'notification_type', 'recipient_email'],
                'uniq_lic_notif_rcpt'
            );
            $table->index(['school_id', 'status']);
            $table->index(['scheduled_for', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_license_notifications');
    }
};
