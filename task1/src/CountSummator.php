<?php

declare(strict_types=1);

namespace Se1y4\CountSum;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Se1y4\CountSum\Exception\InvalidCountFileException;
use Se1y4\CountSum\Exception\InvalidPathException;
use Se1y4\CountSum\Exception\UnreadableDirectoryException;
use Se1y4\CountSum\Exception\UnreadableFileException;
use SplFileInfo;

final class CountSummator
{
    private const COUNT_FILE_NAME = 'count';

    private const INTEGER_PATTERN = '/^[+-]?\d+$/';

    public function sum(string $path): int
    {
        $this->assertUsableDirectory($path);

        $total = 0;

        foreach ($this->walk($path) as $entry) {
            $pathname = $entry->getPathname();

            if ($entry->isDir()) {
                if (!is_readable($pathname)) {
                    throw UnreadableDirectoryException::forPath($pathname);
                }

                continue;
            }

            if ($entry->getFilename() !== self::COUNT_FILE_NAME) {
                continue;
            }

            $total += $this->sumFile($pathname);
        }

        return $total;
    }

    private function assertUsableDirectory(string $path): void
    {
        if (!file_exists($path)) {
            throw InvalidPathException::notFound($path);
        }

        if (!is_dir($path)) {
            throw InvalidPathException::notADirectory($path);
        }

        if (!is_readable($path)) {
            throw UnreadableDirectoryException::forPath($path);
        }
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function walk(string $path): iterable
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
    }

    private function sumFile(string $path): int
    {
        if (!is_readable($path)) {
            throw UnreadableFileException::forPath($path);
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw UnreadableFileException::forPath($path);
        }

        $sum = 0;

        foreach ($this->tokenize($content) as $token) {
            $sum += $this->parseInteger($path, $token);
        }

        return $sum;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $content): array
    {
        $tokens = preg_split('/\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);

        return $tokens === false ? [] : $tokens;
    }

    private function parseInteger(string $path, string $token): int
    {
        if (preg_match(self::INTEGER_PATTERN, $token) !== 1) {
            throw InvalidCountFileException::nonIntegerToken($path, $token);
        }

        $value = filter_var($token, FILTER_VALIDATE_INT);

        if (!is_int($value)) {
            throw InvalidCountFileException::nonIntegerToken($path, $token);
        }

        return $value;
    }
}
