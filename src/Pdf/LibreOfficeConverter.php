<?php

namespace BDCGenerator\DocumentGenerator\Pdf;

use BDCGenerator\DocumentGenerator\Exceptions\DocumentGeneratorException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class LibreOfficeConverter
{
    public function __construct(
        private ?string $libreofficeBin = null,
    ) {}

    public function toPdf(string $docxFullPath, string $outDir): string
    {
        // 1ï¸âƒ£ Validasi
        if (!is_file($docxFullPath)) {
            throw new DocumentGeneratorException("DOCX not found: {$docxFullPath}");
        }

        if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
            throw new DocumentGeneratorException("Cannot create outDir: {$outDir}");
        }

        $driver = (string) (config('bdcgenerator.pdf_driver') ?? 'auto');
        if ($driver === 'auto') {
            $driver = config('bdcgenerator.gotenberg_url') ? 'gotenberg' : 'libreoffice_cli';
        }

        if ($driver === 'gotenberg') {
            return $this->toPdfViaGotenberg($docxFullPath, $outDir);
        }

        if (!$this->canRunLibreOfficeCli()) {
            throw new DocumentGeneratorException(
                'PDF conversion requires a server-side converter. ' .
                'Your server likely disables proc_open/exec; set BDC_PDF_DRIVER=gotenberg and configure GOTENBERG_URL.'
            );
        }

        $docxFullPath = str_replace('\\', '/', $docxFullPath);

        // 2ï¸âƒ£ Tmp output PDF
        $baseTmp = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/');
        $tmpOutDir = $baseTmp . '/pdf_' . uniqid('', true);
        mkdir($tmpOutDir, 0777, true);

        // ðŸ”¥ 3ï¸âƒ£ PROFILE LIBREOFFICE UNIK (INI KUNCI)
        $profileDir = $baseTmp . '/lo_' . uniqid('', true);
        mkdir($profileDir, 0777, true);

        Log::info('[PDF] Converting DOCX', [
            'docx'       => $docxFullPath,
            'tmpOutDir'  => $tmpOutDir,
            'profileDir' => $profileDir,
        ]);

        // 4ï¸âƒ£ Command (PERSIS seperti SSH tapi PROFILE UNIK)
        $binary = $this->libreofficeBin ?: (config('bdcgenerator.libreoffice_bin') ?: 'soffice');

        $cmd = sprintf(
            'HOME=%s TMPDIR=%s ' .
            '%s --headless --nologo --nofirststartwizard --norestore ' .
            '-env:UserInstallation=file://%s ' .
            '--convert-to pdf:writer_pdf_Export --outdir %s %s',
            escapeshellarg($baseTmp),
            escapeshellarg($baseTmp),
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
        $finalPdfPath = rtrim($outDir, "/\\") . DIRECTORY_SEPARATOR . basename($pdfPath);
        rename($pdfPath, $finalPdfPath);

        // 8ï¸âƒ£ Cleanup
        $this->cleanup($tmpOutDir, $profileDir);

        return $finalPdfPath;
    }

    private function toPdfViaGotenberg(string $docxFullPath, string $outDir): string
    {
        $baseUrl = (string) (config('bdcgenerator.gotenberg_url') ?? '');
        $baseUrl = rtrim($baseUrl, '/');
        if ($baseUrl === '') {
            throw new DocumentGeneratorException('GOTENBERG_URL is not set (bdcgenerator.gotenberg_url)');
        }

        $url = $baseUrl . '/forms/libreoffice/convert';
        $timeout = (int) (config('bdcgenerator.pdf_timeout') ?? 300);
        $verifySsl = (bool) (config('bdcgenerator.gotenberg_verify_ssl') ?? true);

        $pdfName = pathinfo($docxFullPath, PATHINFO_FILENAME) . '.pdf';
        $tmpPdf = tempnam(sys_get_temp_dir(), 'pdf_');
        if ($tmpPdf === false) {
            throw new DocumentGeneratorException('Cannot create temporary file for PDF');
        }

        try {
            $pdfBytes = $this->httpPostMultipart($url, $docxFullPath, $timeout, $verifySsl);
            if ($pdfBytes === '') {
                throw new DocumentGeneratorException('Empty PDF response from converter');
            }

            file_put_contents($tmpPdf, $pdfBytes);
            $finalPdfPath = rtrim($outDir, "/\\") . DIRECTORY_SEPARATOR . $pdfName;
            @unlink($finalPdfPath);
            rename($tmpPdf, $finalPdfPath);

            return $finalPdfPath;
        } finally {
            if (is_file($tmpPdf)) {
                @unlink($tmpPdf);
            }
        }
    }

    private function httpPostMultipart(string $url, string $docxFullPath, int $timeout, bool $verifySsl): string
    {
        if (!extension_loaded('curl')) {
            throw new DocumentGeneratorException('cURL extension is required for gotenberg driver');
        }

        $ch = curl_init();
        if ($ch === false) {
            throw new DocumentGeneratorException('Failed to initialize cURL');
        }

        $file = new \CURLFile(
            $docxFullPath,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            basename($docxFullPath)
        );

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(30, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_POSTFIELDS => ['files' => $file],
            CURLOPT_HTTPHEADER => ['Accept: application/pdf'],
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);

        $body = curl_exec($ch);
        $errNo = curl_errno($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $errNo) {
            throw new DocumentGeneratorException("HTTP converter request failed: {$err}");
        }

        if ($status < 200 || $status >= 300) {
            Log::error('[PDF] Gotenberg failed', [
                'url' => $url,
                'status' => $status,
                'body' => is_string($body) ? mb_substr($body, 0, 2000) : null,
            ]);
            throw new DocumentGeneratorException("HTTP converter returned status {$status}");
        }

        return (string) $body;
    }

    private function canRunLibreOfficeCli(): bool
    {
        return $this->isFunctionEnabled('proc_open');
    }

    private function isFunctionEnabled(string $fn): bool
    {
        if (!function_exists($fn)) return false;
        $disabled = (string) ini_get('disable_functions');
        if ($disabled === '') return true;
        $list = array_filter(array_map('trim', explode(',', $disabled)));
        return !in_array($fn, $list, true);
    }

    private function cleanup(string $tmpOutDir, string $profileDir): void
    {
        $this->deleteTree($tmpOutDir);
        $this->deleteTree($profileDir);
    }

    private function deleteTree(string $path): void
    {
        if ($path === '' || $path === '/' || $path === '\\') return;
        if (!file_exists($path)) return;

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = @scandir($path);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $this->deleteTree($path . DIRECTORY_SEPARATOR . $item);
            }
        }

        @rmdir($path);
    }
}
