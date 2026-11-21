<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('about_us')->nullable()->after('name');
            $table->text('services_offered')->nullable()->after('about_us');
            $table->string('support_phone')->nullable()->after('whatsapp_number');
            $table->string('whatsapp_session_status')->default('disconnected')->after('support_phone');
            $table->text('whatsapp_qr_code')->nullable()->after('whatsapp_session_status');
            $table->timestamp('whatsapp_connected_at')->nullable()->after('whatsapp_qr_code');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'about_us',
                'services_offered',
                'support_phone',
                'whatsapp_session_status',
                'whatsapp_qr_code',
                'whatsapp_connected_at',
            ]);
        });
    }
};
