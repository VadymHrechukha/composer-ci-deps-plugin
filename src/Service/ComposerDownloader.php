<?php
declare(strict_types=1);

namespace hiqdev\ComposerCiDeps\Service;

use cweagans\Composer\Patch;

/**
 * Class ComposerDownloader is a wrapper for the original ComposerDownloader class,
 * as we need to change the order of downloaders by disabling the original one.
 *
 * Also adds support for local file patch paths (relative to the project root).
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 */
class ComposerDownloader extends \cweagans\Composer\Downloader\ComposerDownloader
{
    public function download(Patch $patch): void
    {
        if ($this->isAlreadyDownloaded($patch)) {
            return;
        }

        $localPath = $this->resolveLocalPath($patch->url);
        if ($localPath !== null) {
            $patch->localPath = $localPath;
            return;
        }

        parent::download($patch);
    }

    private function isAlreadyDownloaded(Patch $patch): bool
    {
        return !empty($patch->localPath);
    }

    private function resolveLocalPath(string $url): ?string
    {
        $rootDir = dirname($this->composer->getConfig()->get('vendor-dir'));
        $path = $rootDir . DIRECTORY_SEPARATOR . $url;

        return file_exists($path) ? $path : null;
    }
}
