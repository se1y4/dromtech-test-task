<?php

declare(strict_types=1);

namespace Se1y4\CountSum\Exception;

use RuntimeException;

final class InvalidCountFileException extends RuntimeException implements CountSummatorExceptionInterface
{
    public static function nonIntegerToken(string $path, string $token): self
    {
        return new self(sprintf('File "%s" contains a token that is not an integer: "%s".', $path, $token));
    }
}
