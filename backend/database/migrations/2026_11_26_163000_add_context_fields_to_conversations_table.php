<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('conversations', 'conversation_state')) {
                $table->string('conversation_state')->default('browsing')->after('status');
            }
            if (!Schema::hasColumn('conversations', 'context_data')) {
                $table->json('context_data')->nullable()->after('conversation_state');
            }
            if (!Schema::hasColumn('conversations', 'summary')) {
                $table->text('summary')->nullable()->after('context_data');
            }
            if (!Schema::hasColumn('conversations', 'ai_paused')) {
                $table->boolean('ai_paused')->default(false)->after('summary');
            }
            if (!Schema::hasColumn('conversations', 'ai_paused_at')) {
                $table->timestamp('ai_paused_at')->nullable()->after('ai_paused');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $columns = ['ai_paused_at', 'ai_paused', 'summary', 'context_data', 'conversation_state'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('conversations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
