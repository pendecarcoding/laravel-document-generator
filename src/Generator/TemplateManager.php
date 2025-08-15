<?php

namespace BDCGenerator\DocumentGenerator\Generator;

use BDCGenerator\DocumentGenerator\Models\DocTemplate;

class TemplateManager
{
    public function findActiveDescriptor(string $key): ?array
    {
        $row = DocTemplate::where('template_key', $key)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();

        return $row?->descriptor ?? null;
    }
}
