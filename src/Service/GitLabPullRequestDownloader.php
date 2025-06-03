<?php declare(strict_types=1);

namespace hiqdev\ComposerCiDeps\Service;

use Composer\IO\IOInterface;
use cweagans\Composer\Downloader\DownloaderBase;
use cweagans\Composer\Patch;
use Gitlab\Client;
use RuntimeException;

/**
 * Class GitLabPullRequestDownloader works with Patch objects, referencing GitLab merge requests.
 *
 * The downloader fetches the merge request from the GitLab API and creates a diff file.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 */
class GitLabPullRequestDownloader extends DownloaderBase
{
    private Client $client;

    private string $token = '';

    public function download(Patch $patch): void
    {
        if ($this->shouldSkipDownload($patch)) {
            return;
        }

        $this->client = $this->createGitLabClient();

        $mrInfo = $this->parseGitLabUrl($patch->url);
        $this->client->setUrl("https://{$mrInfo->host}");

        try {
            $this->fetchAndSaveDiff($patch, $mrInfo);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to process GitLab MR: " . $e->getMessage(), 0, $e);
        }
    }

    private function shouldSkipDownload(Patch $patch): bool
    {
        return !empty($patch->localPath)    // Don't need to re-download a patch if it has already been downloaded.
            || !isset($patch->extra['gitlab'])
            || empty($patch->url);
    }

    private function createGitLabClient(): Client
    {
        $token = getenv('GITLAB_REPO_ACCESS_TOKEN');
        if (empty($token)) {
            throw new RuntimeException('GITLAB_REPO_ACCESS_TOKEN environment variable is not set');
        }

        $this->token = $token;
        $client = new Client();
        $client->authenticate($this->token, Client::AUTH_HTTP_TOKEN);

        return $client;
    }

    private function parseGitLabUrl(string $url): GitLabMergeRequestInfo
    {
        if (!preg_match('#^https?://([^/]+)/([^/]+/[^/]+)/-/merge_requests/(\d+)#', $url, $matches)) {
            throw new RuntimeException("Invalid GitLab merge request URL: {$url}");
        }

        return new GitLabMergeRequestInfo($matches[1], $matches[2], (int)$matches[3]);
    }

    private function fetchAndSaveDiff(Patch $patch, GitLabMergeRequestInfo $mrInfo): void
    {
        $gitRemoteContext = $this->createGitRemoteContext($patch, $mrInfo);

        $this->addRemoteAndFetchBranch($gitRemoteContext, $mrInfo);

        $diffOutput = $this->createDiff($gitRemoteContext);
        $this->savePatch($patch, $diffOutput);

        $this->removeRemote($gitRemoteContext);
    }

    private function createGitRemoteContext(Patch $patch, GitLabMergeRequestInfo $mrInfo): GitRemoteContext
    {
        return (new GitRemoteContextFactory($this->client, $this->composer, $this->token))
            ->create($patch, $mrInfo);
    }

    private function addRemoteAndFetchBranch(
        GitRemoteContext $gitRemoteContext,
        GitLabMergeRequestInfo $mrInfo
    ): void {
        $this->io->write(
            "      - Fetching PR {$mrInfo->mergeRequestIid} from {$mrInfo->projectPath}",
            true,
            IOInterface::VERBOSE,
        );

        $cmd = sprintf(
            'cd %s && (git remote rm %s 2>&1 || true) && git remote add %s %s 2>&1 && git fetch %s %s 2>&1',
            escapeshellarg($gitRemoteContext->packagePath),
            $gitRemoteContext->remoteName,
            $gitRemoteContext->remoteName,
            escapeshellarg($gitRemoteContext->authorizedRepoUrl),
            $gitRemoteContext->remoteName,
            escapeshellarg($gitRemoteContext->sourceBranch)
        );

        $this->runShellCommand($cmd, "Failed to add remote");
    }

    private function createDiff(GitRemoteContext $gitRemoteContext): string
    {
        $this->io->write("      - Building diff", true, IOInterface::VERBOSE);

        $cmd = sprintf(
            'cd %s && git diff ...%s/%s 2>&1',
            escapeshellarg($gitRemoteContext->packagePath),
            $gitRemoteContext->remoteName,
            escapeshellarg($gitRemoteContext->sourceBranch),
        );

        // Get raw multiline string with all characters (including trailing spaces) intact
        $diffOutput = shell_exec($cmd);

        // Only to detect execution failures
        $this->runShellCommand($cmd, "Failed to create diff");

        return $diffOutput;
    }

    private function removeRemote(GitRemoteContext $gitRemoteContext): void
    {
        $cmd = sprintf(
            'cd %s && git remote rm %s 2>&1',
            escapeshellarg($gitRemoteContext->packagePath),
            $gitRemoteContext->remoteName,
        );
        $this->runShellCommand($cmd, "Failed to drop remote");
    }

    private function runShellCommand(string $command, string $errorMessage): void
    {
        exec($command, $output, $returnCode);
        if ($returnCode !== 0) {
            throw new RuntimeException("$errorMessage: " . implode("\n", $output));
        }
    }

    private function savePatch(Patch $patch, string $diff): void
    {
        $dir = sys_get_temp_dir() . '/composer-patches/';
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        $filename = uniqid($dir) . ".patch";
        file_put_contents($filename, $diff);

        $patch->localPath = $filename;
        $patch->sha256 = hash_file('sha256', $filename);
    }
}
