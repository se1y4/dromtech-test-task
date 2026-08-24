<?php

declare(strict_types=1);

namespace Se1y4\CommentClient\Tests;

use Http\Discovery\Psr18ClientDiscovery;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Se1y4\CommentClient\CommentClient;
use Se1y4\CommentClient\CommentClientInterface;
use Se1y4\CommentClient\Exception\CommentClientExceptionInterface;
use Se1y4\CommentClient\Exception\EmptyUpdateException;
use Se1y4\CommentClient\Exception\HttpImplementationNotFoundException;
use Se1y4\CommentClient\Exception\InvalidBaseUriException;
use Se1y4\CommentClient\Exception\InvalidResponseException;
use Se1y4\CommentClient\Exception\TransportException;
use Se1y4\CommentClient\Exception\UnexpectedStatusException;

final class CommentClientTest extends TestCase
{
    private const BASE_URI = 'https://example.com';

    private MockClient $transport;

    private Psr17Factory $psr17;

    private CommentClient $client;

    protected function setUp(): void
    {
        $this->transport = new MockClient();
        $this->psr17 = new Psr17Factory();
        $this->client = new CommentClient(self::BASE_URI, $this->transport, $this->psr17, $this->psr17);
    }

    public function testImplementsPublicContract(): void
    {
        self::assertInstanceOf(CommentClientInterface::class, $this->client);
    }

    public function testGetCommentsRequestsTheListEndpoint(): void
    {
        $this->queueJson(200, []);

        $this->client->getComments();

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://example.com/comments', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertFalse($request->hasHeader('Content-Type'));
        self::assertSame('', (string) $request->getBody());
    }

    public function testGetCommentsDeserialisesEveryItem(): void
    {
        $this->queueJson(200, [
            ['id' => 1, 'name' => 'Иван', 'text' => 'Первый'],
            ['id' => 2, 'name' => 'Пётр', 'text' => 'Второй'],
        ]);

        $comments = $this->client->getComments();

        self::assertCount(2, $comments);
        self::assertSame(2, $comments[1]->id);
        self::assertSame('Пётр', $comments[1]->name);
    }

    public function testGetCommentsReturnsEmptyArrayForEmptyList(): void
    {
        $this->queueJson(200, []);

        self::assertSame([], $this->client->getComments());
    }

    public function testGetCommentsRejectsResponseThatIsNotAList(): void
    {
        $this->queueJson(200, ['id' => 1, 'name' => 'Иван', 'text' => 'Первый']);

        $this->expectException(InvalidResponseException::class);

        $this->client->getComments();
    }

    public function testAddCommentPostsNameAndTextWithoutId(): void
    {
        $this->queueJson(201, ['id' => 15, 'name' => 'Иван', 'text' => 'Первый']);

        $created = $this->client->addComment('Иван', 'Первый');

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://example.com/comment', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame(['name' => 'Иван', 'text' => 'Первый'], $this->decodeRequestBody($request));
        self::assertSame(15, $created->id);
    }

    public function testUpdateCommentSendsOnlyTheFieldThatWasGiven(): void
    {
        $this->queueJson(200, ['id' => 42, 'name' => 'Новое имя', 'text' => 'Старый текст']);

        $updated = $this->client->updateComment(42, name: 'Новое имя');

        $request = $this->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('https://example.com/comment/42', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame(['name' => 'Новое имя'], $this->decodeRequestBody($request));
        self::assertSame('Новое имя', $updated->name);
    }

    public function testUpdateCommentSendsBothFieldsWhenBothAreGiven(): void
    {
        $this->queueJson(200, ['id' => 42, 'name' => 'Имя', 'text' => 'Текст']);

        $this->client->updateComment(42, 'Имя', 'Текст');

        self::assertSame(['name' => 'Имя', 'text' => 'Текст'], $this->decodeRequestBody($this->lastRequest()));
    }

    public function testUpdateCommentTreatsEmptyStringAsAValue(): void
    {
        $this->queueJson(200, ['id' => 42, 'name' => 'Иван', 'text' => '']);

        $this->client->updateComment(42, text: '');

        self::assertSame(['text' => ''], $this->decodeRequestBody($this->lastRequest()));
    }

    public function testUpdateCommentWithoutFieldsFailsBeforeSendingAnything(): void
    {
        try {
            $this->client->updateComment(42);
            self::fail('Expected ' . EmptyUpdateException::class);
        } catch (EmptyUpdateException $e) {
            self::assertInstanceOf(CommentClientExceptionInterface::class, $e);
        }

        self::assertSame([], $this->transport->getRequests());
    }

    public function testUnexpectedStatusIsReportedWithItsCode(): void
    {
        $this->transport->addResponse($this->psr17->createResponse(500));

        try {
            $this->client->getComments();
            self::fail('Expected ' . UnexpectedStatusException::class);
        } catch (UnexpectedStatusException $e) {
            self::assertSame(500, $e->getStatusCode());
            self::assertInstanceOf(CommentClientExceptionInterface::class, $e);
        }
    }

    public function testBrokenJsonIsReportedAsInvalidResponse(): void
    {
        $this->transport->addResponse(
            $this->psr17->createResponse(200)->withBody($this->psr17->createStream('{not json'))
        );

        $this->expectException(InvalidResponseException::class);

        $this->client->getComments();
    }

    public function testTransportFailureIsWrapped(): void
    {
        $failure = new class ('network is down') extends RuntimeException implements ClientExceptionInterface {
        };
        $this->transport->addException($failure);

        try {
            $this->client->getComments();
            self::fail('Expected ' . TransportException::class);
        } catch (TransportException $e) {
            self::assertSame($failure, $e->getPrevious());
            self::assertInstanceOf(CommentClientExceptionInterface::class, $e);
        }
    }

    public function testTrailingSlashInBaseUriDoesNotProduceDoubleSlash(): void
    {
        $client = new CommentClient('https://example.com/api/', $this->transport, $this->psr17, $this->psr17);
        $this->queueJson(200, []);

        $client->getComments();

        self::assertSame('https://example.com/api/comments', (string) $this->lastRequest()->getUri());
    }

    public function testCanBeBuiltWithoutExplicitHttpImplementation(): void
    {
        self::assertInstanceOf(CommentClientInterface::class, new CommentClient(self::BASE_URI));
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function queueJson(int $status, array $payload): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->transport->addResponse(
            $this->psr17->createResponse($status)->withBody($this->psr17->createStream($body))
        );
    }

    private function lastRequest(): RequestInterface
    {
        $request = $this->transport->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodeRequestBody(RequestInterface $request): array
    {
        return (array) json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testGetCommentsRejectsListItemThatIsNotAnObject(): void
    {
        $this->queueJson(200, [['id' => 1, 'name' => 'Иван', 'text' => 'Первый'], 'oops']);

        $this->expectException(InvalidResponseException::class);

        $this->client->getComments();
    }

    public function testSuccessfulResponseWithoutBodyIsRejected(): void
    {
        $this->transport->addResponse($this->psr17->createResponse(204));

        $this->expectException(InvalidResponseException::class);

        $this->client->updateComment(42, name: 'Имя');
    }

    public function testFailedDiscoveryIsReportedAsLibraryException(): void
    {
        $strategies = [...Psr18ClientDiscovery::getStrategies()];
        Psr18ClientDiscovery::setStrategies([]);
        Psr18ClientDiscovery::clearCache();

        try {
            new CommentClient(self::BASE_URI);
            self::fail('Expected ' . HttpImplementationNotFoundException::class);
        } catch (HttpImplementationNotFoundException $e) {
            self::assertInstanceOf(CommentClientExceptionInterface::class, $e);
        } finally {
            Psr18ClientDiscovery::setStrategies($strategies);
            Psr18ClientDiscovery::clearCache();
        }
    }

    #[DataProvider('unusableBaseUris')]
    public function testRejectsUnusableBaseUri(string $baseUri): void
    {
        $this->expectException(InvalidBaseUriException::class);

        new CommentClient($baseUri, $this->transport, $this->psr17, $this->psr17);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableBaseUris(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'slashes only' => ['///'];
        yield 'without scheme' => ['example.com'];
        yield 'unsupported scheme' => ['ftp://example.com'];
    }
}
