# Resohelph

Plateforme d'emploi, de home service / petits métiers et de formation à Abidjan, présentée comme un fil d'actualité, mobile d'abord.

- Règles de code : [`CLAUDE.md`](CLAUDE.md)
- Spécification : [`docs/CONTEXTE.md`](docs/CONTEXTE.md)
- Plan par lots : [`docs/PLAN_DEVELOPPEMENT.md`](docs/PLAN_DEVELOPPEMENT.md)
- Avancement : [`docs/AVANCEMENT.md`](docs/AVANCEMENT.md) · Décisions : [`docs/DECISIONS.md`](docs/DECISIONS.md)

## Prérequis

PHP 8.3+ (extensions `intl`, `gd` avec WebP, `pdo_mysql`, `memcached` en production), Composer, Node 22+, MySQL 8.

## Installation locale

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# créer les bases MySQL « resohelph » et « resohelph_test » (utf8mb4)
php artisan migrate --seed
composer run dev        # serveur + Vite + file d'attente
```

Sans l'extension `memcached`, le cache bascule automatiquement sur `file`.
En local, les services externes utilisent les drivers `fake` / `log` (aucun appel réseau) ; les SMS et codes OTP s'écrivent dans `storage/logs/sms.log`.

## Qualité

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan test --parallel
```
