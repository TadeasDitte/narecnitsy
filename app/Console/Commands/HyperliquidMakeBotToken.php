<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class HyperliquidMakeBotToken extends Command
{
    protected $signature = 'hyperliquid:make-bot-token {email : Email of the user account the bot acts as} {name : A label for this token, e.g. "momentum-bot-1"}';

    protected $description = 'Mint a Sanctum API token a bot can use to read market data from this app';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email {$this->argument('email')}");

            return self::FAILURE;
        }

        $token = $user->createToken($this->argument('name'))->plainTextToken;

        $this->info('Bot token created:');
        $this->line($token);

        return self::SUCCESS;
    }
}
