<?php


namespace MuCTS\Laravel\EloquentMulti\Providers;


use Illuminate\Support\ServiceProvider as Providers;
use MuCTS\Laravel\EloquentMulti\Commands\ModelMakeCommand;

class ServiceProvider extends Providers
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