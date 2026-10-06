# Docker

Stack: `app` (php-fpm), `web` (nginx), `db` (MySQL 8.4), `worker` (supervisord: `queue:work` x `QUEUE_PROCS` + `schedule:work`).
The app uses database cache/session/queue, so there is no Redis, Horizon, Reverb, Scout or S3 container. Add them only if the code starts using them.
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

## Queue and scheduler
`worker` runs `queue:work` (database queue) and `schedule:work`, which executes the tasks from `routes/console.php` (ScheduledTaskRegistry, `serp:run-task`). Run exactly one `worker` replica, otherwise the scheduler fires twice (`withoutOverlapping` mitigates, not eliminates). Scale queue throughput with `QUEUE_PROCS`. Restart after deploys (`docker compose restart worker`). Logs: `docker compose logs -f worker`.
