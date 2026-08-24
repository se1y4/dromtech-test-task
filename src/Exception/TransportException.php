<?php

declare(strict_types=1);

namespace Se1y4\CommentClient\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

final class TransportException extends RuntimeException implements CommentClientExceptionInterface
{
    public static function fromClientException(ClientExceptionInterface $previous): self
    {
        return new self(sprintf('HTTP request failed: %s', $previous->getMessage()), 0, $previous);
    }
}
