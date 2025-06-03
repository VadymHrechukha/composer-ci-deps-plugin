<?php declare(strict_types=1);

namespace hiqdev\ComposerCiDeps\Service;

use RuntimeException;

final class GitShellService
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

        // Get raw multiline string with all characters (including trailing spaces) intact
        $diff = shell_exec($cmd);

        // Only to detect execution failures
        $this->runShellCommand($cmd, "Failed to create diff");

        if (trim($diff) === '') {
            throw new RuntimeException("Empty diff output - no changes detected");
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
