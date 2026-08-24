# syntax=docker/dockerfile:1
ARG PHP_VERSION=8.1

FROM composer:2.8 AS composer

FROM php:${PHP_VERSION}-cli-alpine

RUN apk add --no-cache git unzip

COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache

RUN addgroup -g 1000 -S app \
    && adduser -u 1000 -S app -G app \
    && mkdir -p /tmp/composer \
    && chmod 1777 /tmp/composer

WORKDIR /app

USER app
