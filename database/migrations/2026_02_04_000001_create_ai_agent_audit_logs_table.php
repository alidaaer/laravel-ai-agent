<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Deprecated: audit_logs merged into agent_logs table.
 * This migration now cleans up the old table if it exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ai_agent_audit_logs');
    }

    public function down(): void
    {
        // No-op: table is no longer used
    }
};
