<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('booking_code')->unique();
            $table->string('channel')->default('whatsapp');
            $table->string('customer_phone')->index();
            $table->string('customer_name')->nullable();
            $table->date('appointment_date')->index();
            $table->string('appointment_time')->nullable();
            $table->unsignedInteger('queue_number')->default(1);
            $table->json('form_data')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('sheet_row_number')->nullable();
            $table->timestamp('sheet_synced_at')->nullable();
            $table->timestamp('customer_notified_at')->nullable();
            $table->string('notified_status')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'appointment_date', 'status']);
            $table->index(['company_id', 'customer_phone', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
