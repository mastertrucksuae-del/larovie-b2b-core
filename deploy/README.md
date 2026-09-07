# Deployment notes (Laravel Forge / nginx)

## Deploy script

Forge → Site → App → Deploy Script. The build steps matter: `public/build` is
gitignored, so the compiled CSS and self-hosted fonts only exist after
`npm run build` runs **on the server**.

```bash
cd /home/forge/wholesale.larovie.com
git pull origin main

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci
npm run build

php artisan migrate --force
php artisan storage:link
php artisan view:clear
php artisan config:cache
php artisan route:cache

( flock -w 10 9 || exit 1; echo 'Restarting FPM'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock
```

`php artisan migrate --force` is what enables search indexing — migration
`2026_08_10_000001_enable_search_indexing` flips the settings row on.

`php artisan storage:link` is what makes admin-uploaded artwork reachable. Every
image set in the panel — the logo, the homepage hero, product and variant image
overrides, brand logos — is written to `storage/app/public` and served from
`/storage/...`. Without the symlink each one is a 404 on the live site while
looking perfectly fine in the admin preview. The command is safe to re-run: it
does nothing when the link already exists.

## nginx

See [nginx/larovie-performance.conf](nginx/larovie-performance.conf) — static
asset caching and text compression, which cannot be set from the application
because nginx serves `public/` without touching PHP. Paste it into
Forge → Site → Nginx Configuration.

Security response headers (`X-Content-Type-Options`, `Referrer-Policy`,
`X-Frame-Options`, `Permissions-Policy`) are **not** here — they are applied by
`App\Http\Middleware\SecurityHeaders` so they survive a Forge nginx template
reset.

## Environment

`APP_URL` must be the canonical https production origin:

```
APP_URL=https://wholesale.larovie.com
```

Canonical tags, hreflang alternates, the `Sitemap:` directive in `robots.txt`
and every URL in `sitemap.xml` are generated from it. A wrong or http `APP_URL`
silently poisons all of them.

## Post-deploy verification

```bash
curl -s https://wholesale.larovie.com/robots.txt          # expect "Allow: /" + Sitemap line
curl -sI https://wholesale.larovie.com/sitemap.xml         # expect 200, application/xml
curl -s https://wholesale.larovie.com/ | grep -i 'name="robots"'   # expect index, follow
curl -sI https://wholesale.larovie.com/build/assets/*.css  # expect Cache-Control immutable
```

Then submit the sitemap in Google Search Console (founder action — external).
