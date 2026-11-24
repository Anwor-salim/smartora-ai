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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('whatsapp_provider')->default('waha')->after('whatsapp_number');
            $table->string('meta_phone_number_id')->nullable()->after('whatsapp_provider');
            $table->string('meta_waba_id')->nullable()->after('meta_phone_number_id');
            $table->text('meta_access_token')->nullable()->after('meta_waba_id');
            $table->string('meta_verify_token')->nullable()->after('meta_access_token');
            $table->string('meta_app_id')->nullable()->after('meta_verify_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_provider',
                'meta_phone_number_id',
                'meta_waba_id',
                'meta_access_token',
                'meta_verify_token',
                'meta_app_id',
            ]);
        });
    }
};
