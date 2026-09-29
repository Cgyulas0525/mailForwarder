.PHONY: up down migrate test admin

up:
	docker compose up -d --build

down:
	docker compose down

migrate:
	docker compose exec app php artisan migrate

test:
	docker compose exec app php artisan test

admin:
	docker compose exec app php artisan admin:create admin@example.com
