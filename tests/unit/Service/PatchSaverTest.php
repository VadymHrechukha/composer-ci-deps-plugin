<?php declare(strict_types=1);

namespace hiqdev\ComposerCiDeps\tests\unit\Service;

use cweagans\Composer\Patch;
use hiqdev\ComposerCiDeps\Service\PatchSaver;
use PHPUnit\Framework\TestCase;

class PatchSaverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test-patch-saver/';
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob("{$this->tempDir}/*.patch"));
            @rmdir($this->tempDir);
        }
    }

    public function testSavesPatchToFile(): void
    {
        $saver = new PatchSaver($this->tempDir);
        $patch = new Patch();

        $diff = "--- a/file\n+++ b/file\n@@ -1 +1 @@\n-foo\n+bar\n";
        $saver->save($patch, $diff);

        $this->assertFileExists($patch->localPath);
        $this->assertEquals(hash_file('sha256', $patch->localPath), $patch->sha256);
        $this->assertStringEqualsFile($patch->localPath, $diff);
    }
}
