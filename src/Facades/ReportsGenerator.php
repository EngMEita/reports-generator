<?php

namespace Meita\ReportsGenerator\Facades;

use Illuminate\Support\Facades\Facade;
use Meita\ReportsGenerator\ReportsGeneratorManager;

class ReportsGenerator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ReportsGeneratorManager::class;
    }
}
