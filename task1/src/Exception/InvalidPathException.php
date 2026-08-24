<?php

declare(strict_types=1);

namespace Se1y4\CountSum\Exception;

use InvalidArgumentException;

final class InvalidPathException extends InvalidArgumentException implements CountSummatorExceptionInterface
{
    public static function notFound(string $path): self
    {
        return new self(sprintf('Path "%s" does not exist.', $path));
    }

    public static function notADirectory(string $path): self
    {
        return new self(sprintf('Path "%s" is not a directory.', $path));
    }
}
