<?php


namespace MuCTS\LaravelEloquentMulti;


use Illuminate\Support\ServiceProvider;
use MuCTS\LaravelEloquentMulti\Commands\ModelMakeCommand;

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