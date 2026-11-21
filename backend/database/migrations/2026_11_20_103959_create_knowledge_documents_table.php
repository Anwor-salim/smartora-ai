<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title');
            $table->string('file_type'); // excel, pdf, text
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->longText('extracted_text')->nullable();
            $table->json('extracted_data')->nullable();
            $table->integer('total_items')->default(0);
            $table->string('status')->default('ready');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
