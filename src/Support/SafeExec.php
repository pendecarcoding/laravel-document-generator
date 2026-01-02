<?php

namespace BDCGenerator\DocumentGenerator\Support;

use Symfony\Component\Process\Process;
use BDCGenerator\DocumentGenerator\Exceptions\DocumentGeneratorException;
use Illuminate\Support\Facades\Log;

class SafeExec
{
    public static function run(array $command, ?string $cwd = null, int $timeout = 120): void
    {
        // // Buat direktori sementara untuk LibreOffice UserInstallation
        // $tempUserDir = sys_get_temp_dir() . '/libreoffice_' . uniqid();
        // if (!is_dir($tempUserDir)) {
        //     mkdir($tempUserDir, 0777, true);
        // }

        // // Set environment supaya LibreOffice tidak error
        // $env = array_merge($_ENV, [
        //     'HOME' => '/tmp',
        //     'TMPDIR' => '/tmp',
        //     'UserInstallation' => 'file://' . $tempUserDir,
        // ]);

        $p = new Process($command, $cwd);
        $p->setTimeout($timeout);
        $p->run();

        if (!$p->isSuccessful()) {
            Log::info("PDF Error :" . $p->getErrorOutput());
            throw new DocumentGeneratorException("Command failed: " . $p->getErrorOutput());
        }

        // Bersihkan direktori sementara setelah selesai
        // exec('rm -rf ' . escapeshellarg($tempUserDir));
    }
}
