<?php declare(strict_types=1);

namespace hiqdev\ComposerCiDeps\Service;

class GitLabMergeRequestInfo
{
    public function __construct(
        public readonly string $host,
        public readonly string $projectPath,
        public readonly int $mergeRequestIid,
    ) {}
}
