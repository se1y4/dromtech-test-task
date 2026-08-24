<?php

declare(strict_types=1);

namespace Se1y4\CountSum\Exception;

use RuntimeException;

final class SumOverflowException extends RuntimeException implements CountSummatorExceptionInterface
{
    public static function atFile(string $path): self
    {
        return new self(sprintf('Sum does not fit into an integer while reading "%s".', $path));
    }
}
