<?php

use Illuminate\Support\Facades\Route;
use LaravelAIAgent\Http\Controllers\ChatController;

Route::middleware(config('ai-agent.widget.middleware', ['web']))
    ->prefix(config('ai-agent.widget.prefix', 'ai-agent'))
    ->group(function () {
        // Chat API endpoint
        Route::post('/chat', [ChatController::class, 'chat'])
            ->name('ai-agent.chat');
        
        // Widget JavaScript
        Route::get('/widget.js', [ChatController::class, 'widget'])
            ->name('ai-agent.widget');
        
        // Widget configuration
        Route::get('/config', [ChatController::class, 'config'])
            ->name('ai-agent.config');
    });
