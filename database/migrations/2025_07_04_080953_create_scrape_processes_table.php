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
        Schema::create('scrape_processes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('url');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('status');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrape_processes');
    }
};
