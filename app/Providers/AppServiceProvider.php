<?php

namespace App\Providers;

use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Remember to comment this all out when running migrate:fresh
        $bot = TelegraphBot::find(1);
        $bot->registerCommands([
            'start' => 'Just says hello',
            'help' => 'Shows you what to do',
            'invite' => 'Invites you to our Trello board',
        ])->send();
    }
}
