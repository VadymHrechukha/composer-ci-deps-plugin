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

        $mrInfo = (new GitLabMergeRequestUrlParser())->parse($patch->url);
        $this->client->setUrl("https://{$mrInfo->host}");

        try {
            $diff = $this->fetchDiff($patch, $mrInfo);
            $this->savePatch($patch, $diff);
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

    private function fetchDiff(Patch $patch, GitLabMergeRequestInfo $mrInfo): string
    {
        $gitShell = $this->createGitShellService();
        $gitRemoteContext = $this->createGitRemoteContext($patch, $mrInfo);

        $this->addRemoteAndFetchBranch($gitShell, $gitRemoteContext, $mrInfo);
        $diffOutput = $this->createDiff($gitShell, $gitRemoteContext);
        $gitShell->removeRemote($gitRemoteContext);

        return $diffOutput;
    }

    private function createGitShellService(): GitShellService
    {
        return new GitShellService();
    }

    private function createGitRemoteContext(Patch $patch, GitLabMergeRequestInfo $mrInfo): GitRemoteContext
    {
        return (new GitRemoteContextFactory($this->client, $this->composer, $this->token))
            ->create($patch, $mrInfo);
    }

    private function addRemoteAndFetchBranch(
        GitShellService $gitShell,
        GitRemoteContext $gitRemoteContext,
        GitLabMergeRequestInfo $mrInfo
    ): void {
        $this->io->write(
            "      - Fetching PR {$mrInfo->mergeRequestIid} from {$mrInfo->projectPath}",
            true,
            IOInterface::VERBOSE,
        );

        $gitShell->addRemoteAndFetch($gitRemoteContext);
    }

    private function createDiff(GitShellService $gitShell, GitRemoteContext $gitRemoteContext): string
    {
        $this->io->write("      - Building diff", true, IOInterface::VERBOSE);

        return $gitShell->createDiff($gitRemoteContext);
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
