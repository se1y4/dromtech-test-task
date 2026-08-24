DOCKER_UID := $(shell id -u)
DOCKER_GID := $(shell id -g)
PHP_VERSION ?= 8.1
COMPOSE := DOCKER_UID=$(DOCKER_UID) DOCKER_GID=$(DOCKER_GID) PHP_VERSION=$(PHP_VERSION) docker compose
RUN1 := $(COMPOSE) run --rm task1
RUN2 := $(COMPOSE) run --rm task2

.DEFAULT_GOAL := help

help:
	@echo "Доступные команды:"
	@echo "  make build        сборка образа (PHP_VERSION=8.1 по умолчанию)"
	@echo "  make install      composer install в обоих заданиях"
	@echo "  make test         тесты обоих заданий"
	@echo "  make test-task1   тесты задания 1"
	@echo "  make test-task2   тесты задания 2"
	@echo "  make qa           phpstan + проверка стиля в обоих заданиях"
	@echo "  make cs-fix       автоформатирование под PSR-12"
	@echo "  make shell        shell внутри контейнера"
	@echo "  make clean        остановить контейнеры и удалить зависимости"

build:
	$(COMPOSE) build

install:
	$(RUN1) composer install
	$(RUN2) composer install

test: test-task1 test-task2

test-task1:
	$(RUN1) composer test

test-task2:
	$(RUN2) composer test

qa:
	$(RUN1) composer phpstan
	$(RUN1) composer cs-check
	$(RUN2) composer phpstan
	$(RUN2) composer cs-check

cs-fix:
	$(RUN1) composer cs-fix
	$(RUN2) composer cs-fix

shell:
	$(RUN2) sh

clean:
	$(COMPOSE) down -v --remove-orphans
	rm -rf vendor task1/vendor .phpunit.cache task1/.phpunit.cache

.PHONY: help build install test test-task1 test-task2 qa cs-fix shell clean
