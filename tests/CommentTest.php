<?php

declare(strict_types=1);

namespace Se1y4\CommentClient\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Se1y4\CommentClient\Comment;
use Se1y4\CommentClient\Exception\InvalidResponseException;

final class CommentTest extends TestCase
{
    public function testBuildsCommentFromServerPayload(): void
    {
        $comment = Comment::fromArray(['id' => 7, 'name' => 'Иван', 'text' => 'Первый']);

        self::assertSame(7, $comment->id);
        self::assertSame('Иван', $comment->name);
        self::assertSame('Первый', $comment->text);
    }

    public function testExposesCommentAsArray(): void
    {
        $comment = new Comment(7, 'Иван', 'Первый');

        self::assertSame(['id' => 7, 'name' => 'Иван', 'text' => 'Первый'], $comment->toArray());
    }

    public function testOmitsIdOfCommentThatWasNotStoredYet(): void
    {
        $comment = new Comment(null, 'Иван', 'Первый');

        self::assertSame(['name' => 'Иван', 'text' => 'Первый'], $comment->toArray());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('malformedPayloads')]
    public function testRejectsMalformedPayload(array $payload): void
    {
        $this->expectException(InvalidResponseException::class);

        Comment::fromArray($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedPayloads(): iterable
    {
        yield 'missing id' => [['name' => 'Иван', 'text' => 'Первый']];
        yield 'null id' => [['id' => null, 'name' => 'Иван', 'text' => 'Первый']];
        yield 'id as string' => [['id' => '7', 'name' => 'Иван', 'text' => 'Первый']];
        yield 'missing name' => [['id' => 7, 'text' => 'Первый']];
        yield 'name as number' => [['id' => 7, 'name' => 42, 'text' => 'Первый']];
        yield 'missing text' => [['id' => 7, 'name' => 'Иван']];
        yield 'text as array' => [['id' => 7, 'name' => 'Иван', 'text' => []]];
    }
}
