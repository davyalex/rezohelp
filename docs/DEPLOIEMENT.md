# Rezohelp — Déploiement

Guide d'installation et de mise à jour en préproduction et en production (CONTEXTE §17).
Un VPS est recommandé (Memcached, Supervisor, HTTP/2). Sur un hébergement mutualisé, voir les variantes signalées « mutualisé ».

---

## 1. Serveur

| Élément | Version / réglage |
|---|---|
| PHP | 8.3 minimum (8.4 recommandé), en FPM derrière Nginx ou Apache |
| Extensions PHP | `bcmath`, `ctype`, `curl`, `dom`, `exif`, `fileinfo`, `gd` (avec WebP) ou `imagick`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, **`memcached`** |
| MySQL | 8.0, base et tables en `utf8mb4` / `utf8mb4_unicode_ci` |
| Memcached | 1.6, accessible seulement en local (`127.0.0.1:11211`) |
| Node.js | 22, uniquement pour compiler les assets (`npm run build`) ; on peut aussi compiler ailleurs et envoyer `public/build` |
| Supervisor | pour le worker de la file (VPS) |
| HTTPS | obligatoire : certificat valide (Let's Encrypt). L'application envoie HSTS en HTTPS |

L'extension `sockets` et Playwright ne servent qu'aux tests de bout en bout : inutiles en production.

Paramètres PHP conseillés : `memory_limit = 256M`, `upload_max_filesize = 6M`, `post_max_size = 30M` (photos de 5 Mo, jusqu'à 4 par annonce), `opcache.enable = 1`.

La racine web pointe sur le dossier `public/`. Le serveur doit pouvoir écrire dans `storage/` et `bootstrap/cache/`.

---

## 2. Fichier `.env` de production

Partir de `.env.example`, puis régler au minimum :

```env
APP_NAME=Rezohelp
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rezohelp.ci          # domaine définitif à confirmer (CONTEXTE §18)
APP_TIMEZONE=Africa/Abidjan

DB_DATABASE=rezohelp
DB_USERNAME=…
DB_PASSWORD=…

CACHE_STORE=memcached                 # mutualisé sans Memcached : file
MEMCACHED_HOST=127.0.0.1
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database

# Super administrateur créé au premier « db:seed » (connexion sur /admin/connexion)
ADMIN_NOM_UTILISATEUR=…
ADMIN_NOM=…
ADMIN_MOT_DE_PASSE=                   # vide : un mot de passe provisoire est affiché une seule fois

# Services externes
PAIEMENT_DRIVER=cinetpay              # fake interdit en production (guichet simulé désactivé)
CINETPAY_API_KEY=…
CINETPAY_SITE_ID=…
CINETPAY_SECRET=…
SMS_DRIVER=orange                     # ou twilio ; log tant qu'aucun fournisseur n'est choisi
VEILLE_DRIVE_DRIVER=google_drive
GOOGLE_DRIVE_FICHIER_IDENTIFIANTS=/chemin/hors/du/site/compte-de-service.json
GOOGLE_DRIVE_DOSSIER_ID=…
IA_DRIVER=regles                      # gratuit (D-064) ; claude pour l'IA payante
CLAUDE_API_KEY=                       # seulement si IA_DRIVER=claude
```

Règles :
- Le fichier JSON du compte de service Google se place **hors** du dossier du site, et n'est jamais commité.
- `.env` n'est jamais commité.
- Après toute modification de `.env` : `php artisan optimize` puis `php artisan queue:restart`. Un worker déjà lancé garde sinon l'ancienne configuration.

URL à déclarer chez CinetPay pour les notifications : `https://<domaine>/paiements/notification/cinetpay`.

---

## 3. Premier déploiement

```bash
git clone https://github.com/davyalex/rezohelp.git /var/www/rezohelp
cd /var/www/rezohelp
composer install --no-dev --optimize-autoloader
npm ci && npm run build && npm run budget-js
cp .env.example .env            # puis remplir le .env (section 2)
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force     # pays, communes, catégories, rôles, paramètres, plans, sources, super administrateur
php artisan storage:link
php artisan optimize
php artisan view:cache
```

Noter le mot de passe provisoire du super administrateur s'il s'affiche : il ne sera plus montré. Il est à changer à la première connexion.

Jamais `composer update` en production. Jamais `db:seed --class=DemoSeeder` en production : ce seeder crée de faux membres et de fausses annonces, réservés au développement et à la préproduction.

---

## 4. Tâches planifiées (cron)

Une seule ligne de cron, chaque minute :

```cron
* * * * * cd /var/www/rezohelp && php artisan schedule:run >> /dev/null 2>&1
```

Elle déclenche, à l'heure d'Abidjan :

| Commande | Fréquence |
|---|---|
| `veille:importer` | 01h20, 09h20, 17h20 |
| `publications:expirer` (expiration et suppression des annonces de la veille 5 jours après clôture) | chaque jour à 02h00 |
| `publications:purger` | dimanche à 03h00 |
| `abonnements:traiter` (relances, grâce, expirations) | chaque heure |
| `alertes:recap-quotidien` | chaque jour à 07h00 |
| `alertes:recap-sms` | chaque jour à 19h00 |
| `mises-en-avant:compter` | toutes les 5 minutes |

---

## 5. File d'attente (worker)

Alertes, SMS, notation des annonces et imports passent par la file `database`.

**VPS : Supervisor** (`/etc/supervisor/conf.d/rezohelp.conf`)

```ini
[program:rezohelp-file]
command=php /var/www/rezohelp/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
user=www-data
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/rezohelp/storage/logs/file.log
```

Puis `supervisorctl reread && supervisorctl update`.

**Mutualisé** : une seconde ligne de cron, chaque minute :

```cron
* * * * * cd /var/www/rezohelp && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

---

## 6. Mise à jour (déploiement courant)

```bash
cd /var/www/rezohelp
mysqldump --single-transaction rezohelp | gzip > ~/sauvegardes/avant-maj-$(date +%F-%H%M).sql.gz
git tag -f deploiement-precedent                # point de retour (section 8)
php artisan down --retry=60
git pull --ff-only
composer install --no-dev --optimize-autoloader
npm ci && npm run build && npm run budget-js
php artisan migrate --force
php artisan db:seed --class=ParametresSeeder --force          # ajoute les nouveaux paramètres, garde les valeurs modifiées
php artisan db:seed --class=RolesEtPermissionsSeeder --force  # si config/admin.php a changé
php artisan optimize
php artisan view:cache
php artisan cache:clear                         # si le gabarit des cartes a changé (cartes en cache 24 h)
php artisan queue:restart
php artisan up
```

Notes :
- `ParametresSeeder` doit tourner après chaque mise à jour qui ajoute un paramètre (D-067, D-068). Un paramètre absent lève une erreur, volontairement.
- `cache:clear` vide aussi le cache du fil et des référentiels. Il se reconstruit tout seul en quelques minutes ; les sessions, stockées en base, ne sont pas touchées.

---

## 7. Sauvegardes

Chaque nuit, par cron, vers un dossier synchronisé hors du serveur (autre machine ou stockage distant) :

```cron
30 3 * * * mysqldump --single-transaction --routines rezohelp | gzip > /sauvegardes/rezohelp-$(date +\%F).sql.gz
45 3 * * * tar czf /sauvegardes/medias-$(date +\%F).tar.gz -C /var/www/rezohelp/storage/app public
0 4 * * * find /sauvegardes -name 'rezohelp-*' -mtime +14 -delete; find /sauvegardes -name 'medias-*' -mtime +14 -delete
```

- **Base** : 14 jours conservés. **Médias** (photos, logos, reçus PDF) : `storage/app/public`.
- Une fois par mois, tester une restauration sur la préproduction :

  ```bash
  gunzip < rezohelp-AAAA-MM-JJ.sql.gz | mysql rezohelp_preprod
  ```

---

## 8. Retour arrière

Si une mise à jour pose problème :

```bash
php artisan down
git checkout deploiement-precedent
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

Ensuite, selon le cas :
- **Mise à jour sans migration** : rien à faire sur la base.
- **Avec migrations** : préférer la restauration de la sauvegarde prise juste avant la mise à jour (section 6), car `migrate:rollback` peut perdre des données. Commande :

  ```bash
  gunzip < ~/sauvegardes/avant-maj-….sql.gz | mysql rezohelp
  ```

Pour terminer :

```bash
php artisan optimize && php artisan view:cache && php artisan cache:clear
php artisan queue:restart
php artisan up
```

---

## 9. Vérifications après chaque déploiement

- [ ] `https://<domaine>/up` répond 200.
- [ ] Le fil s'affiche, une page détail s'ouvre, `/admin/connexion` permet de se connecter.
- [ ] Back-office → tableau de bord : le dernier import de la veille date de moins de 9 h (sinon, bandeau rouge).
- [ ] `php artisan schedule:list` affiche les tâches ; `storage/logs/laravel.log` ne contient pas d'erreur nouvelle.
- [ ] Le worker tourne (`supervisorctl status`), et la table `jobs` ne grossit pas.
- [ ] La connexion à Memcached fonctionne (`php artisan tinker`, puis `cache()->put('test', 1); cache()->get('test');`).
