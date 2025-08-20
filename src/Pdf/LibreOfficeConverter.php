<?php

namespace BDCGenerator\DocumentGenerator\Pdf;

use BDCGenerator\DocumentGenerator\Support\SafeExec;
use BDCGenerator\DocumentGenerator\Exceptions\DocumentGeneratorException;
use Illuminate\Support\Facades\Log;

class LibreOfficeConverter
{
    public function __construct(private string $binary = 'soffice') {}

    public function toPdf(string $docxFullPath, string $outDir): string
    {
        if (!is_file($docxFullPath)) {
            throw new DocumentGeneratorException("DOCX not found: $docxFullPath");
        }
        if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
            throw new DocumentGeneratorException("Cannot create outDir: $outDir");
        }
        Log::info("path Docx :" . $docxFullPath);
        $docxFullPath = str_replace('\\', '/', $docxFullPath);
        SafeExec::run([$this->binary, '--headless', '--convert-to', 'pdf', '--outdir', $outDir, $docxFullPath]);

        $pdfPath = preg_replace('/\.docx$/i', '.pdf', $outDir . '/' . basename($docxFullPath));
        if (!is_file($pdfPath)) {
            throw new DocumentGeneratorException("PDF not generated");
        }
        return $pdfPath;
    }
}
