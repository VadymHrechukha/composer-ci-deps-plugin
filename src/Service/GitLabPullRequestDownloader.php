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

    private GitShellService $gitShell;

    public function download(Patch $patch): void
    {
        $this->gitShell = $this->createGitShellService();

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

    private function createGitShellService(): GitShellService
    {
        return new GitShellService();
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

        $this->gitShell->removeRemote($gitRemoteContext);
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

        $this->gitShell->addRemoteAndFetch($gitRemoteContext);
    }

    private function createDiff(GitRemoteContext $gitRemoteContext): string
    {
        $this->io->write("      - Building diff", true, IOInterface::VERBOSE);

        return $this->gitShell->createDiff($gitRemoteContext);
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
