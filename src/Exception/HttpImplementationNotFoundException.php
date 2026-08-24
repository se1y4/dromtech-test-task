<?php

declare(strict_types=1);

namespace Se1y4\CommentClient\Exception;

use RuntimeException;
use Throwable;

final class HttpImplementationNotFoundException extends RuntimeException implements CommentClientExceptionInterface
{
    public static function forDiscovery(Throwable $previous): self
    {
        return new self(
            'No PSR-18 client or PSR-17 factory was found. Install one or pass it to the constructor.',
            0,
            $previous,
        );
    }
}
