<?php

namespace BDCGenerator\DocumentGenerator\Generator;

use Carbon\Carbon;

class Filters
{
    /** @var array<string, callable> */
    private array $filters = [];

    public function __construct()
    {
        $this->register('trim', fn($v) => is_string($v) ? trim($v) : $v);
        $this->register('upper', fn($v) => is_string($v) ? mb_strtoupper($v) : $v);
        $this->register('title', fn($v) => is_string($v) ? mb_convert_case($v, MB_CASE_TITLE) : $v);

        $this->register('date_id', function ($v, $fmt = 'd MMMM yyyy') {
            return Carbon::parse($v)->locale('id')->translatedFormat($fmt);
        });

        $this->register('date_en', function ($v, $fmt = 'F j, Y') {
            return Carbon::parse($v)->locale('en')->translatedFormat($fmt);
        });

        $this->register('number_format', function ($v, $arg = '2') {
            return number_format((float)$v, (int)$arg, ',', '.');
        });

        $this->register('nip_format', fn($v) => is_string($v) ? trim($v) : $v);
    }

    public function register(string $name, callable $callable): void
    {
        $this->filters[$name] = $callable;
    }

    public function applyChain($value, array $chain): mixed
    {
        foreach ($chain as $f) {
            [$name, $arg] = array_pad(explode(':', $f, 2), 2, null);
            if (isset($this->filters[$name])) {
                $fn = $this->filters[$name];
                $value = $fn($value, $arg);
            }
        }
        return $value;
    }
}
