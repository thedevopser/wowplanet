.PHONY: quality test hooks rector phpcbf-check phpcbf-fix

# Vérifie le style de code avec PHPCBF (PSR12)
phpcbf-check:
	@docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 phpcs --standard=./quality/phpcbf.xml --report=summary

# Corrige automatiquement le style de code avec PHPCBF (PSR12)
phpcbf-fix:
	@docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 phpcbf --standard=./quality/phpcbf.xml; \
	EXIT_CODE=$$?; \
	if [ $$EXIT_CODE -eq 2 ]; then \
		echo "PHPCBF found unfixable errors!"; \
		exit 1; \
	fi; \
	exit 0

quality:
	@docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 phpstan analyse -c ./quality/phpstan.neon.dist --memory-limit=1G
	@docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 /tools/rector process --dry-run -c ./quality/rector.php --ansi

hooks:
	@chmod +x .githooks/* 2>/dev/null || true
	@git config core.hooksPath .githooks
	@echo "Git hooks installed (pre-commit & commit-msg)."

test:
	@php vendor/bin/phpunit --testdox

rector-dry:
	@docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 /tools/rector process --dry-run -c ./quality/rector.php --ansi

rector:
	@docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 /tools/rector process -c ./quality/rector.php --ansi

before-commit:
	@make phpcbf-fix
	@make rector
	@make quality
	@make test