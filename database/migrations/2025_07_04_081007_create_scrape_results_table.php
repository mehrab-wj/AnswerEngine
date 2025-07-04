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
        Schema::create('scrape_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scrape_process_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->longText('content');
            $table->string('source_url')->nullable();
            $table->string('author')->nullable();
            $table->json('internal_links')->nullable();
            $table->json('external_links')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('scrape_process_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrape_results');
    }
};
