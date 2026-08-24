# dromtech-test-task

![CI](https://github.com/se1y4/dromtech-test-task/actions/workflows/ci.yml/badge.svg)

Два задания в одном репозитории. Корень это пакет `se1y4/comment-client` (задание 2),
задание 1 лежит в `task1/`.

## Запуск

```bash
make build
make install
make test
```

Ещё есть `make test-task1`, `make test-task2`, `make qa` (PHPStan max + PSR-12), `make cs-fix`,
`make shell`, `make clean`. Без аргументов `make` печатает список.

Собирается на PHP 8.1, это минимум из `composer.json`. Другая версия:
`PHP_VERSION=8.4 make build install test`. CI гоняет 8.1, 8.2, 8.3, 8.4 и 8.5.

Если PHP есть локально, докер не нужен: `composer install && composer test` в корне и в `task1/`.

## Задание 1

```php
use Se1y4\CountSum\CountSummator;

echo (new CountSummator())->sum('/path/to/tree');
```

Обход ленивый, `RecursiveDirectoryIterator` поверх `RecursiveIteratorIterator`, глубина
дерева упирается в файловую систему, а не в память.

Краевые случаи, которых нет в задании, решил так:

- число это целое в десятичной записи, знак можно: `42`, `-5`, `+7`;
- `3.14`, `1e5`, `0x1F`, `12x` и всё, что не влезает в `PHP_INT_MAX`, дают
  `InvalidCountFileException` с путём файла и самим токеном;
- пустой файл и файл из одних пробелов просто пропускаются, это не ошибка;
- симлинки игнорируются целиком, и на папки, и на файлы: иначе число попадёт в сумму
  дважды, а ссылка на родителя зациклит обход;
- директория с именем `count` не ломает обход, внутрь заходим, читать её как файл не пытаемся;
- имя сверяется точно, `Count` и `count.txt` мимо;
- нечитаемая директория обрывает подсчёт, а не пропускается молча: заниженную сумму не
  отличить от правильной.

Все исключения реализуют `CountSummatorExceptionInterface`.

## Задание 2

```bash
composer config repositories.dromtech vcs https://github.com/se1y4/dromtech-test-task.git
composer config allow-plugins.php-http/discovery true
composer require se1y4/comment-client:dev-main guzzlehttp/guzzle
```

`allow-plugins` обязателен, `php-http/discovery` это composer-плагин. Guzzle тут для примера,
подойдёт любая реализация PSR-18.

```php
use Se1y4\CommentClient\CommentClient;
use Se1y4\CommentClient\Exception\CommentClientExceptionInterface;

$client = new CommentClient('https://example.com');

try {
    foreach ($client->getComments() as $comment) {
        printf("%d %s: %s\n", $comment->id, $comment->name, $comment->text);
    }

    $created = $client->addComment('Иван', 'Первый комментарий');
    $client->updateComment($created->id, text: 'Исправленный текст');
} catch (CommentClientExceptionInterface $e) {
    report($e);
}
```

По `CommentClientExceptionInterface` ловятся все исключения библиотеки. Транспорт можно
передать явно, так делают тесты:
`new CommentClient($baseUri, $psr18Client, $psr17Factory, $psr17Factory)`.

Что решил по ходу:

- PSR-18 и discovery вместо зависимости от Guzzle. Библиотека не должна навязывать проекту
  свой HTTP-клиент и конфликтовать с ним по версиям;
- PUT отправляет только переданные поля: `null` значит «не трогаем», `''` значит «очищаем»;
- `updateComment()` без единого поля кидает `EmptyUpdateException` до запроса. Пустой PUT
  вернёт 200 и прежний объект, ошибку так не увидишь;
- успех это любой 2xx с JSON-телом. Дальше `UnexpectedStatusException` со статусом,
  `InvalidResponseException` на битое тело, `TransportException` поверх
  `ClientExceptionInterface`;
- разбор JSON в `Comment::fromArray()`, клиент занимается только транспортом.

Тесты на `php-http/mock-client`, сервер не поднимается. Для каждого метода проверяется и то,
что ушло в запрос, и то, как разобран ответ.
