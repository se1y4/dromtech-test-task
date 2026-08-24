<?php

declare(strict_types=1);

namespace Se1y4\CommentClient\Exception;

use InvalidArgumentException;

final class InvalidBaseUriException extends InvalidArgumentException implements CommentClientExceptionInterface
{
    public static function forUri(string $baseUri): self
    {
        return new self(sprintf('Base URI "%s" must be an absolute http or https URL.', $baseUri));
    }
}
