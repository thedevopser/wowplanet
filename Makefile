.PHONY: help build up down install install-hooks check-db drop-test-disks build-assets dev dev-stop worker worker-stop psql psql-test redis-cli clean test test-js tokens-check temp-paths-check lint lint-check static refactor refactor-check quality coverage coverage-php coverage-php-ci coverage-js crap mutate mutate-changed build-prod build-prod-ssr build-prod-all push push-ssr push-all deploy redeploy prod-up prod-down

# Development database coordinates. Not read from .env on purpose: make would
# parse the whole file as makefile syntax and choke on the first '#' inside a
# secret. Override from the environment when they differ.
DB_USERNAME ?= wowplanet
DB_DATABASE ?= wowplanet
DB_TEST_DATABASE ?= wowplanet_test
DB_PORT_HOST ?= 55432
REDIS_PORT_HOST ?= 56379

help: ## Display this help
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

build: ## Build docker images
	docker compose build --no-cache

up: ## Start the application
	docker compose up -d

down: ## Stop the application
	docker compose down

install: ## Install dependencies (Composer & NPM)
	composer install
	npm install

install-hooks: ## Install git pre-commit hook
	@sh scripts/install-hooks.sh

build-assets: ## Build assets for production
	npm run build

dev: ## Start Vite dev server (via Traefik HTTPS)
	docker compose --profile dev up vite -d

dev-stop: ## Stop Vite dev server
	docker compose --profile dev stop vite

worker: ## Start queue worker (dev)
	docker compose --profile dev up worker -d

worker-stop: ## Stop queue worker (dev)
	docker compose --profile dev stop worker

psql: ## Open a psql shell on the application database
	docker compose exec postgres psql -U $(DB_USERNAME) -d $(DB_DATABASE)

psql-test: ## Open a psql shell on the test database
	docker compose exec postgres psql -U $(DB_USERNAME) -d $(DB_TEST_DATABASE)

redis-cli: ## Open a redis-cli shell
	docker compose exec redis redis-cli

clean: ## Clear Laravel caches
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan route:clear
	docker compose exec app php artisan view:clear
	docker compose exec app php artisan cache:clear

# The suite runs on PostgreSQL, so it needs the stack. Fail on one readable line
# instead of a Pest stack trace nobody reads to the end. A TCP probe answers the
# only question asked here — is the stack up — and needs no credentials.
check-db:
	@php -r 'if (!@fsockopen("127.0.0.1", $(DB_PORT_HOST), $$errno, $$errstr, 2)) { fwrite(STDERR, "\033[31mPostgreSQL injoignable sur 127.0.0.1:$(DB_PORT_HOST).\033[0m\n\nLance la stack : make up\n\n"); exit(1); }'
	@php -r 'if (!@fsockopen("127.0.0.1", $(REDIS_PORT_HOST), $$errno, $$errstr, 2)) { fwrite(STDERR, "\033[31mRedis injoignable sur 127.0.0.1:$(REDIS_PORT_HOST).\033[0m\n\nLance la stack : make up\n\n"); exit(1); }'

# One process per core, each on its own database (wowplanet_test_test_<n>, created by
# Laravel) and its own Redis key prefix (tests/Pest.php).
test: check-db ## Run Pest tests in parallel (requires PostgreSQL: make up)
	vendor/bin/pest --parallel

lint: ## Fix code style (Laravel Pint)
	vendor/bin/pint

lint-check: ## Check code style without fixing (Laravel Pint)
	vendor/bin/pint --test --parallel

static: ## Run Larastan static analysis
	vendor/bin/phpstan analyse --memory-limit=2G

docs-coverage: ## Report classes documented nowhere (fails above the declared ceiling)
	php artisan docs:coverage

mixed-check: ## Fail on any `mixed` outside the boundary files declared in mixed-boundaries.txt
	@boundaries=$$(grep -v -e '^#' -e '^$$' mixed-boundaries.txt); \
	violations=$$(grep -rnw --include='*.php' mixed app | while IFS= read -r line; do \
		printf '%s\n' "$$boundaries" | grep -qxF "$${line%%:*}" || printf '%s\n' "$$line"; \
	done); \
	stale=$$(printf '%s\n' "$$boundaries" | while IFS= read -r file; do \
		grep -qw mixed "$$file" 2>/dev/null || printf '%s\n' "$$file"; \
	done); \
	if [ -n "$$violations" ]; then \
		printf '%s\n' "$$violations"; \
		echo "mixed outside the declared boundaries: narrow the value where it enters, or justify the file in mixed-boundaries.txt."; \
		exit 1; \
	fi; \
	if [ -n "$$stale" ]; then \
		printf '%s\n' "$$stale"; \
		echo "declared as boundaries but no longer holding any mixed: remove them from mixed-boundaries.txt."; \
		exit 1; \
	fi; \
	echo "No mixed outside the $$(printf '%s\n' "$$boundaries" | wc -l | tr -d ' ') declared boundary files."

# A clock-based identifier is shared by two parallel processes starting a test in the
# same microsecond, and the first to finish deletes the directory under the other.
temp-paths-check: ## Fail on any uniqid() in the tests: temporary paths go through testTempPath()
	@if grep -rn 'uniqid(' tests; then \
		echo "uniqid() in the tests: build temporary paths with testTempPath() instead."; \
		exit 1; \
	fi; \
	echo "No uniqid() in the tests."

refactor: ## Run Rector automated refactoring
	vendor/bin/rector process

refactor-check: ## Report pending Rector refactorings without writing (exit 2 if any)
	vendor/bin/rector process --dry-run

test-js: ## Run Vitest (Vue component tests)
	npx vitest run

tokens-check: ## Fail on any raw Tailwind palette class, arbitrary size or arbitrary colour in the front end
	node scripts/check-design-tokens.mjs

quality: lint static refactor docs-coverage mixed-check temp-paths-check tokens-check test test-js ## Run all quality checks

# Pest running in the container writes storage/framework/testing/disks as root,
# which the local user can then neither read nor clear: every later local test run
# fails on it. The container is the only place that can remove it.
drop-test-disks:
	@docker compose exec -T app rm -rf /app/storage/framework/testing/disks

coverage-php: check-db ## Run Pest with coverage, line floor 70%, then the CRAP check (requires the app container: pcov)
	@docker compose exec app vendor/bin/pest --parallel --coverage --min=70 --coverage-clover=coverage/clover.xml; status=$$?; $(MAKE) --no-print-directory drop-test-disks; [ $$status -eq 0 ] || exit $$status
	@$(MAKE) --no-print-directory crap

coverage-php-ci: ## Same as coverage-php, on the calling PHP (CI: pcov on the runner, no stack)
	vendor/bin/pest --parallel --coverage --min=70 --coverage-clover=coverage/clover.xml
	@$(MAKE) --no-print-directory crap

# The line floor only catches a vanished suite; the CRAP check is what names an untested
# complex method.
crap: ## Fail on any method above a CRAP of 30, read from coverage/clover.xml (run a coverage target first)
	@php artisan coverage:crap

# Each class runs against its dedicated tests; constants carry @pest-mutate-ignore:
# see scripts/mutate.sh and documentation/backend/10-qualite-ci.md.
MUTATION_MIN ?= 80

mutate: check-db ## Mutation testing on the whole perimeter of mutation-perimeter.txt, min 80% per class (requires the app container: pcov)
	@MUTATION_MIN=$(MUTATION_MIN) PEST="docker compose exec -T app vendor/bin/pest" sh scripts/mutate.sh; status=$$?; $(MAKE) --no-print-directory drop-test-disks; exit $$status

MUTATION_BASE ?= origin/main

mutate-changed: check-db ## Mutation testing on the perimeter classes the branch touches, against MUTATION_BASE (origin/main), min 80% per class
	@MUTATION_MIN=$(MUTATION_MIN) PEST="docker compose exec -T app vendor/bin/pest" sh scripts/mutate.sh --base=$(MUTATION_BASE); status=$$?; $(MAKE) --no-print-directory drop-test-disks; exit $$status

coverage-js: ## Run Vitest with coverage
	npx vitest run --coverage

coverage: check-db ## Coverage PHP + JS avec rapports HTML (PHP : conteneur requis)
	@echo ""
	@echo "\033[1;36m══════════════════════════════════════════════════════════════\033[0m"
	@echo "\033[1;36m  PHP Coverage (Pest + pcov, dans le conteneur) — plancher 70%\033[0m"
	@echo "\033[1;36m══════════════════════════════════════════════════════════════\033[0m"
	@echo ""
	@docker compose exec app vendor/bin/pest --parallel --coverage --min=70 --coverage-html=coverage/php --coverage-clover=coverage/clover.xml; status=$$?; $(MAKE) --no-print-directory drop-test-disks; [ $$status -eq 0 ] || exit $$status
	@echo ""
	@echo "\033[1;35m══════════════════════════════════════════════════════════════\033[0m"
	@echo "\033[1;35m  CRAP par méthode — seuil 30, bloquant\033[0m"
	@echo "\033[1;35m══════════════════════════════════════════════════════════════\033[0m"
	@echo ""
	@php artisan coverage:crap
	@echo ""
	@echo "\033[1;33m══════════════════════════════════════════════════════════════\033[0m"
	@echo "\033[1;33m  JavaScript Coverage (Vitest + v8) — plancher 70%\033[0m"
	@echo "\033[1;33m══════════════════════════════════════════════════════════════\033[0m"
	@echo ""
	@npx vitest run --coverage
	@echo ""
	@echo "\033[1;32m══════════════════════════════════════════════════════════════\033[0m"
	@echo "\033[1;32m  Rapports HTML :\033[0m"
	@echo "\033[1;32m    PHP : coverage/php/index.html\033[0m"
	@echo "\033[1;32m    JS  : coverage/js/index.html\033[0m"
	@echo "\033[1;32m══════════════════════════════════════════════════════════════\033[0m"

# =============================================================================
# Production
# =============================================================================

HARBOR_REGISTRY = harbor.wowplanet.fr
IMAGE_NAME = wowplanet/app
SSR_IMAGE_NAME = wowplanet/ssr

# Portainer auto-redeploy (set these in .env.deploy or environment)
PORTAINER_URL ?= https://portainer.wowplanet.fr
PORTAINER_API_KEY ?=
PORTAINER_STACK_ID ?=
PORTAINER_ENDPOINT_ID ?= 1

-include .env.deploy

build-prod: ## Build production Docker image
	docker build --target prod \
	             -t $(HARBOR_REGISTRY)/$(IMAGE_NAME):latest .

build-prod-ssr: ## Build production SSR (Node) Docker image
	docker build --target ssr \
	             -t $(HARBOR_REGISTRY)/$(SSR_IMAGE_NAME):latest .

build-prod-all: build-prod build-prod-ssr ## Build both prod images (app + SSR)

push: ## Push production image to Harbor
	docker push $(HARBOR_REGISTRY)/$(IMAGE_NAME):latest

push-ssr: ## Push SSR image to Harbor
	docker push $(HARBOR_REGISTRY)/$(SSR_IMAGE_NAME):latest

push-all: push push-ssr ## Push both prod images (app + SSR)

redeploy: ## Trigger Portainer stack redeploy (pull new image)
	@if [ -z "$(PORTAINER_API_KEY)" ]; then echo "ERROR: PORTAINER_API_KEY not set. Create .env.deploy or export it."; exit 1; fi
	@if [ -z "$(PORTAINER_STACK_ID)" ]; then echo "ERROR: PORTAINER_STACK_ID not set."; exit 1; fi
	@echo "==> Fetching current stack config from Portainer..."
	@STACK_FILE=$$(curl -sf -H "X-API-Key: $(PORTAINER_API_KEY)" \
		"$(PORTAINER_URL)/api/stacks/$(PORTAINER_STACK_ID)/file" | jq -r '.StackFileContent') && \
	ENV=$$(curl -sf -H "X-API-Key: $(PORTAINER_API_KEY)" \
		"$(PORTAINER_URL)/api/stacks/$(PORTAINER_STACK_ID)" | jq '.Env') && \
	echo "==> Redeploying stack with image pull..." && \
	curl -sf -X PUT \
		"$(PORTAINER_URL)/api/stacks/$(PORTAINER_STACK_ID)?endpointId=$(PORTAINER_ENDPOINT_ID)" \
		-H "X-API-Key: $(PORTAINER_API_KEY)" \
		-H "Content-Type: application/json" \
		-d "{\"stackFileContent\": $$(echo "$$STACK_FILE" | jq -Rs .), \"env\": $$ENV, \"pullImage\": true, \"prune\": true}" > /dev/null && \
	echo "==> Stack redeployed successfully!" || \
	(echo "ERROR: Redeploy failed. Check Portainer URL/credentials."; exit 1)

deploy: build-prod-all push-all redeploy ## Build, push (app + SSR), and redeploy via Portainer

prod-up: ## Start production stack
	docker compose -f docker-compose.prod.yml up -d

prod-down: ## Stop production stack
	docker compose -f docker-compose.prod.yml down
