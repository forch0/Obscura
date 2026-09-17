# Obscura — cPanel / Shared-Hosting Deployment

**Target:** standard shared PHP hosting (cPanel/DirectAdmin), MySQL, no Node.js on the server.
**Placeholders used throughout:** `domain.com` = your domain, `cpaneluser` = your cPanel account name, `~/public_html` = the app's web root.

The core principle: **the server never runs npm or Vite.** All frontend assets are
built locally and uploaded pre-compiled. The server only needs PHP 8.2+, MySQL,
and Composer dependencies (or a pre-built `vendor/`).

---

## 1. Build locally

```powershell
cd src
npm run build
composer install --no-dev --optimize-autoloader
```

`public/build/` now contains the Vite manifest + hashed assets. Upload the whole
directory — **always upload the complete `public/build`, not individual files**,
because asset filenames are content-hashed and `manifest.json` must match.

---

## 2. Upload

Upload the Laravel app to `~/public_html` (or a sibling dir — see §3).

**Do NOT upload:** `node_modules/`, `src.zip`, `bootstrap.zip`, `index.html.bak`,
`index.html_`, `cpanel_import.sql` (import it, don't serve it), `.env copy`,
`.env.dump`, `tests/`, `docs/`.

**Do upload:** `app/`, `bootstrap/`, `config/`, `database/`, `public/` (incl.
`build/` and `.htaccess`), `resources/`, `routes/`, `storage/` (empty dirs ok),
`vendor/` (or run `composer install` over SSH), `artisan`, `composer.json`,
`.env` (created on server — never commit it).

---

## 3. Document root

Point the domain/subdomain document root at **`~/public_html/public`**.

If the host won't let you set a custom docroot and the app sits in
`~/public_html` directly, either:

- create an `.htaccess` in `public_html` that rewrites everything into `public/`, or
- move the app to `~/obscura` and symlink `public_html` → `obscura/public`.

The app files must never be directly web-accessible — `.env` in particular.

---

## 4. `.env` (create on the server)

```ini
APP_NAME=Obscura
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpaneluser_obscura
DB_USERNAME=cpaneluser_dbuser
DB_PASSWORD=your_db_password

SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=null

MAIL_MAILER=log   # or smtp with real credentials
```

Generate the key: `php artisan key:generate`

**Watch out:** a bare line like `6PC5-RAG6-HX7Q-...` (a recovery code pasted
without a `KEY=` name) crashes Laravel with *"Failed to parse dotenv file"*.
Every line must be `NAME=value` or a `#` comment.

---

## 5. Database

Create the DB + user in cPanel (MySQL Databases), assign the user ALL PRIVILEGES.

Import the schema via **phpMyAdmin → Import → `cpanel_import.sql`** (generated
from migrations; includes `SET FOREIGN_KEY_CHECKS` guards, drops stale tables,
and pre-populates `migrations` so `php artisan migrate` reports "Nothing to
migrate").

Import the **entire file**, not individual table statements — foreign keys
fail (`errno: 150`) if `users` was created by an older partial import
(`bigint` ids vs `char(36)` UUIDs).

To regenerate the dump after migration changes, run the app's dump script
locally against MySQL/MariaDB.

---

## 6. Post-deploy commands (SSH)

```bash
cd ~/public_html
php artisan key:generate          # first deploy only
php artisan storage:link          # serve uploaded files
chmod -R 775 storage bootstrap/cache
php artisan optimize:clear        # clear all caches
php artisan config:cache          # production config cache
php artisan migrate --force       # should print "Nothing to migrate" after SQL import
```

Cron (cPanel → Cron Jobs), for stale re-key job cleanup:

```bash
* * * * * cd ~/public_html && php artisan schedule:run >> /dev/null 2>&1
```

(or a daily `php artisan rekey:cleanup` if the scheduler isn't used.)

---

## 7. Updating an existing deployment

| Changed | Upload | Then run |
|---|---|---|
| PHP (controllers/models/routes) | changed files | `php artisan optimize:clear && php artisan config:cache` |
| Blade views only | `resources/views/...` | `php artisan view:clear` |
| JS crypto modules (`resources/js/`) | full `public/build/` after local `npm run build` | nothing server-side; hard-refresh browser (`Ctrl+F5`) |
| New migration | file + run `php artisan migrate --force` | — |
| `.env` | edit on server | `php artisan config:clear && php artisan config:cache` |

---

## 8. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `419 Page Expired` on POST | Session/CSRF. Check `APP_KEY` is set (`grep APP_KEY .env`), `php artisan config:clear && php artisan config:cache`, `chmod -R 775 storage`, `SESSION_SECURE_COOKIE=true` over HTTPS, correct `APP_URL`. |
| *"Failed to parse dotenv file"* | Bare line in `.env` without `NAME=` — remove or comment it. |
| `npm: command not found` on server | Expected — build assets locally, upload `public/build`. |
| `Unknown column 'is_super_admin'` | Stale `users` table from old schema — re-import full `cpanel_import.sql`. |
| FK error `errno: 150` on import | Old `bigint` ids vs new UUID `char(36)` — import the whole dump (it drops stale tables first). |
| `exportKey ... not extractable` (login) | Stale `keypair` JS — upload new `public/build`, hard-refresh. |
| Blank pages / 500 | `APP_DEBUG=true` temporarily, check `storage/logs/laravel.log`, then set back to `false`. |
| Missing images/CSS | Check docroot is `public/`, `php artisan storage:link`, `APP_URL` matches the domain exactly. |

---

## 9. Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_URL` matches the real domain with `https://`
- [ ] `APP_KEY` generated on the server
- [ ] Docroot → `public/`, app files unreachable over HTTP
- [ ] `storage/` + `bootstrap/cache/` writable (775)
- [ ] `config:cache` + `route:cache` run after final `.env`
- [ ] DB imported via `cpanel_import.sql`; `migrate --force` → "Nothing to migrate"
- [ ] Cron for `schedule:run` (re-key cleanup)
- [ ] Hard-refresh browser after uploading a new `public/build`
