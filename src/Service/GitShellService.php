<?php declare(strict_types=1);

namespace hiqdev\ComposerCiDeps\Service;

use RuntimeException;

class GitShellService
{
    public function addRemoteAndFetch(GitRemoteContext $ctx): void
    {
        $cmd = sprintf(
            'cd %s && (git remote rm %s 2>&1 || true) && git remote add %s %s 2>&1 && git fetch %s %s 2>&1',
            escapeshellarg($ctx->packagePath),
            $ctx->remoteName,
            $ctx->remoteName,
            escapeshellarg($ctx->authorizedRepoUrl),
            $ctx->remoteName,
            escapeshellarg($ctx->sourceBranch)
        );

        $this->runShellCommand($cmd, "Failed to add and fetch remote");
    }

    public function createDiff(GitRemoteContext $ctx): string
    {
        $cmd = sprintf(
            'cd %s && git diff ...%s/%s 2>&1',
            escapeshellarg($ctx->packagePath),
            $ctx->remoteName,
            escapeshellarg($ctx->sourceBranch)
        );

        $diff = shell_exec($cmd);
        if ($diff === false || trim($diff) === '') {
            throw new RuntimeException("Failed to create diff or empty output");
        }

        return $diff;
    }

    public function removeRemote(GitRemoteContext $ctx): void
    {
        $cmd = sprintf(
            'cd %s && git remote rm %s 2>&1',
            escapeshellarg($ctx->packagePath),
            $ctx->remoteName
        );

        $this->runShellCommand($cmd, "Failed to remove remote");
    }

    private function runShellCommand(string $command, string $errorMessage): void
    {
        exec($command, $output, $code);
        if ($code !== 0) {
            throw new RuntimeException("$errorMessage:\n" . implode("\n", $output));
        }
    }
}
