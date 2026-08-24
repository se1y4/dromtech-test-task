<?php

declare(strict_types=1);

namespace Se1y4\CommentClient\Exception;

use RuntimeException;

final class UnexpectedStatusException extends RuntimeException implements CommentClientExceptionInterface
{
    private function __construct(string $message, private readonly int $statusCode)
    {
        parent::__construct($message);
    }

    public static function forStatus(int $statusCode, string $method, string $uri): self
    {
        return new self(
            sprintf('%s %s responded with unexpected status %d.', $method, $uri, $statusCode),
            $statusCode,
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
