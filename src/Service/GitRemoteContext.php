<?php declare(strict_types=1);

namespace hiqdev\ComposerCiDeps\Service;

final class GitRemoteContext
{
    public function __construct(
        public readonly string $packagePath,
        public readonly string $remoteName,
        public readonly string $authorizedRepoUrl,
        public readonly string $sourceBranch
    ) {}
}
