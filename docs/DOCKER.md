# Docker

Stack: `app` (php-fpm), `web` (nginx), `db` (MySQL 8.4), `worker` (supervisord: `queue:work` x `QUEUE_PROCS` + `schedule:work`).
Defaults are database cache/session/queue and local disk. Redis and MinIO (S3) are optional compose profiles; there is no Horizon or Reverb service because those packages are not installed and the app does not broadcast.
Dev adds `vite`, `mailpit`, and (profile `test`) `db_test` + `mysql-tests`.

## First run (dev)
```
cp .env.docker.example .env      # set APP_KEY, DB_PASSWORD, DB_ROOT_PASSWORD
docker compose up --build        # app http://localhost:8080, mail UI :8025, vite :5173
```
First start runs `composer install` (host `vendor/`) and migrations (`RUN_MIGRATIONS` defaults to true in dev).
Key: `echo "APP_KEY=base64:$(openssl rand -base64 32)"`.

## Tests and static analysis
```
docker compose run --rm app vendor/bin/pest                 # sqlite :memory: per phpunit.xml
docker compose run --rm app vendor/bin/phpstan analyse --memory-limit=2G
docker compose run --rm app vendor/bin/pint --test
docker compose run --rm mysql-tests                         # full suite incl. NumberingConcurrencyTest on real MySQL
```
`NumberingConcurrencyTest` hardcodes `127.0.0.1:3306` root/`P@55wordMYSQL` (and skips otherwise); `mysql-tests` shares `db_test`'s network namespace to satisfy that.

## Production
```
cp .env.docker.example .env      # strong DB passwords, APP_ENV=production, APP_URL, SMTP, APP_KEY
docker compose -f docker-compose.yml up -d --build
```
Always pass `-f docker-compose.yml` so the dev override is skipped. Put TLS in front of `web` (port 8080) with your reverse proxy.
Config/route/view/event caches are built at container start. Prod image: non-root, opcache with no timestamp validation, no dev deps.

## Upgrading / migrating
```
git pull && docker compose -f docker-compose.yml build
docker compose -f docker-compose.yml run --rm -e RUN_MIGRATIONS=true app php artisan migrate --force   # or set RUN_MIGRATIONS=true
docker compose -f docker-compose.yml up -d
```
Take a backup first. Only `app` runs migrations; `worker` never does.

## Backups
```
docker compose exec db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines eugemsms' | gzip > backup-$(date +%F).sql.gz
gunzip -c backup.sql.gz | docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" eugemsms'
```
Also back up the `storage` volume (uploads): `docker run --rm -v eugemsms_storage:/s -v $PWD:/b alpine tar czf /b/storage.tgz -C /s .`

## Production file storage
Options, simplest first:
- **Plain local disk (default, zero dependencies):** `FILESYSTEM_DISK=local`; files live in the `storage` volume. Right for one VPS. Back up that volume (see Backups).
- **Garage (self-hosted S3, profile `garage`):** `dxflrs/garage:v2.3.0` (newest semver tag seen on Docker Hub; not pulled or tested here, owner must verify). MinIO's `s3` profile stays as is.
  1. Set `COMPOSE_PROFILES=garage` and the Garage block from `.env.docker.example` (RPC secret, admin token, bucket, key id `GK`+24 hex, secret 64 hex, `AWS_ENDPOINT=http://garage:3900`, path style true, region `garage`, `FILESYSTEM_DISK=s3`).
  2. `docker compose -f docker-compose.yml --profile garage up -d`. `garage-setup` runs once and is safe to re-run.
  3. Garage publishes no ports (internal network only). Config is `docker/garage/garage.toml` (single node, `replication_factor=1`, `s3_region=garage`); secrets come from env.
- **Unverified CLI steps** in `docker/garage/setup.sh` (check against the pinned version with `garage --help`): `layout show` output text "Current cluster layout version: 0" used to detect no layout; `node id -q`; `layout assign -z -c`; `layout apply --version 1`; `bucket info|create`; `key info|import --yes -n`; `bucket allow --read --write --owner --key`. The official image is shell-less, so setup builds a small alpine image copying `/garage`, and shares the garage network and meta volume. The healthcheck `/garage status` is also unverified. Never run end to end: no Docker daemon was available.
- **Backups:** stop or snapshot, then archive both volumes: `docker run --rm -v eugemsms_garagemeta:/m -v eugemsms_garagedata:/d -v $PWD:/b alpine sh -c 'tar czf /b/garage-meta.tgz -C /m . && tar czf /b/garage-data.tgz -C /d .'`. Meta (LMDB) is not safe to copy hot; stop `garage` first (or use `metadata_snapshots_dir`/filesystem snapshots). Keep the `.env` secrets too.
- **Status:** the S3 package is now in composer.json, and hardcoded disks were replaced by `DOCUMENTS_DISK`, `PUBLIC_ASSETS_DISK`, `BACKUPS_DISK` (see below). Untested against a real S3 endpoint.

## Queue and scheduler
`worker` runs `queue:work` (database queue) and `schedule:work`, which executes the tasks from `routes/console.php` (ScheduledTaskRegistry, `serp:run-task`). Run exactly one `worker` replica, otherwise the scheduler fires twice (`withoutOverlapping` mitigates, not eliminates). Scale queue throughput with `QUEUE_PROCS`. Restart after deploys (`docker compose restart worker`). Logs: `docker compose logs -f worker`.


## Switching drivers
All drivers are read from `.env` (config files use `env()` only). Defaults: database queue/cache/session, `local` disk, `BROADCAST_CONNECTION=log`.
- Redis: `COMPOSE_PROFILES=redis`, set `REDIS_PASSWORD`, then `SESSION_DRIVER/CACHE_STORE/QUEUE_CONNECTION=redis`. Uses the phpredis extension built into the image, no Composer package. Then `docker compose up -d` (restart `worker`).
- S3: `COMPOSE_PROFILES=s3` (or `redis,s3`), uncomment the S3 block in `.env.docker.example`, `FILESYSTEM_DISK=s3`. MinIO and a one-shot `minio-setup` bucket job start. Requires `league/flysystem-aws-s3-v3`, now added to composer.json/composer.lock (lock generated with `--with-all-dependencies --ignore-platform-reqs` under PHP 8.3, so it also bumped other packages; run `composer install` and the test suite on PHP 8.4 to confirm). For real AWS leave `AWS_ENDPOINT` empty and skip the profile.
- Horizon and websockets: not available (`laravel/horizon`, `laravel/reverb` not installed; no broadcasting config, channels or events exist).
- Disk roles are chosen by `.env` only, via `config/filesystems.php`: `DOCUMENTS_DISK` (uploads, generated documents, downloads; default `FILESYSTEM_DISK`, then `local`), `PUBLIC_ASSETS_DISK` (school branding; default `public`), `BACKUPS_DISK` (backups; default `backups`, `BACKUP_DISK` is a legacy alias). Existing files keep the disk recorded on their row. Status: written but not run (no vendor/ in the authoring sandbox); run `php artisan test --compact` locally.
- Other driver notes: `Install\TestServiceConnectionAction` uses the default disk and `Redis::connection()`; nothing hardcodes `Cache::store`, `Queue::connection` or a database connection beyond the installer probe.

## cPanel deployment
No Docker on shared hosting; same codebase, database drivers.
- PHP 8.4 with extensions: pdo_mysql, mbstring, bcmath, intl, gd, exif, zip, openssl, curl, fileinfo, tokenizer, xml, ctype. (pcntl and redis are not needed.)
- Point the domain docroot at `public/` (not the repo root). If the host cannot, keep the app above `public_html` and symlink or copy `public/` into it, editing the paths in `public/index.php`.
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, MySQL credentials, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `FILESYSTEM_DISK=local` (or `s3`; the S3 package is in composer.json).
- Install: `composer install --no-dev -o`, upload built `public/build` (run `npm run build` elsewhere), `php artisan migrate --force`, `php artisan config:cache route:cache view:cache`.
- `storage:link` needs symlink support; if disabled, ask the host or copy `storage/app/public` into `public/storage` (branding uploads use the `public` disk). Keep `storage/` and `bootstrap/cache` writable.
- Cron (cPanel, every minute):
```
* * * * * cd /home/USER/app && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/USER/app && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```
