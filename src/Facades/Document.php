<?php

namespace BDCGenerator\DocumentGenerator\Facades;

use Illuminate\Support\Facades\Facade;
use BDCGenerator\DocumentGenerator\Contracts\Generator;

class Document extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Generator::class;
    }
}
