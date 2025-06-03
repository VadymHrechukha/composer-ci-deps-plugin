<?php declare(strict_types=1);

namespace hiqdev\ComposerCiDeps\tests\unit\Service;

use hiqdev\ComposerCiDeps\Service\GitLabMergeRequestUrlParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GitLabMergeRequestUrlParserTest extends TestCase
{
    public function testParsesValidGitLabUrl(): void
    {
        $parser = new GitLabMergeRequestUrlParser();
        $info = $parser->parse('https://git.hiqdev.com/hiqdev/hiapi-legacy/-/merge_requests/1597');

        $this->assertEquals('git.hiqdev.com', $info->host);
        $this->assertEquals('hiqdev/hiapi-legacy', $info->projectPath);
        $this->assertEquals(1597, $info->mergeRequestIid);
    }

    public function testThrowsExceptionForInvalidUrl(): void
    {
        $parser = new GitLabMergeRequestUrlParser();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid GitLab merge request URL');

        $parser->parse('https://gitlab.example.com/vendor/package/merge_requests/42'); // invalid
    }
}
