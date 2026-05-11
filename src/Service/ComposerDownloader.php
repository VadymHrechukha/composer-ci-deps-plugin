<?php
declare(strict_types=1);

namespace hiqdev\ComposerCiDeps\Service;

use cweagans\Composer\Patch;

/**
 * Replaces the original ComposerDownloader to control downloader ordering —
 * the original must be disabled so GitLabPullRequestDownloader runs first.
 * Also adds support for patch paths local to the project root.
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

        $sourcePath = $this->findLocalFile($patch->url);
        if ($sourcePath !== null) {
            $patch->localPath = $this->copyToTempFile($sourcePath);
            $patch->sha256 = hash_file('sha256', $patch->localPath);
            return;
        }

        parent::download($patch);
    }

    private function isAlreadyDownloaded(Patch $patch): bool
    {
        return !empty($patch->localPath);
    }

    private function findLocalFile(string $url): ?string
    {
        $rootDir = dirname($this->composer->getConfig()->get('vendor-dir'));
        $path = $rootDir . DIRECTORY_SEPARATOR . $url;

        return file_exists($path) ? $path : null;
    }

    private function copyToTempFile(string $sourcePath): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'composer-patch-') . '.patch';
        copy($sourcePath, $tempPath);

        return $tempPath;
    }
}
