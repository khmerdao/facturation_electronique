# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Makefile — raccourcis de développement
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PHP     = docker compose exec app php
CONSOLE = docker compose exec app php bin/console
COMPOSER= docker compose exec app composer

.PHONY: help install start stop restart build db-create db-migrate db-fixtures \
        cache-clear workers lint test jwt-keys

help:  ## Afficher cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ── Infrastructure ───────────────────────────────────────────────────────────
install: ## Installer le projet (docker + deps)
	docker compose up -d --build
	$(COMPOSER) install
	make jwt-keys
	make db-create
	make db-migrate

start: ## Démarrer les containers
	docker compose up -d

stop: ## Arrêter les containers
	docker compose down

restart: ## Redémarrer les containers
	docker compose restart

build: ## Rebuilder les images Docker
	docker compose build --no-cache

# ── Base de données ───────────────────────────────────────────────────────────
db-create: ## Créer la base de données
	$(CONSOLE) doctrine:database:create --if-not-exists

db-migrate: ## Appliquer les migrations Doctrine
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

db-rollback: ## Annuler la dernière migration
	$(CONSOLE) doctrine:migrations:migrate prev --no-interaction

db-fixtures: ## Charger les fixtures de développement
	$(CONSOLE) doctrine:fixtures:load --no-interaction

db-reset: ## Recréer la BDD (DROP + CREATE + MIGRATE + FIXTURES)
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	$(CONSOLE) doctrine:fixtures:load --no-interaction

# ── Application ───────────────────────────────────────────────────────────────
cache-clear: ## Vider le cache Symfony
	$(CONSOLE) cache:clear

cache-warmup: ## Préchauffer le cache Symfony
	$(CONSOLE) cache:warmup

workers: ## Démarrer tous les workers Messenger
	$(CONSOLE) messenger:consume async pdp_urgent exports emails webhooks --time-limit=3600 &

workers-stop: ## Arrêter les workers
	$(CONSOLE) messenger:stop-workers

# ── Build frontend ────────────────────────────────────────────────────────────
assets-install: ## Installer les dépendances npm
	docker compose exec app npm install

assets-dev: ## Compiler les assets (développement)
	docker compose exec app npm run dev

assets-watch: ## Watcher les assets (hot reload)
	docker compose exec app npm run watch

assets-build: ## Compiler les assets (production)
	docker compose exec app npm run build

# ── JWT ───────────────────────────────────────────────────────────────────────
jwt-keys: ## Générer les clés JWT (openssl)
	mkdir -p config/jwt
	docker compose exec app openssl genrsa -out config/jwt/private.pem -aes256 -passout env:JWT_PASSPHRASE 4096
	docker compose exec app openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin env:JWT_PASSPHRASE

# ── Qualité ───────────────────────────────────────────────────────────────────
lint: ## Vérifier la syntaxe PHP et Twig
	$(CONSOLE) lint:twig templates/
	$(CONSOLE) lint:yaml config/
	$(CONSOLE) lint:container

test: ## Lancer les tests PHPUnit
	$(PHP) vendor/bin/phpunit --testdox

test-coverage: ## Tests avec couverture de code
	$(PHP) vendor/bin/phpunit --coverage-html var/coverage/

# ── Accès ─────────────────────────────────────────────────────────────────────
sh: ## Ouvrir un shell dans le container app
	docker compose exec app sh

logs: ## Afficher les logs en temps réel
	docker compose logs -f app nginx

psql: ## Se connecter à MySQL (CLI)
	docker compose exec mysql mysql -u facturation -psecret facturation
