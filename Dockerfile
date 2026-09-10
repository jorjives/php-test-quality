FROM php:8.4-cli-alpine

# Ship the same PHAR as the GitHub release; build it first with `box compile`.
COPY tq.phar /usr/local/bin/tq

LABEL org.opencontainers.image.source="https://github.com/jorjives/php-test-quality"
LABEL org.opencontainers.image.description="AST-based test quality analyser for PHPUnit tests"
LABEL org.opencontainers.image.licenses="MIT"

ENTRYPOINT ["tq"]
