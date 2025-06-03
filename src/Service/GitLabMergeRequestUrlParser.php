<?php declare(strict_types=1);

namespace hiqdev\ComposerCiDeps\Service;

use RuntimeException;

final class GitLabMergeRequestUrlParser
{
    public function parse(string $url): GitLabMergeRequestInfo
    {
        if (!preg_match('#^https?://([^/]+)/([^/]+/[^/]+)/-/merge_requests/(\d+)#', $url, $matches)) {
            throw new RuntimeException("Invalid GitLab merge request URL: {$url}");
        }

        return new GitLabMergeRequestInfo(
            $matches[1],
            $matches[2],
            (int)$matches[3],
        );
    }
}
