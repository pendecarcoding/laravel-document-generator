<?php

namespace BDCGenerator\DocumentGenerator\Support;

use Symfony\Component\Process\Process;
use BDCGenerator\DocumentGenerator\Exceptions\DocumentGeneratorException;

class SafeExec
{
    public static function run(array $command, ?string $cwd = null, int $timeout = 120): void
    {
        $p = new Process($command, $cwd);
        $p->setTimeout($timeout);
        $p->run();

        if (!$p->isSuccessful()) {
            throw new DocumentGeneratorException("Command failed: " . $p->getErrorOutput());
        }
    }
}
