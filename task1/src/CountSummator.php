<?php

declare(strict_types=1);

namespace Se1y4\CountSum;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Se1y4\CountSum\Exception\InvalidCountFileException;
use Se1y4\CountSum\Exception\InvalidPathException;
use Se1y4\CountSum\Exception\SumOverflowException;
use Se1y4\CountSum\Exception\UnreadableDirectoryException;
use Se1y4\CountSum\Exception\UnreadableFileException;
use SplFileInfo;
use SplFileObject;
use Throwable;

final class CountSummator
{
    private const COUNT_FILE_NAME = 'count';

    private const INTEGER_PATTERN = '/^([+-]?)(\d+)$/';

    private const BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    public function sum(string $path): int
    {
        $this->assertUsableDirectory($path);

        $total = 0;

        foreach ($this->walk($path) as $entry) {
            if ($entry->isLink()) {
                continue;
            }

            $pathname = $entry->getPathname();

            if ($entry->isDir()) {
                if (!is_readable($pathname)) {
                    throw UnreadableDirectoryException::forPath($pathname);
                }

                continue;
            }

            if (!$entry->isFile() || $entry->getFilename() !== self::COUNT_FILE_NAME) {
                continue;
            }

            $total = $this->add($pathname, $total, $this->sumFile($pathname));
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

        try {
            $file = new SplFileObject($path, 'rb');
        } catch (Throwable $e) {
            throw UnreadableFileException::forPath($path);
        }

        $sum = 0;
        $isFirstLine = true;

        foreach ($file as $line) {
            $line = is_string($line) ? $line : '';

            if ($isFirstLine) {
                $line = $this->stripByteOrderMark($line);
                $isFirstLine = false;
            }

            foreach ($this->tokenize($line) as $token) {
                $sum = $this->add($path, $sum, $this->parseInteger($path, $token));
            }
        }

        return $sum;
    }

    private function stripByteOrderMark(string $line): string
    {
        if (!str_starts_with($line, self::BYTE_ORDER_MARK)) {
            return $line;
        }

        return substr($line, strlen(self::BYTE_ORDER_MARK));
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $line): array
    {
        $tokens = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);

        return $tokens === false ? [] : $tokens;
    }

    private function parseInteger(string $path, string $token): int
    {
        if (preg_match(self::INTEGER_PATTERN, $token, $matches) !== 1) {
            throw InvalidCountFileException::nonIntegerToken($path, $token);
        }

        $digits = ltrim($matches[2], '0');
        $value = filter_var($matches[1] . ($digits === '' ? '0' : $digits), FILTER_VALIDATE_INT);

        if (!is_int($value)) {
            throw InvalidCountFileException::nonIntegerToken($path, $token);
        }

        return $value;
    }

    private function add(string $path, int $sum, int $value): int
    {
        $overflows = $value > 0 && $sum > PHP_INT_MAX - $value;
        $underflows = $value < 0 && $sum < PHP_INT_MIN - $value;

        if ($overflows || $underflows) {
            throw SumOverflowException::atFile($path);
        }

        return $sum + $value;
    }
}
