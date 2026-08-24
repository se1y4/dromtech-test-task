<?php

declare(strict_types=1);

namespace Se1y4\CommentClient\Exception;

use InvalidArgumentException;

final class EmptyUpdateException extends InvalidArgumentException implements CommentClientExceptionInterface
{
    public static function forComment(int $id): self
    {
        return new self(sprintf('Update of comment %d has no fields to send.', $id));
    }
}
