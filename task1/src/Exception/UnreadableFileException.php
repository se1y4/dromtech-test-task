<?php

declare(strict_types=1);

namespace Se1y4\CountSum\Exception;

use RuntimeException;

final class UnreadableFileException extends RuntimeException implements CountSummatorExceptionInterface
{
    public static function forPath(string $path): self
    {
        return new self(sprintf('File "%s" is not readable.', $path));
    }
}
