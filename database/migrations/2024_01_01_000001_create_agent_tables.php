<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('agent_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id');
            $table->string('role'); // user, assistant, system, tool
            $table->text('content');
            $table->json('tool_calls')->nullable();
            $table->string('tool_call_id')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('agent_conversations')
                ->onDelete('cascade');

            $table->index('conversation_id');
            $table->index('created_at');
        });

        Schema::create('agent_tool_calls', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id');
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('tool_name');
            $table->json('arguments');
            $table->json('result')->nullable();
            $table->string('status')->default('pending'); // pending, success, failed
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('agent_conversations')
                ->onDelete('cascade');

            $table->foreign('message_id')
                ->references('id')
                ->on('agent_messages')
                ->onDelete('set null');

            $table->index('conversation_id');
            $table->index('tool_name');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_tool_calls');
        Schema::dropIfExists('agent_messages');
        Schema::dropIfExists('agent_conversations');
    }
};
