<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Facades;

use Illuminate\Support\Facades\Facade;
use Padosoft\Ecr17\Ecr17Client;

/**
 * @see Ecr17Client
 */
final class Ecr17 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ecr17';
    }
}
