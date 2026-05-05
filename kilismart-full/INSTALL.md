# KiliSmart — Complete Server Setup Guide
# Ubuntu 22.04 / 24.04 — test.kilismart.co.tz

## WHAT IS IN THIS ZIP

```
kilismart-full/
├── backend/          ← Laravel 11 (complete, ready for composer install)
│   ├── app/          ← Models, Controllers, Services, Middleware
│   ├── bootstrap/    ← app.php (Laravel bootstrap — was missing before)
│   ├── config/       ← All config files (mpesa, database, cache, etc.)
│   ├── database/     ← Migrations (15 tables) + Seeder
│   ├── public/       ← index.php entry point
│   ├── routes/       ← api.php (50+ endpoints) + web.php + console.php
│   ├── storage/      ← Logs, cache, sessions (pre-created)
│   ├── composer.json ← Laravel 11 + Sanctum + Guzzle
│   └── .env.example  ← Copy to .env and fill your values
└── frontend/         ← 19 HTML pages (static, no build needed)
```

---

## STEP 1 — Upload files to server

```bash
# On your local machine — upload the zip
scp kilismart-full.zip root@YOUR_SERVER_IP:/tmp/

# On the server
cd /tmp
unzip kilismart-full.zip

# Move into place
mv kilismart-full/backend  /var/www/kilismart-test/backend
mv kilismart-full/frontend /var/www/kilismart-test/frontend
```

---

## STEP 2 — Install PHP 8.3 and extensions

```bash
apt update
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-redis \
               php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
               php8.3-bcmath php8.3-intl php8.3-tokenizer \
               php8.3-fileinfo unzip curl git

php -v   # Should show PHP 8.3.x
```

---

## STEP 3 — Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
composer --version   # Should show Composer 2.x
```

---

## STEP 4 — Install Laravel dependencies

```bash
cd /var/www/kilismart-test/backend
composer install --no-dev --optimize-autoloader
```

This will install Laravel framework, Sanctum, Guzzle — all packages.
Takes 2-5 minutes. You will see a vendor/ folder appear.

---

## STEP 5 — Setup MySQL database

```bash
apt install -y mysql-server
mysql_secure_installation

mysql -u root -p
```

Inside MySQL:
```sql
CREATE DATABASE kilismart_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'kilismart'@'localhost' IDENTIFIED BY 'YourStrongPassword123!';
GRANT ALL PRIVILEGES ON kilismart_test.* TO 'kilismart'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## STEP 6 — Install and configure Redis

```bash
apt install -y redis-server
systemctl enable redis-server
systemctl start redis-server
redis-cli ping   # Should return: PONG
```

---

## STEP 7 — Configure .env

```bash
cd /var/www/kilismart-test/backend
cp .env.example .env
nano .env
```

Fill in these values:
```
APP_KEY=          ← Leave blank for now (Step 8 will fill it)
APP_URL=https://test.kilismart.co.tz

DB_DATABASE=kilismart_test
DB_USERNAME=kilismart
DB_PASSWORD=YourStrongPassword123!   ← Your MySQL password from Step 5

MPESA_CONSUMER_KEY=     ← From developer.safaricom.co.ke
MPESA_CONSUMER_SECRET=  ← From developer.safaricom.co.ke
```

---

## STEP 8 — Generate app key and run migrations

```bash
cd /var/www/kilismart-test/backend

# Generate APP_KEY (adds to .env automatically)
php artisan key:generate

# Create all 15 database tables
php artisan migrate --force

# Seed with test data (9 categories, 6 suppliers, 15 products, 2 test users)
php artisan db:seed --force

# Link storage for product image uploads
php artisan storage:link

# Verify it works
php artisan --version
php artisan route:list | head -30
```

---

## STEP 9 — Fix permissions

```bash
chown -R www-data:www-data /var/www/kilismart-test/backend
chown -R www-data:www-data /var/www/kilismart-test/frontend
chmod -R 755 /var/www/kilismart-test/backend/storage
chmod -R 755 /var/www/kilismart-test/backend/bootstrap/cache
```

---

## STEP 10 — Install and configure Nginx

```bash
apt install -y nginx
```

Create Nginx config:
```bash
nano /etc/nginx/sites-available/kilismart-test
```

Paste this config:
```nginx
server {
    listen 80;
    server_name test.kilismart.co.tz;

    # Frontend (static HTML pages)
    root /var/www/kilismart-test/frontend;
    index index.html;

    # Block direct access to admin/internal pages
    location ~ ^/(admin|dashboard|supplier)\.html$ {
        return 404;
    }

    # API — Laravel backend
    location /api/ {
        root /var/www/kilismart-test/backend/public;
        try_files $uri $uri/ @laravel;
    }

    location @laravel {
        root /var/www/kilismart-test/backend/public;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/kilismart-test/backend/public/index.php;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Storage (uploaded product images)
    location /storage/ {
        alias /var/www/kilismart-test/backend/storage/app/public/;
        expires 30d;
    }

    # Frontend pages
    location / {
        try_files $uri $uri/ /index.html;
    }

    # robots.txt
    location = /robots.txt {
        access_log off;
        log_not_found off;
    }

    access_log /var/log/nginx/kilismart.access.log;
    error_log  /var/log/nginx/kilismart.error.log;
}
```

Enable the site:
```bash
ln -s /etc/nginx/sites-available/kilismart-test /etc/nginx/sites-enabled/
nginx -t        # Should say: syntax is ok
systemctl reload nginx
```

---

## STEP 11 — SSL Certificate (HTTPS)

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d test.kilismart.co.tz
# Follow prompts — enter email, agree to terms
```

---

## STEP 12 — Queue worker (for WhatsApp, M-Pesa processing)

```bash
apt install -y supervisor

cat > /etc/supervisor/conf.d/kilismart-worker.conf << 'CONF'
[program:kilismart-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/kilismart-test/backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/kilismart-worker.log
stopwaitsecs=3600
CONF

supervisorctl reread
supervisorctl update
supervisorctl start kilismart-worker:*
supervisorctl status
```

---

## STEP 13 — Test everything works

```bash
# 1. API health check
curl http://test.kilismart.co.tz/api/v1/products
# Should return: {"success":true,"data":{...}}

# 2. Check logs
tail -f /var/log/nginx/kilismart.error.log
tail -f /var/www/kilismart-test/backend/storage/logs/laravel.log

# 3. Test database
php artisan tinker --execute="echo DB::table('products')->count().' products';"
# Should return: 15 products
```

---

## DEFAULT LOGIN CREDENTIALS (after seeding)

| Role    | Phone         | Password           |
|---------|---------------|--------------------|
| Admin   | +255700000001 | Admin@KiliSmart2024! |
| Customer| +255700000002 | Test@1234!         |

Admin panel: https://test.kilismart.co.tz/admin.html

---

## COMMON ERRORS AND FIXES

**"bootstrap/app.php not found"**
→ Run `composer install` — the vendor folder needs to be created first.

**"SQLSTATE connection refused"**
→ Check DB_PASSWORD in .env matches MySQL password from Step 5.

**"Redis connection refused"**
→ Run `systemctl start redis-server`

**"Permission denied" on storage**
→ Run `chown -R www-data:www-data storage bootstrap/cache`

**502 Bad Gateway**
→ Check PHP-FPM: `systemctl status php8.3-fpm`
→ Check socket: `ls /var/run/php/php8.3-fpm.sock`

**"Class not found" errors**
→ Run `composer dump-autoload`
