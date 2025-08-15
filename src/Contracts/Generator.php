<?php

namespace BDCGenerator\DocumentGenerator\Contracts;

use BDCGenerator\DocumentGenerator\DTO\GenerateResult;

interface Generator
{
    /** Generate dari input statis (langsung mapping values) */
    public function generate(array $params): GenerateResult;

    /** Generate berdasarkan template_key di DB (descriptor dinamis) */
    public function generateByKey(string $templateKey, array $payload): GenerateResult;
}
