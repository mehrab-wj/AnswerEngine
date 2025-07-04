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
        Schema::create('pdf_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->unsignedBigInteger('file_size');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            
            // Content fields
            $table->longText('extracted_text')->nullable();
            $table->longText('markdown_text')->nullable();
            $table->json('metadata')->nullable();
            
            // Processing info
            $table->string('driver_used')->nullable();
            $table->decimal('processing_time', 8, 2)->nullable(); // seconds
            $table->text('error_message')->nullable();
            
            // Relationships
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->timestamps();
            
            // Additional indexes for better performance
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdf_documents');
    }
};
