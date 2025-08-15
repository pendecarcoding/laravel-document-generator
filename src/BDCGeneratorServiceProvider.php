<?php

namespace BDCGenerator\DocumentGenerator;

use Illuminate\Support\ServiceProvider;
use BDCGenerator\DocumentGenerator\Contracts\Generator;
use BDCGenerator\DocumentGenerator\Generator\PhpWordGenerator;

class BDCGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bdcgenerator.php', 'bdcgenerator');

        $this->app->singleton(Generator::class, function () {
            return new PhpWordGenerator(
                config('bdcgenerator.libreoffice_bin'),
                config('bdcgenerator.temp_disk'),
                config('bdcgenerator.upload_disk'),
                config('bdcgenerator.upload_visibility'),
                (bool) config('bdcgenerator.keep_local'),
                config('bdcgenerator.docx_path'),
                config('bdcgenerator.pdf_path'),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/bdcgenerator.php' => config_path('bdcgenerator.php'),
        ], 'bdcgenerator-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'bdcgenerator-migrations');
    }
}
