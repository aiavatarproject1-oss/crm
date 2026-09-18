# Admin panel (Next.js) + runtime

## Super admins (seed)

| username | password |
| --- | --- |
| amirreza | 123456 |
| danial | 123456 |

Change the password from **Account** after first login. Creating extra admins requires at least 8 characters.

## Manual commands (testing)

```bash
# API
php artisan serve --host=0.0.0.0 --port=8000

# Queue (pick ONE: horizon OR queue:work)
php artisan horizon
# php artisan queue:work redis --queue=default --timeout=320

# WebSockets
php artisan reverb:start

# Admin UI
cd admin && npm run dev
```

Admin: http://localhost:3000/fa/login

API through ngrok: `https://resale-displace-banker.ngrok-free.dev` (set in `admin/.env.local` as `NEXT_PUBLIC_API_URL`).
Restart `npm run dev` after changing env.

## Supervisor (installed locally, daemon not started)

System `apt` Supervisor needs sudo, so it lives in a project venv:

`deploy/supervisor/.venv` + programs in `deploy/supervisor/crm.conf`.

Every program has `autostart=false`. Do **not** start the daemon while you are running commands yourself.

When you are ready:

```bash
cd deploy/supervisor
.venv/bin/supervisord -c supervisord.conf
.venv/bin/supervisorctl -c supervisord.conf start crm-api crm-horizon crm-reverb crm-admin-dev
# production UI: crm-admin (needs `npm run build` first)
# queue:work instead of Horizon: start crm-queue, not crm-horizon
```

Do **not** run Horizon and `queue:work` at the same time.
Do **not** run `crm-admin` and `crm-admin-dev` at the same time.

To copy the same programs into OS Supervisor later:

```bash
sudo cp deploy/supervisor/crm.conf /etc/supervisor/conf.d/crm.conf
sudo supervisorctl reread && sudo supervisorctl update
```

## Schema reset

```bash
php artisan migrate:fresh --seed --force
```

Every collection has `deleted_at` (soft delete) and query indexes. Unique indexes are partial (`deleted_at: null`) so deleted rows do not block reuse.
