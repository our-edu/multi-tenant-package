.PHONY: help test test-coverage test-watch lint lint-fix clean install update

help:
	@echo "Available commands:"
	@echo "  make install          - Install dependencies"
	@echo "  make update           - Update dependencies"
	@echo "  make test             - Run tests"
	@echo "  make test-coverage    - Run tests with coverage report"
	@echo "  make test-watch       - Run tests in watch mode"
	@echo "  make lint             - Check code style (PSR-12)"
	@echo "  make lint-fix         - Fix code style issues"
	@echo "  make clean            - Clean up test artifacts"
	@echo "  make help             - Show this help message"

install:
	composer install

update:
	composer update

test:
	composer test

test-coverage:
	composer test:coverage

test-watch:
	./vendor/bin/phpunit --watch

lint:
	composer lint

lint-fix:
	composer lint:fix

clean:
	rm -rf .phpunit.cache/
	rm -rf .php-cs-fixer.cache
	rm -rf coverage/
	find . -name "*.log" -delete

.DEFAULT_GOAL := help

