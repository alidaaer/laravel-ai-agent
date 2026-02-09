<?php

namespace LaravelAIAgent\Console;

use Illuminate\Console\Command;
use LaravelAIAgent\Agent;

class ChatCommand extends Command
{
    protected $signature = 'agent:chat 
                            {agent=Agent : The agent name}
                            {--driver=openai : The AI driver to use}
                            {--model= : The model to use}';

    protected $description = 'Chat with an AI agent in the terminal';

    public function handle(): int
    {
        $agentName = $this->argument('agent');
        $driver = $this->option('driver');
        $model = $this->option('model');

        $this->info("🤖 Starting chat with {$agentName}");
        $this->info("   Driver: {$driver}");
        $this->info("   Type 'exit' to quit, 'clear' to reset conversation\n");

        $agent = Agent::make($agentName)->driver($driver);

        if ($model) {
            $agent->model($model);
        }

        while (true) {
            $input = $this->ask('You');

            if ($input === null || strtolower($input) === 'exit') {
                $this->info("\n👋 Goodbye!");
                break;
            }

            if (strtolower($input) === 'clear') {
                $agent->forget();
                $this->info("🗑️  Conversation cleared.\n");
                continue;
            }

            if (empty(trim($input))) {
                continue;
            }

            $this->line('');

            try {
                $this->output->write("<fg=cyan>🤖 {$agentName}:</> ");
                
                $response = $agent->chat($input);
                $this->line($response);

            } catch (\Throwable $e) {
                $this->error("Error: " . $e->getMessage());
            }

            $this->line('');
        }

        return Command::SUCCESS;
    }
}
