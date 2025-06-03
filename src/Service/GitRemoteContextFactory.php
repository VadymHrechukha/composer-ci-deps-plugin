<?php declare(strict_types=1);

namespace hiqdev\ComposerCiDeps\Service;

use Composer\Composer;
use cweagans\Composer\Patch;
use Gitlab\Client;
use RuntimeException;

class GitRemoteContextFactory
{
    public function __construct(
        readonly private Client $gitlabClient,
        readonly private Composer $composer,
        readonly private string $token
    ) {}

    public function create(Patch $patch, GitLabMergeRequestInfo $mrInfo): GitRemoteContext
    {
        $mergeRequest = $this->gitlabClient->mergeRequests()->show($mrInfo->projectPath, $mrInfo->mergeRequestIid);
        if (!$mergeRequest) {
            throw new RuntimeException(
                "Merge request #{$mrInfo->mergeRequestIid} not found in {$mrInfo->projectPath}",
            );
        }

        $sourceBranch = $mergeRequest['source_branch'];
        $sourceProjectId = $mergeRequest['source_project_id'];
        $project = $this->gitlabClient->projects()->show($sourceProjectId);

        $authorizedRepoUrl = $this->buildAuthorizedRepoUrl($project['http_url_to_repo']);
        $remoteName = 'mr_' . $mrInfo->mergeRequestIid;
        $packagePath = $this->getInstalledPackagePath($patch);

        return new GitRemoteContext(
            $packagePath,
            $remoteName,
            $authorizedRepoUrl,
            $sourceBranch
        );
    }

    private function buildAuthorizedRepoUrl(string $httpUrl): string
    {
        return str_replace('https://', "https://git:{$this->token}@", $httpUrl);
    }

    private function getInstalledPackagePath(Patch $patch): string
    {
        $package = $this->composer->getRepositoryManager()
            ->getLocalRepository()
            ->findPackage($patch->package, '*');

        if (!$package) {
            throw new RuntimeException("Package {$patch->package} not found.");
        }

        return $this->composer->getInstallationManager()->getInstallPath($package);
    }
}
