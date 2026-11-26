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
            $table->string('messenger_page_id')->nullable()->index()->after('instagram_comment_public_reply');
            $table->string('messenger_page_name')->nullable()->after('messenger_page_id');
            $table->text('messenger_access_token')->nullable()->after('messenger_page_name');
            $table->string('messenger_verify_token')->nullable()->after('messenger_access_token');
            $table->string('messenger_status')->default('disconnected')->after('messenger_verify_token');
            $table->timestamp('messenger_connected_at')->nullable()->after('messenger_status');
            $table->boolean('messenger_comment_reply_enabled')->default(false)->after('messenger_connected_at');
            $table->string('messenger_comment_keywords')->nullable()->after('messenger_comment_reply_enabled');
            $table->string('messenger_comment_public_reply')->nullable()->after('messenger_comment_keywords');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'messenger_page_id',
                'messenger_page_name',
                'messenger_access_token',
                'messenger_verify_token',
                'messenger_status',
                'messenger_connected_at',
                'messenger_comment_reply_enabled',
                'messenger_comment_keywords',
                'messenger_comment_public_reply',
            ]);
        });
    }
};
