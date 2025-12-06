.PHONY: quality test hooks rector

quality:
	@docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 phpstan analyse -c ./quality/phpstan.neon.dist --memory-limit=1G
	@docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 /tools/rector process --dry-run -c ./quality/rector.php --ansi

hooks:
	@chmod +x .githooks/* 2>/dev/null || true
	@git config core.hooksPath .githooks
	@echo "Git hooks installed (pre-commit & commit-msg)."

test:
	@docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 ./vendor/bin/phpunit --testdox

rector-dry:
	@docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 /tools/rector process --dry-run -c ./quality/rector.php --ansi

rector:
	@docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 /tools/rector process -c ./quality/rector.php --ansi

