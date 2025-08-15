<?php

namespace BDCGenerator\DocumentGenerator\DTO;

class GenerateResult
{
    public function __construct(
        public string  $docxPathLocal,
        public ?string $pdfPathLocal,
        public ?string $uploadedUrl
    ) {}
}
