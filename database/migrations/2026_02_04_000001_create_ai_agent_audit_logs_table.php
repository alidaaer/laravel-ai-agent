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
        Schema::create('ai_agent_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 50)->index();
            $table->json('data')->nullable();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            // Optional: Add index for faster queries
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_agent_audit_logs');
    }
};
