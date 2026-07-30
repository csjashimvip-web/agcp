SHELL := /bin/sh
COMPOSE := docker compose
DEV := $(COMPOSE) -f docker-compose.yml -f docker-compose.dev.yml

.PHONY: setup up down restart logs ps migrate seed test lint verify shell-backend shell-frontend clean

setup:
	sh scripts/setup.sh

up:
	$(DEV) up -d --build

down:
	$(DEV) down

restart:
	$(DEV) restart

logs:
	$(DEV) logs -f --tail=200

ps:
	$(DEV) ps

migrate:
	$(DEV) exec backend php artisan migrate

seed:
	$(DEV) exec backend php artisan db:seed

test:
	$(DEV) exec backend php artisan test
	$(DEV) exec frontend npm run typecheck
	$(DEV) exec frontend npm run lint

lint:
	$(DEV) exec backend ./vendor/bin/pint --test
	$(DEV) exec frontend npm run lint

verify:
	sh scripts/verify.sh

shell-backend:
	$(DEV) exec backend bash

shell-frontend:
	$(DEV) exec frontend sh

clean:
	$(DEV) down -v --remove-orphans
