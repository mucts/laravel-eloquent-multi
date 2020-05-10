<?php


namespace MuCTS\Laravel\EloquentMulti\Commands;

use Illuminate\Foundation\Console\ModelMakeCommand as ConsoleModelMakeCommand;

class ModelMakeCommand extends ConsoleModelMakeCommand
{
    protected $name = 'multi:make:model';

    protected function getStub()
    {
        return $this->option('pivot')
            ? $this->resolveStubPath('/stubs/model.pivot.stub')
            : $this->resolveStubPath('/stubs/model.stub');
    }

    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = __DIR__ . $stub) ? $customPath : parent::resolveStubPath($stub);
    }
}