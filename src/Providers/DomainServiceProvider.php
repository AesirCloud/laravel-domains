<?php

namespace AesirCloud\LaravelDomains\Providers;

use AesirCloud\LaravelDomains\Commands\MakeDomainCommand;
use AesirCloud\LaravelDomains\Commands\MakeValueObjectCommand;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Publish stub files so that users can override them if needed.
            $this->publishes([
                __DIR__.'/../../stubs' => base_path('stubs/laravel-domains'),
            ], 'ddd-stubs');

            // Register the console command.
            $this->commands([
                MakeDomainCommand::class,
                MakeValueObjectCommand::class,
            ]);
        }
    }
}