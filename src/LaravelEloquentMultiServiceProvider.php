<?php


namespace MuCTS\Laravel\EloquentMulti;


use Illuminate\Support\ServiceProvider;
use MuCTS\Laravel\EloquentMulti\Commands\ModelMakeCommand;

class LaravelEloquentMultiServiceProvider extends ServiceProvider
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