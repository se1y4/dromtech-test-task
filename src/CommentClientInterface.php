<?php

declare(strict_types=1);

namespace Se1y4\CommentClient;

interface CommentClientInterface
{
    /**
     * @return list<Comment>
     */
    public function getComments(): array;

    public function addComment(string $name, string $text): Comment;

    public function updateComment(int $id, ?string $name = null, ?string $text = null): Comment;
}
