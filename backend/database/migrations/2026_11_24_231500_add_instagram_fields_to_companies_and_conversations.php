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
            $table->string('instagram_account_id')->nullable()->index()->after('meta_app_id');
            $table->string('instagram_username')->nullable()->after('instagram_account_id');
            $table->text('instagram_access_token')->nullable()->after('instagram_username');
            $table->string('instagram_verify_token')->nullable()->after('instagram_access_token');
            $table->string('instagram_status')->default('disconnected')->after('instagram_verify_token');
            $table->timestamp('instagram_connected_at')->nullable()->after('instagram_status');
            $table->boolean('instagram_comment_reply_enabled')->default(false)->after('instagram_connected_at');
            $table->string('instagram_comment_keywords')->nullable()->after('instagram_comment_reply_enabled');
            $table->string('instagram_comment_public_reply')->nullable()->after('instagram_comment_keywords');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->string('channel')->default('whatsapp')->index()->after('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'instagram_account_id',
                'instagram_username',
                'instagram_access_token',
                'instagram_verify_token',
                'instagram_status',
                'instagram_connected_at',
                'instagram_comment_reply_enabled',
                'instagram_comment_keywords',
                'instagram_comment_public_reply',
            ]);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
