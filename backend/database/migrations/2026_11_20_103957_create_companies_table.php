<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('phone')->nullable();
            $table->string('whatsapp_number')->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('google_maps_url')->nullable();
            $table->text('working_hours')->nullable();
            $table->text('custom_system_prompt')->nullable();
            $table->string('status')->default('active'); // active, suspended, expired
            $table->string('subscription_plan')->default('monthly_pro');
            $table->timestamp('subscription_starts_at')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
