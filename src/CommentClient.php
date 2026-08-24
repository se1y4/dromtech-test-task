<?php

declare(strict_types=1);

namespace Se1y4\CommentClient;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Se1y4\CommentClient\Exception\EmptyUpdateException;
use Se1y4\CommentClient\Exception\InvalidBaseUriException;
use Se1y4\CommentClient\Exception\InvalidResponseException;
use Se1y4\CommentClient\Exception\TransportException;
use Se1y4\CommentClient\Exception\UnexpectedStatusException;

final class CommentClient implements CommentClientInterface
{
    private const JSON_MEDIA_TYPE = 'application/json';

    private readonly string $baseUri;

    private readonly ClientInterface $httpClient;

    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    public function __construct(
        string $baseUri,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->baseUri = self::normaliseBaseUri($baseUri);
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    private static function normaliseBaseUri(string $baseUri): string
    {
        $normalised = rtrim(trim($baseUri), '/');

        if (preg_match('~^https?://[^/\s]+~i', $normalised) !== 1) {
            throw InvalidBaseUriException::forUri($baseUri);
        }

        return $normalised;
    }

    public function getComments(): array
    {
        $payload = $this->send($this->createRequest('GET', '/comments'));

        if (!array_is_list($payload)) {
            throw InvalidResponseException::unexpectedShape('a list of comments was expected');
        }

        return array_map(
            static fn (mixed $item): Comment => Comment::fromArray(is_array($item) ? $item : []),
            $payload,
        );
    }

    public function addComment(string $name, string $text): Comment
    {
        $request = $this->createRequest('POST', '/comment', ['name' => $name, 'text' => $text]);

        return Comment::fromArray($this->send($request));
    }

    public function updateComment(int $id, ?string $name = null, ?string $text = null): Comment
    {
        $payload = array_filter(
            ['name' => $name, 'text' => $text],
            static fn (?string $value): bool => $value !== null,
        );

        if ($payload === []) {
            throw EmptyUpdateException::forComment($id);
        }

        $request = $this->createRequest('PUT', '/comment/' . $id, $payload);

        return Comment::fromArray($this->send($request));
    }

    /**
     * @param array<string, string>|null $payload
     */
    private function createRequest(string $method, string $path, ?array $payload = null): RequestInterface
    {
        $request = $this->requestFactory
            ->createRequest($method, $this->baseUri . $path)
            ->withHeader('Accept', self::JSON_MEDIA_TYPE);

        if ($payload === null) {
            return $request;
        }

        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return $request
            ->withHeader('Content-Type', self::JSON_MEDIA_TYPE)
            ->withBody($this->streamFactory->createStream($body));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function send(RequestInterface $request): array
    {
        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw TransportException::fromClientException($e);
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw UnexpectedStatusException::forStatus($status, $request->getMethod(), (string) $request->getUri());
        }

        try {
            $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidResponseException::notJson($e->getMessage());
        }

        if (!is_array($decoded)) {
            throw InvalidResponseException::unexpectedShape('a JSON object or array was expected');
        }

        return $decoded;
    }
}
