<?php


namespace MuCTS\Laravel\EloquentMulti\Providers;


use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider as Providers;
use MuCTS\Laravel\EloquentMulti\Commands\ModelMakeCommand;

class ServiceProvider extends Providers implements DeferrableProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModelMakeCommand::class
            ]);
        }
    }
}