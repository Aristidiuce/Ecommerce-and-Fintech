# KiliSmart — Installation on Linux Ubuntu Server
# ============================================================
# Works on: Ubuntu 20.04 LTS, 22.04 LTS, 24.04 LTS
# Target: Your test server (10GB RAM, 2 cores, 150GB SSD)
# Domain: test.kilismart.co.tz
# Time: ~45 minutes
# ============================================================


## OVERVIEW
─────────────────────────────────────────────────────────
  This guide sets up the complete production-like environment.
  Run every command in order. Do not skip steps.
  All commands require root or sudo access.
─────────────────────────────────────────────────────────


════════════════════════════════════════
  PART 1 — SERVER PREPARATION
════════════════════════════════════════

## Connect to your server

  ssh root@YOUR_SERVER_IP
  # OR: ssh username@YOUR_SERVER_IP

## Update Ubuntu

  apt update && apt upgrade -y
  reboot
  # Wait 1 minute then reconnect: ssh root@YOUR_SERVER_IP

## Set timezone and locale

  timedatectl set-timezone Africa/Nairobi
  timedatectl
  # Should show: Time zone: Africa/Nairobi (EAT, +0300)

  locale-gen en_US.UTF-8
  update-locale LANG=en_US.UTF-8

## Create a dedicated user (security best practice)

  adduser kilismart
  # Set a strong password when prompted

  usermod -aG sudo kilismart
  usermod -aG www-data kilismart

## Configure firewall

  ufw allow OpenSSH
  ufw allow 80/tcp
  ufw allow 443/tcp
  ufw --force enable
  ufw status


════════════════════════════════════════
  PART 2 — INSTALL PHP 8.3
════════════════════════════════════════

  apt install -y software-properties-common
  add-apt-repository ppa:ondrej/php -y
  apt update

  apt install -y \
    php8.3 \
    php8.3-fpm \
    php8.3-mysql \
    php8.3-redis \
    php8.3-curl \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-zip \
    php8.3-gd \
    php8.3-bcmath \
    php8.3-intl \
    php8.3-tokenizer

  # Verify:
  php -v
  # PHP 8.3.x (cli)

  # Verify extensions:
  php -m | grep -E "pdo_mysql|redis|curl|mbstring|xml|zip|gd|bcmath"
  # All should appear in output


════════════════════════════════════════
  PART 3 — INSTALL MySQL 8
════════════════════════════════════════

  apt install -y mysql-server
  systemctl enable mysql
  systemctl start mysql

  # Secure MySQL:
  mysql_secure_installation
  # Options:
  #   VALIDATE PASSWORD component: Y
  #   Password strength: 2 (STRONG)
  #   Set root password: Choose a very strong password — WRITE IT DOWN
  #   Remove anonymous users: Y
  #   Disallow root login remotely: Y
  #   Remove test database: Y
  #   Reload privilege tables: Y

  # Create KiliSmart database and user:
  mysql -u root -p

  # Inside MySQL prompt — paste all at once:
  CREATE DATABASE kilismart_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'kilismart'@'localhost' IDENTIFIED BY 'YOUR_STRONG_DB_PASSWORD';
  GRANT ALL PRIVILEGES ON kilismart_test.* TO 'kilismart'@'localhost';
  FLUSH PRIVILEGES;
  SHOW DATABASES;
  EXIT;

  # WRITE DOWN:
  # DB_DATABASE = kilismart_test
  # DB_USERNAME = kilismart
  # DB_PASSWORD = YOUR_STRONG_DB_PASSWORD


════════════════════════════════════════
  PART 4 — INSTALL REDIS
════════════════════════════════════════

  apt install -y redis-server

  # Configure Redis to start on boot:
  systemctl enable redis-server
  systemctl start redis-server

  # Verify:
  redis-cli ping
  # Should return: PONG

  # Optional — set Redis password (recommended for production):
  nano /etc/redis/redis.conf
  # Find: # requirepass foobared
  # Change to: requirepass YOUR_REDIS_PASSWORD
  systemctl restart redis-server


════════════════════════════════════════
  PART 5 — INSTALL NGINX
════════════════════════════════════════

  apt install -y nginx
  systemctl enable nginx
  systemctl start nginx

  # Test Nginx is working:
  curl http://localhost
  # Should return Nginx welcome page HTML

  # Remove default site:
  rm -f /etc/nginx/sites-enabled/default


════════════════════════════════════════
  PART 6 — INSTALL COMPOSER + NODE
════════════════════════════════════════

  # Composer:
  curl -sS https://getcomposer.org/installer | php
  mv composer.phar /usr/local/bin/composer
  chmod +x /usr/local/bin/composer
  composer --version

  # Node.js 20 (for future frontend builds):
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt install -y nodejs
  node --version  # v20.x.x

  # Supervisor (queue worker manager):
  apt install -y supervisor
  systemctl enable supervisor
  systemctl start supervisor

  # Certbot (free SSL):
  apt install -y certbot python3-certbot-nginx


════════════════════════════════════════
  PART 7 — UPLOAD KILISMART FILES
════════════════════════════════════════

## Create directories:

  mkdir -p /var/www/kilismart-frontend
  mkdir -p /var/www/kilismart-backend
  chown -R www-data:www-data /var/www/kilismart-frontend
  chown -R www-data:www-data /var/www/kilismart-backend

## Upload from your computer — run these on YOUR MacBook/PC (not the server):

  # Frontend files:
  scp -r kilismart-full/frontend/* root@YOUR_SERVER_IP:/var/www/kilismart-frontend/

  # Backend files:
  scp -r kilismart-full/backend/* root@YOUR_SERVER_IP:/var/www/kilismart-backend/

## If using Git (recommended):

  cd /var/www/kilismart-backend
  git clone https://github.com/YOUR_USERNAME/kilismart-backend.git .

  cd /var/www/kilismart-frontend
  git clone https://github.com/YOUR_USERNAME/kilismart-frontend.git .


════════════════════════════════════════
  PART 8 — CONFIGURE THE BACKEND
════════════════════════════════════════

  cd /var/www/kilismart-backend

  # Install PHP packages:
  composer install --no-dev --optimize-autoloader

  # Create environment file:
  cp .env.example .env
  nano .env

  # ── Fill these values in .env: ──
  # APP_KEY=           (will be generated next)
  # APP_URL=https://test.kilismart.co.tz
  # DB_HOST=127.0.0.1
  # DB_DATABASE=kilismart_test
  # DB_USERNAME=kilismart
  # DB_PASSWORD=YOUR_STRONG_DB_PASSWORD
  # MPESA_CONSUMER_KEY=from_daraja_sandbox
  # MPESA_CONSUMER_SECRET=from_daraja_sandbox
  # AT_API_KEY=from_africastalking
  # ── Save with Ctrl+X, Y, Enter ──

  # Generate unique app key:
  php artisan key:generate

  # Run all 15 database table migrations:
  php artisan migrate --force

  # Seed: 9 categories, 6 suppliers, 15 products, admin + test users:
  php artisan db:seed --force
  # Note the output:
  # Admin: +255700000001 / Admin@KiliSmart2024!
  # Customer: +255700000002 / Test@1234!

  # Cache everything for speed:
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache

  # Create storage symlink:
  php artisan storage:link

  # Set permissions:
  chown -R www-data:www-data /var/www/kilismart-backend
  chmod -R 755 /var/www/kilismart-backend/storage
  chmod -R 755 /var/www/kilismart-backend/bootstrap/cache


════════════════════════════════════════
  PART 9 — CONFIGURE NGINX
════════════════════════════════════════

  nano /etc/nginx/sites-available/kilismart-test

  # Paste the ENTIRE block below:
  ──────────────────────────────────────────────────────────────
  server {
      listen 80;
      server_name test.kilismart.co.tz;
      return 301 https://$host$request_uri;
  }

  server {
      listen 443 ssl;
      server_name test.kilismart.co.tz;

      # SSL — filled by Certbot in next step
      ssl_certificate /etc/letsencrypt/live/test.kilismart.co.tz/fullchain.pem;
      ssl_certificate_key /etc/letsencrypt/live/test.kilismart.co.tz/privkey.pem;
      include /etc/letsencrypt/options-ssl-nginx.conf;
      ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

      add_header X-Frame-Options "SAMEORIGIN" always;
      add_header X-Content-Type-Options "nosniff" always;

      # Frontend
      root /var/www/kilismart-frontend;
      index index.html;

      # API → Laravel
      location /api/ {
          alias /var/www/kilismart-backend/public/;
          try_files $uri $uri/ @laravel;
      }

      location @laravel {
          fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
          fastcgi_index index.php;
          fastcgi_param SCRIPT_FILENAME /var/www/kilismart-backend/public/index.php;
          include fastcgi_params;
          fastcgi_read_timeout 300;
      }

      # Storage (uploaded images)
      location /storage/ {
          alias /var/www/kilismart-backend/storage/app/public/;
      }

      # Frontend routing
      location / {
          try_files $uri $uri/ /index.html;
      }

      # Static files cache
      location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
          expires 30d;
          add_header Cache-Control "public, immutable";
      }

      location ~ /\.(?!well-known).* { deny all; }

      client_max_body_size 10M;
      access_log /var/log/nginx/kilismart.access.log;
      error_log /var/log/nginx/kilismart.error.log;
  }
  ──────────────────────────────────────────────────────────────

  # Enable site:
  ln -s /etc/nginx/sites-available/kilismart-test /etc/nginx/sites-enabled/

  # Test config:
  nginx -t
  # Must say: syntax is ok

  systemctl reload nginx


════════════════════════════════════════
  PART 10 — DNS + SSL
════════════════════════════════════════

## Point your DNS first (do this BEFORE SSL):

  Login to your domain registrar or Cloudflare
  Add DNS record:
    Type:  A
    Name:  test
    Value: YOUR_SERVER_IP
    Proxy: OFF (grey cloud)
    TTL:   Auto

  Wait 5–15 minutes for DNS to propagate.
  Verify: nslookup test.kilismart.co.tz
  Should return your server IP.

## Get free SSL certificate:

  # DNS must be pointing to your server first!
  certbot --nginx -d test.kilismart.co.tz

  # Follow prompts — enter email, agree to terms

  # Test auto-renewal:
  certbot renew --dry-run

  # Reload Nginx after SSL:
  systemctl reload nginx


════════════════════════════════════════
  PART 11 — QUEUE WORKER (SUPERVISOR)
════════════════════════════════════════

  nano /etc/supervisor/conf.d/kilismart-worker.conf

  # Paste:
  ──────────────────────────────────────────────────────────────
  [program:kilismart-worker]
  process_name=%(program_name)s_%(process_num)02d
  command=php /var/www/kilismart-backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
  autostart=true
  autorestart=true
  stopasgroup=true
  killasgroup=true
  user=www-data
  numprocs=2
  redirect_stderr=true
  stdout_logfile=/var/www/kilismart-backend/storage/logs/worker.log
  ──────────────────────────────────────────────────────────────

  supervisorctl reread
  supervisorctl update
  supervisorctl start kilismart-worker:*

  # Verify:
  supervisorctl status
  # Should show: kilismart-worker:00   RUNNING
  #              kilismart-worker:01   RUNNING


════════════════════════════════════════
  PART 12 — CRON JOB
════════════════════════════════════════

  crontab -e
  # Choose editor 1 (nano)

  # Add this line at the bottom:
  * * * * * www-data php /var/www/kilismart-backend/artisan schedule:run >> /dev/null 2>&1

  # Save: Ctrl+X, Y, Enter


════════════════════════════════════════
  PART 13 — VERIFY EVERYTHING
════════════════════════════════════════

  # Check all services are running:
  systemctl status nginx | grep Active
  systemctl status mysql | grep Active
  systemctl status redis-server | grep Active
  supervisorctl status

  # Test API:
  curl https://test.kilismart.co.tz/api/v1/products
  # Should return 15 products as JSON

  # Test website in browser:
  # https://test.kilismart.co.tz

  # Test login API:
  curl -X POST https://test.kilismart.co.tz/api/v1/auth/login \
    -H "Content-Type: application/json" \
    -d '{"phone":"+255700000002","password":"Test@1234!"}'

  # Check error logs if anything fails:
  tail -f /var/www/kilismart-backend/storage/logs/laravel.log


════════════════════════════════════════
  USEFUL DAILY COMMANDS (Linux)
════════════════════════════════════════

  # Restart all services:
  systemctl restart nginx mysql redis-server
  supervisorctl restart kilismart-worker:*

  # View live logs:
  tail -f /var/www/kilismart-backend/storage/logs/laravel.log
  tail -f /var/log/nginx/kilismart.error.log

  # After code changes:
  cd /var/www/kilismart-backend
  php artisan config:clear && php artisan config:cache
  php artisan route:cache
  supervisorctl restart kilismart-worker:*

  # Check disk space:
  df -h

  # Check memory usage:
  free -h

  # Check what's using port 80/443:
  ss -tlnp | grep -E ':80|:443'

  # Database backup:
  mysqldump -u kilismart -p kilismart_test > backup_$(date +%Y%m%d).sql

  # Fresh install (WARNING — deletes all data):
  php artisan migrate:fresh --seed --force


════════════════════════════════════════
  TROUBLESHOOTING — Linux
════════════════════════════════════════

  Problem: 502 Bad Gateway
  Fix:
    systemctl status php8.3-fpm
    systemctl restart php8.3-fpm
    nginx -t && systemctl reload nginx

  Problem: 500 Internal Server Error
  Fix:
    tail -f /var/log/nginx/kilismart.error.log
    tail -f /var/www/kilismart-backend/storage/logs/laravel.log
    chown -R www-data:www-data /var/www/kilismart-backend

  Problem: SSL certificate not found
  Fix:
    # Make sure DNS is pointing to your IP first
    certbot --nginx -d test.kilismart.co.tz

  Problem: Queue worker not running
  Fix:
    supervisorctl reread && supervisorctl update
    supervisorctl start kilismart-worker:*
    tail -f /var/www/kilismart-backend/storage/logs/worker.log

  Problem: MySQL access denied
  Fix:
    mysql -u root -p
    SHOW GRANTS FOR 'kilismart'@'localhost';
    GRANT ALL ON kilismart_test.* TO 'kilismart'@'localhost';
    FLUSH PRIVILEGES;

  Problem: Permission denied on storage
  Fix:
    chown -R www-data:www-data /var/www/kilismart-backend/storage
    chmod -R 775 /var/www/kilismart-backend/storage
    chmod -R 775 /var/www/kilismart-backend/bootstrap/cache

  Problem: Cannot connect to Redis
  Fix:
    redis-cli ping
    systemctl restart redis-server
    # Check .env: REDIS_HOST=127.0.0.1 REDIS_PORT=6379

  Problem: "No application encryption key has been set"
  Fix:
    cd /var/www/kilismart-backend
    php artisan key:generate
    php artisan config:cache
