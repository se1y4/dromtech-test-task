<?php

declare(strict_types=1);

namespace Se1y4\CommentClient\Exception;

use RuntimeException;

final class InvalidResponseException extends RuntimeException implements CommentClientExceptionInterface
{
    public static function notJson(string $reason): self
    {
        return new self(sprintf('Response body is not valid JSON: %s.', $reason));
    }

    public static function unexpectedShape(string $expected): self
    {
        return new self(sprintf('Response body does not match the expected shape: %s.', $expected));
    }

    public static function malformedComment(string $field): self
    {
        return new self(sprintf('Comment payload has a missing or malformed field "%s".', $field));
    }
}
