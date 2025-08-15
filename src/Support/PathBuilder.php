<?php

namespace BDCGenerator\DocumentGenerator\Support;

class PathBuilder
{
    public static function build(string $pattern, array $vars): string
    {
        $repl = [];
        foreach ($vars as $k => $v) $repl['{' . $k . '}'] = $v;
        return strtr($pattern, $repl);
    }

    public static function safeFilename(string $name): string
    {
        $name = preg_replace('/\s+/', '_', trim($name));
        return preg_replace('/[^A-Za-z0-9_\-\.]/', '', $name);
    }
}
