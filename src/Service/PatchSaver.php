<?php declare(strict_types=1);

namespace hiqdev\ComposerCiDeps\Service;

use cweagans\Composer\Patch;

class PatchSaver
{
    private string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        $this->baseDir = $baseDir ?? sys_get_temp_dir() . '/composer-patches/';
    }

    public function save(Patch $patch, string $diff): void
    {
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir);
        }

        $filename = uniqid($this->baseDir, true) . ".patch";
        file_put_contents($filename, $diff);

        $patch->localPath = $filename;
        $patch->sha256 = hash_file('sha256', $filename);
    }
}
