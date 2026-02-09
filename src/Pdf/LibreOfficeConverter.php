<?php

namespace BDCGenerator\DocumentGenerator\Pdf;

use BDCGenerator\DocumentGenerator\Exceptions\DocumentGeneratorException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class LibreOfficeConverter
{
    public function toPdf(string $docxFullPath, string $outDir): string
    {
        // 1ï¸âƒ£ Validasi
        if (!is_file($docxFullPath)) {
            throw new DocumentGeneratorException("DOCX not found: {$docxFullPath}");
        }

        if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
            throw new DocumentGeneratorException("Cannot create outDir: {$outDir}");
        }

        $docxFullPath = str_replace('\\', '/', $docxFullPath);

        // 2ï¸âƒ£ Tmp output PDF
        $tmpOutDir = '/tmp/pdf_' . uniqid('', true);
        mkdir($tmpOutDir, 0777, true);

        // ðŸ”¥ 3ï¸âƒ£ PROFILE LIBREOFFICE UNIK (INI KUNCI)
        $profileDir = '/tmp/lo_' . uniqid('', true);
        mkdir($profileDir, 0777, true);

        Log::info('[PDF] Converting DOCX', [
            'docx'       => $docxFullPath,
            'tmpOutDir'  => $tmpOutDir,
            'profileDir' => $profileDir,
        ]);

        // 4ï¸âƒ£ Command (PERSIS seperti SSH tapi PROFILE UNIK)
        $binary = config('bdc.libreoffice_bin') ?: '/usr/bin/libreoffice';

        $cmd = sprintf(
            'HOME=/tmp TMPDIR=/tmp ' .
            '%s --headless --nologo --nofirststartwizard --norestore ' .
            '-env:UserInstallation=file://%s ' .
            '--convert-to pdf:writer_pdf_Export --outdir %s %s',
            escapeshellcmd($binary),
            escapeshellarg($profileDir),
            escapeshellarg($tmpOutDir),
            escapeshellarg($docxFullPath)
        );

        // 5ï¸âƒ£ Jalankan via bash (WAJIB di server kamu)
        $process = new Process(['/bin/bash', '-lc', $cmd]);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('[PDF] LibreOffice failed', [
                'cmd'    => $cmd,
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ]);

            $this->cleanup($tmpOutDir, $profileDir);
            throw new DocumentGeneratorException('LibreOffice conversion failed');
        }

        // 6ï¸âƒ£ Ambil PDF
        $pdfFiles = glob($tmpOutDir . '/*.pdf');

        if (empty($pdfFiles)) {
            Log::error('[PDF] Output empty', [
                'tmpOutDir_ls' => @scandir($tmpOutDir),
            ]);

            $this->cleanup($tmpOutDir, $profileDir);
            throw new DocumentGeneratorException('PDF not generated');
        }

        $pdfPath = $pdfFiles[0];

        // 7ï¸âƒ£ Pindahkan ke tujuan final
        $finalPdfPath = rtrim($outDir, '/') . '/' . basename($pdfPath);
        rename($pdfPath, $finalPdfPath);

        // 8ï¸âƒ£ Cleanup
        $this->cleanup($tmpOutDir, $profileDir);

        return $finalPdfPath;
    }

    private function cleanup(string $tmpOutDir, string $profileDir): void
    {
        @exec('rm -rf ' . escapeshellarg($tmpOutDir));
        @exec('rm -rf ' . escapeshellarg($profileDir));
    }
}
