# KiliSmart — Installation on macOS
# ============================================================
# Works on: macOS 12 Monterey, 13 Ventura, 14 Sonoma, 15+
# Tested on: MacBook Air M1/M2, MacBook Pro Intel/Apple Silicon
# Time needed: ~30 minutes first time
# ============================================================


## WHAT YOU ARE INSTALLING
─────────────────────────────────────────
  PHP 8.3        → runs the Laravel backend
  MySQL 8        → stores all data
  Redis          → background jobs + cache
  Composer       → PHP package manager
  Node.js 20     → frontend assets (optional)
  Nginx          → web server (or use PHP built-in for local)
─────────────────────────────────────────


## STEP 1 — Install Homebrew (Mac's package manager)

Open Terminal (Cmd + Space → type "Terminal" → Enter):

  /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

  # After install, run what it tells you (add to PATH):
  echo 'eval "$(/opt/homebrew/bin/brew shellenv)"' >> ~/.zprofile
  eval "$(/opt/homebrew/bin/brew shellenv)"

  # Verify:
  brew --version
  # Should show: Homebrew 4.x.x


## STEP 2 — Install PHP 8.3

  brew install php@8.3

  # Add PHP to your PATH:
  echo 'export PATH="/opt/homebrew/opt/php@8.3/bin:$PATH"' >> ~/.zprofile
  echo 'export PATH="/opt/homebrew/opt/php@8.3/sbin:$PATH"' >> ~/.zprofile
  source ~/.zprofile

  # Verify:
  php -v
  # Should show: PHP 8.3.x

  # PHP extensions needed (usually included with brew):
  php -m | grep -E "pdo|mysql|redis|curl|mbstring|xml|zip|gd|bcmath"
  # If any missing: brew install php@8.3-redis etc.


## STEP 3 — Install MySQL 8

  brew install mysql@8.0
  brew services start mysql@8.0

  # Add MySQL to PATH:
  echo 'export PATH="/opt/homebrew/opt/mysql@8.0/bin:$PATH"' >> ~/.zprofile
  source ~/.zprofile

  # Secure MySQL:
  mysql_secure_installation
  # Set a strong root password, answer Y to all prompts

  # Create KiliSmart database:
  mysql -u root -p

  # Inside MySQL prompt:
  CREATE DATABASE kilismart_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'kilismart'@'localhost' IDENTIFIED BY 'YourPassword123!';
  GRANT ALL PRIVILEGES ON kilismart_test.* TO 'kilismart'@'localhost';
  FLUSH PRIVILEGES;
  EXIT;


## STEP 4 — Install Redis

  brew install redis
  brew services start redis

  # Verify:
  redis-cli ping
  # Should return: PONG


## STEP 5 — Install Composer

  curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
  composer --version


## STEP 6 — Install Node.js (optional, for future frontend build)

  brew install node@20
  echo 'export PATH="/opt/homebrew/opt/node@20/bin:$PATH"' >> ~/.zprofile
  source ~/.zprofile
  node --version  # Should show v20.x.x


## STEP 7 — Set up the KiliSmart backend

  # Navigate to your kilismart project:
  cd ~/Downloads/kilismart-full/backend
  # (Or wherever you extracted the zip)

  # Install PHP dependencies:
  composer install

  # Create environment file:
  cp .env.example .env
  nano .env
  # Fill in DB_PASSWORD with your MySQL password
  # Fill in MPESA keys if you have them (use sandbox for testing)

  # Generate app key:
  php artisan key:generate

  # Run database migrations (creates all 15 tables):
  php artisan migrate --force

  # Seed the database (15 products, categories, admin user):
  php artisan db:seed --force

  # Cache for speed:
  php artisan config:cache
  php artisan route:cache


## STEP 8 — Start the backend server

  # Start the PHP development server:
  php artisan serve
  # Running at: http://localhost:8000

  # In a NEW terminal tab, start queue worker:
  php artisan queue:work
  # Keep this running for WhatsApp notifications and M-Pesa callbacks


## STEP 9 — Open the frontend

  # Open your browser and go to:
  # Frontend files: just open them directly in browser
  open ~/Downloads/kilismart-full/frontend/index.html

  # OR set up a simple local server for frontend:
  cd ~/Downloads/kilismart-full/frontend
  python3 -m http.server 3000
  # Then open: http://localhost:3000

  # API base URL for testing:
  # http://localhost:8000/api/v1/


## STEP 10 — Test everything works

  # Test API returns products:
  curl http://localhost:8000/api/v1/products
  # Should return JSON with 15 products

  # Test login:
  curl -X POST http://localhost:8000/api/v1/auth/login \
    -H "Content-Type: application/json" \
    -d '{"phone":"+255700000002","password":"Test@1234!"}'
  # Should return: {"success":true,"data":{"token":"...","wallet_balance":2000}}


## DAILY DEVELOPMENT WORKFLOW (macOS)

  # Each time you work on KiliSmart, open 3 terminal tabs:
  
  Tab 1 — Backend server:
    cd ~/kilismart-full/backend && php artisan serve

  Tab 2 — Queue worker:
    cd ~/kilismart-full/backend && php artisan queue:work

  Tab 3 — Frontend:
    cd ~/kilismart-full/frontend && python3 -m http.server 3000


## USEFUL macOS COMMANDS

  # Restart MySQL:
  brew services restart mysql@8.0

  # Restart Redis:
  brew services restart redis

  # Check all brew services:
  brew services list

  # View Laravel logs:
  tail -f ~/kilismart-full/backend/storage/logs/laravel.log

  # Clear all caches:
  cd ~/kilismart-full/backend
  php artisan optimize:clear

  # Fresh database (WARNING: deletes all data):
  php artisan migrate:fresh --seed

  # Tinker (interactive PHP console):
  php artisan tinker


## TROUBLESHOOTING — macOS

  Problem: "command not found: php"
  Fix: source ~/.zprofile OR brew reinstall php@8.3

  Problem: "Access denied for user 'kilismart'"
  Fix: mysql -u root -p → check password is correct

  Problem: "SQLSTATE[HY000] Can't connect to MySQL"
  Fix: brew services start mysql@8.0

  Problem: Redis connection refused
  Fix: brew services start redis

  Problem: "Class not found" errors
  Fix: cd backend && composer install

  Problem: Port 8000 already in use
  Fix: php artisan serve --port=8001
