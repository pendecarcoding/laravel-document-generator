<?php

namespace BDCGenerator\DocumentGenerator\Support;
use BDCGenerator\DocumentGenerator\Exceptions\DocumentGeneratorException;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class SafeExec
{
    public static function run(array $command, ?string $cwd = null, int $timeout = 300): void
    {
        // Base tmp (portable: nginx, litespeed, docker, bare metal)
        $baseTmp = rtrim(sys_get_temp_dir(), '/');

        // Unique LibreOffice profile per process
        $tempUserDir = $baseTmp . '/libreoffice_' . uniqid('', true);

        // Pastikan permission fleksibel
        $oldUmask = umask(0000);
        if (!mkdir($tempUserDir, 0777, true) && !is_dir($tempUserDir)) {
            umask($oldUmask);
            throw new \RuntimeException('Failed to create temp directory: ' . $tempUserDir);
        }
        umask($oldUmask);

        // Environment aman & portable
        $env = array_merge($_ENV, [
            'HOME'              => '/tmp',
            'TMPDIR'            => '/tmp',
            'UserInstallation'  => 'file://' . $tempUserDir,
            'SAL_USE_VCLPLUGIN' => 'gen',
        ]);

        try {
            $process = new Process($command, $cwd, $env);
            $process->setTimeout($timeout);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('LibreOffice PDF Error', [
                    'command' => implode(' ', $command),
                    'error'   => $process->getErrorOutput(),
                ]);

                throw new \RuntimeException(
                    'LibreOffice failed: ' . $process->getErrorOutput()
                );
            }
        } finally {
            // Cleanup TANPA permission issue
            if (is_dir($tempUserDir)) {
                exec('rm -rf ' . escapeshellarg($tempUserDir));
            }
        }
    }
}
