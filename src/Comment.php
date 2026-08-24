<?php

declare(strict_types=1);

namespace Se1y4\CommentClient;

use Se1y4\CommentClient\Exception\InvalidResponseException;

final class Comment
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $text,
    ) {
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $id = $payload['id'] ?? null;
        $name = $payload['name'] ?? null;
        $text = $payload['text'] ?? null;

        if (!is_int($id)) {
            throw InvalidResponseException::malformedComment('id');
        }

        if (!is_string($name)) {
            throw InvalidResponseException::malformedComment('name');
        }

        if (!is_string($text)) {
            throw InvalidResponseException::malformedComment('text');
        }

        return new self($id, $name, $text);
    }

    /**
     * @return array{id?: int, name: string, text: string}
     */
    public function toArray(): array
    {
        $payload = ['name' => $this->name, 'text' => $this->text];

        if ($this->id === null) {
            return $payload;
        }

        return ['id' => $this->id] + $payload;
    }
}
