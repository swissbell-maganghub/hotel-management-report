# Production Deployment Guide — Hotel Management Report System

**Swiss-Belhotel Waterfront Makassar**

## 1. Server Prerequisites
- **OS**: Linux (Ubuntu 24.04 LTS) or Windows Server managed by IT
- **Web Server**: Nginx or Apache pointing DocumentRoot to `public/` (HTTPS / Internal TLS required)
- **PHP**: `8.3+` or `8.4` with extensions `pdo_mysql`, `mbstring`, `xml`, `zip`, `gd`, `fileinfo`, `openssl`
- **Database**: MySQL 8.x on isolated internal VLAN (`3306` closed to public internet)
- **Node.js**: `20+` (only for building static Vite assets during release)

## 2. Initial Production Setup
1. Copy `.env.example` to `.env` and configure:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_DEMO_LOGIN=false` (demo 1-click login is automatically blocked whenever `APP_ENV=production`)
   - `DB_CONNECTION=mysql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
   - `SESSION_SECURE_COOKIE=true` (when served via HTTPS)
2. Install production dependencies & build assets:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php artisan key:generate
   ```
3. Run database migrations and initial seed (departments, templates, topology baseline, initial accounts):
   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   ```
4. **Immediately change all default seeded user passwords** (`password`) via the UI (`Kata Sandi` / IT Admin `Reset PW`) or deactivate unused demo accounts.
5. Cache Laravel configuration, routes, and views:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

## 3. Scheduler & Temporary Preview Cleanup
Register the Laravel scheduler in cron (Linux) or Task Scheduler (Windows) every minute so `pomec:cleanup-temp` automatically purges expired Excel preview files from `storage/app/private/pomec/tmp`:
```cron
* * * * * cd /var/www/hotel-report-system && php artisan schedule:run >> /dev/null 2>&1
```

## 4. Security & Backup Checklist
- Confirm `storage/app/private` is **not** publicly reachable from the web server.
- Confirm MySQL port `3306` is accessible only from the Application Server IP/VLAN.
- Require VPN + MFA for remote access outside the hotel LAN.
- Schedule automated daily backups of MySQL (`mysqldump`) and `storage/app/private` to separate NAS storage, and verify restore regularly.
