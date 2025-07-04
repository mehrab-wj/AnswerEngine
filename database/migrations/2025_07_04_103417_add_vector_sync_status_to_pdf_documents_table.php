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
        Schema::table('pdf_documents', function (Blueprint $table) {
            $table->enum('vector_sync_status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('pending')
                  ->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdf_documents', function (Blueprint $table) {
            $table->dropColumn('vector_sync_status');
        });
    }
};
