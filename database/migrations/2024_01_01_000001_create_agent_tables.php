<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_conversations', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('agent_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id', 100);
            $table->string('role'); // user, assistant, system, tool
            $table->text('content');
            $table->json('tool_calls')->nullable();
            $table->string('tool_call_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('agent_conversations')
                ->onDelete('cascade');

            $table->index('conversation_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
        Schema::dropIfExists('agent_conversations');
    }
};
