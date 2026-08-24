<?php

declare(strict_types=1);

namespace Se1y4\CountSum\Tests;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Se1y4\CountSum\CountSummator;
use Se1y4\CountSum\Exception\CountSummatorExceptionInterface;
use Se1y4\CountSum\Exception\InvalidCountFileException;
use Se1y4\CountSum\Exception\InvalidPathException;
use Se1y4\CountSum\Exception\UnreadableDirectoryException;
use Se1y4\CountSum\Exception\UnreadableFileException;

final class CountSummatorTest extends TestCase
{
    private CountSummator $summator;

    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function setUp(): void
    {
        $this->summator = new CountSummator();
    }

    public function testSumsCountFilesFromDifferentBranches(): void
    {
        $root = vfsStream::setup('root', null, [
            'a' => ['count' => '10'],
            'b' => ['count' => '32'],
            'c' => ['readme.txt' => 'ignored'],
        ]);

        self::assertSame(42, $this->summator->sum($root->url()));
    }

    public function testSumsNumbersFromDeeplyNestedDirectories(): void
    {
        $root = vfsStream::setup('root', null, [
            'count' => '1',
            'a' => [
                'count' => '2',
                'b' => [
                    'c' => [
                        'count' => '4',
                        'd' => ['count' => '8'],
                    ],
                ],
            ],
        ]);

        self::assertSame(15, $this->summator->sum($root->url()));
    }

    public function testSumsSeveralNumbersSeparatedByAnyWhitespace(): void
    {
        $root = vfsStream::setup('root', null, [
            'count' => "1 2\t3\n4\r\n5",
        ]);

        self::assertSame(15, $this->summator->sum($root->url()));
    }

    public function testSumsNegativeAndExplicitlyPositiveNumbers(): void
    {
        $root = vfsStream::setup('root', null, [
            'count' => '-5 +7 3',
        ]);

        self::assertSame(5, $this->summator->sum($root->url()));
    }

    public function testSkipsEmptyCountFile(): void
    {
        $root = vfsStream::setup('root', null, [
            'a' => ['count' => ''],
            'b' => ['count' => '7'],
        ]);

        self::assertSame(7, $this->summator->sum($root->url()));
    }

    public function testSkipsCountFileMadeOfWhitespaceOnly(): void
    {
        $root = vfsStream::setup('root', null, [
            'a' => ['count' => "  \t\n  "],
            'b' => ['count' => '7'],
        ]);

        self::assertSame(7, $this->summator->sum($root->url()));
    }

    public function testReturnsZeroWhenTreeHasNoCountFiles(): void
    {
        $root = vfsStream::setup('root', null, [
            'a' => ['notes.md' => 'nothing here'],
            'b' => [],
        ]);

        self::assertSame(0, $this->summator->sum($root->url()));
    }

    #[DataProvider('nonIntegerTokens')]
    public function testRejectsTokenThatIsNotAnInteger(string $token): void
    {
        $root = vfsStream::setup('root', null, [
            'a' => ['count' => '10 ' . $token],
        ]);

        try {
            $this->summator->sum($root->url());
            self::fail('Expected ' . InvalidCountFileException::class);
        } catch (InvalidCountFileException $e) {
            self::assertInstanceOf(CountSummatorExceptionInterface::class, $e);
            self::assertStringContainsString('vfs://root/a/count', $e->getMessage());
            self::assertStringContainsString($token, $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonIntegerTokens(): iterable
    {
        yield 'word' => ['abc'];
        yield 'float' => ['3.14'];
        yield 'scientific notation' => ['1e5'];
        yield 'hexadecimal' => ['0x1F'];
        yield 'digits with suffix' => ['12x'];
        yield 'lonely sign' => ['-'];
    }

    public function testRejectsNumberOutsideIntegerRange(): void
    {
        $root = vfsStream::setup('root', null, [
            'count' => '99999999999999999999999',
        ]);

        $this->expectException(InvalidCountFileException::class);

        $this->summator->sum($root->url());
    }

    public function testRejectsMissingPath(): void
    {
        $this->expectException(InvalidPathException::class);

        $this->summator->sum('vfs://root/nowhere');
    }

    public function testRejectsPathThatIsAFile(): void
    {
        $root = vfsStream::setup('root', null, ['count' => '10']);

        $this->expectException(InvalidPathException::class);

        $this->summator->sum($root->url() . '/count');
    }

    public function testFailsOnUnreadableNestedDirectory(): void
    {
        $this->skipWhenRunningAsRoot();

        $root = vfsStream::setup('root', null, [
            'a' => ['count' => '10'],
            'b' => ['count' => '5'],
        ]);
        $root->getChild('b')->chmod(0000);
        $root->getChild('b')->chown(vfsStream::OWNER_ROOT);

        try {
            $this->summator->sum($root->url());
            self::fail('Expected ' . UnreadableDirectoryException::class);
        } catch (UnreadableDirectoryException $e) {
            self::assertInstanceOf(CountSummatorExceptionInterface::class, $e);
            self::assertStringContainsString('vfs://root/b', $e->getMessage());
        }
    }

    public function testFailsOnUnreadableCountFile(): void
    {
        $this->skipWhenRunningAsRoot();

        $root = vfsStream::setup('root', null, [
            'a' => ['count' => '10'],
        ]);
        $root->getChild('a/count')->chmod(0000);
        $root->getChild('a/count')->chown(vfsStream::OWNER_ROOT);

        $this->expectException(UnreadableFileException::class);

        $this->summator->sum($root->url());
    }

    private function skipWhenRunningAsRoot(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Права доступа не проверяются под root.');
        }
    }

    public function testTreatsDirectoryNamedCountAsAnOrdinaryDirectory(): void
    {
        $root = vfsStream::setup('root', null, [
            'count' => [
                'count' => '7',
                'nested' => ['count' => '3'],
            ],
        ]);

        self::assertSame(10, $this->summator->sum($root->url()));
    }

    #[DataProvider('nonMatchingFileNames')]
    public function testIgnoresFilesNotNamedExactlyCount(string $filename): void
    {
        $root = vfsStream::setup('root', null, [
            'a' => [$filename => '10'],
            'b' => ['count' => '7'],
        ]);

        self::assertSame(7, $this->summator->sum($root->url()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonMatchingFileNames(): iterable
    {
        yield 'capitalised' => ['Count'];
        yield 'upper case' => ['COUNT'];
        yield 'with extension' => ['count.txt'];
        yield 'with prefix' => ['mycount'];
    }

    public function testDoesNotFollowDirectorySymlinks(): void
    {
        $root = $this->createTempDirectory();
        mkdir($root . '/real');
        file_put_contents($root . '/real/count', '10');
        symlink($root . '/real', $root . '/link');

        self::assertSame(10, $this->summator->sum($root));
    }

    public function testSurvivesSymlinkLoop(): void
    {
        $root = $this->createTempDirectory();
        mkdir($root . '/inner');
        file_put_contents($root . '/inner/count', '4');
        symlink($root, $root . '/inner/loop');

        self::assertSame(4, $this->summator->sum($root));
    }

    public function testDoesNotCountSymlinkedCountFileTwice(): void
    {
        $root = $this->createTempDirectory();
        mkdir($root . '/real');
        file_put_contents($root . '/real/count', '10');
        symlink($root . '/real/count', $root . '/count');

        self::assertSame(10, $this->summator->sum($root));
    }

    private function createTempDirectory(): string
    {
        $path = sys_get_temp_dir() . '/count-summator-' . bin2hex(random_bytes(6));
        mkdir($path);
        $this->temporaryDirectories[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $this->removeDirectory($directory);
        }

        $this->temporaryDirectories = [];
    }

    private function removeDirectory(string $path): void
    {
        foreach ((array) scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path . '/' . $entry;

            if (is_link($child) || is_file($child)) {
                unlink($child);

                continue;
            }

            $this->removeDirectory((string) $child);
        }

        rmdir($path);
    }
}
