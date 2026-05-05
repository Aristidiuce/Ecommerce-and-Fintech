# KiliSmart — Installation on Windows
# ============================================================
# Works on: Windows 10 (version 2004+), Windows 11
# Recommended method: WSL2 (Windows Subsystem for Linux)
# Alternative: XAMPP (simpler but less production-like)
# ============================================================


## TWO OPTIONS

  OPTION A — WSL2 (RECOMMENDED)
  Runs Ubuntu inside Windows. Identical to the Linux server.
  Best for developers. Production-identical environment.

  OPTION B — XAMPP
  Easier setup. Good for quick testing only.
  Not recommended for serious development.

  We explain BOTH below.


════════════════════════════════════════
  OPTION A: WSL2 (Windows Subsystem for Linux)
════════════════════════════════════════

## A1 — Enable WSL2

  Open PowerShell as Administrator (right-click → Run as administrator):

    wsl --install

  Restart your computer when prompted.

  After restart, Ubuntu will open and ask for a username and password.
  Create them — this is your Linux username (not Windows).

  Verify WSL2 is working:
    wsl --list --verbose
    # Should show Ubuntu with VERSION 2


## A2 — Open Ubuntu terminal

  Press Windows key → type "Ubuntu" → click the Ubuntu app
  
  All remaining commands run INSIDE this Ubuntu terminal.
  This is your Linux environment inside Windows.


## A3 — Install everything (same as Linux guide)

  # Update Ubuntu:
  sudo apt update && sudo apt upgrade -y

  # Install PHP 8.3:
  sudo apt install -y software-properties-common
  sudo add-apt-repository ppa:ondrej/php -y
  sudo apt update
  sudo apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-redis \
    php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip \
    php8.3-gd php8.3-bcmath php8.3-intl
  php -v  # Verify: PHP 8.3.x

  # Install MySQL 8:
  sudo apt install -y mysql-server
  sudo service mysql start
  sudo mysql_secure_installation

  # Create database:
  sudo mysql -u root -p
  # Inside MySQL:
  CREATE DATABASE kilismart_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'kilismart'@'localhost' IDENTIFIED BY 'YourPassword123!';
  GRANT ALL PRIVILEGES ON kilismart_test.* TO 'kilismart'@'localhost';
  FLUSH PRIVILEGES;
  EXIT;

  # Install Redis:
  sudo apt install -y redis-server
  sudo service redis-server start
  redis-cli ping  # Should return: PONG

  # Install Composer:
  curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
  composer --version

  # Install Node.js 20:
  curl -fsSL https://deb.nodesource.com/setup_20.x | sudo bash -
  sudo apt install -y nodejs
  node --version


## A4 — Set up KiliSmart

  # Navigate to your project (Windows files accessible via /mnt/c/):
  # If you extracted to C:\Users\YourName\Downloads\kilismart-full:
  cd /mnt/c/Users/YourName/Downloads/kilismart-full/backend

  # OR copy files to Linux home folder (faster):
  cp -r /mnt/c/Users/YourName/Downloads/kilismart-full ~/kilismart-full
  cd ~/kilismart-full/backend

  # Install dependencies:
  composer install

  # Set up environment:
  cp .env.example .env
  nano .env
  # Fill: DB_PASSWORD, MPESA keys

  php artisan key:generate
  php artisan migrate --force
  php artisan db:seed --force
  php artisan config:cache
  php artisan route:cache


## A5 — Start servers

  # Terminal 1 — Backend:
  cd ~/kilismart-full/backend
  php artisan serve

  # Terminal 2 — Queue worker (open new Ubuntu tab: Ctrl+Shift+N in Windows Terminal):
  cd ~/kilismart-full/backend
  php artisan queue:work

  # Open frontend in Windows browser:
  # Navigate to: C:\Users\YourName\Downloads\kilismart-full\frontend\index.html
  # OR: http://localhost:8000 (API only)


## A6 — Access WSL files from Windows Explorer

  Open Windows Explorer → type in address bar: \\wsl$\Ubuntu
  You can now drag and drop files between Windows and WSL.


## A7 — Daily workflow on Windows

  Open Windows Terminal (install from Microsoft Store if not installed)
  Ctrl+Shift+N to open new Ubuntu tabs

  Tab 1: php artisan serve
  Tab 2: php artisan queue:work
  Tab 3: your development work


════════════════════════════════════════
  OPTION B: XAMPP (Simpler, Quick Testing Only)
════════════════════════════════════════

## B1 — Download and install XAMPP

  Download from: apachefriends.org/download.html
  Choose: XAMPP for Windows, PHP 8.3+
  Install to default location: C:\xampp

## B2 — Start MySQL and Apache

  Open XAMPP Control Panel
  Click Start next to: Apache, MySQL
  Both should show green "Running"

## B3 — Create the database

  Open browser → http://localhost/phpmyadmin
  Click "New" on left sidebar
  Database name: kilismart_test
  Collation: utf8mb4_unicode_ci
  Click Create

## B4 — Install Composer for Windows

  Download from: getcomposer.org/Composer-Setup.exe
  Run the installer — it finds PHP automatically

## B5 — Set up the project

  Open Command Prompt (cmd):

  cd C:\xampp\htdocs
  # Copy kilismart-full/backend here, OR:
  xcopy /E /I "C:\Users\YourName\Downloads\kilismart-full\backend" "C:\xampp\htdocs\kilismart"

  cd C:\xampp\htdocs\kilismart
  composer install

  copy .env.example .env
  notepad .env
  # Edit:
  #   DB_HOST=127.0.0.1
  #   DB_DATABASE=kilismart_test
  #   DB_USERNAME=root
  #   DB_PASSWORD=      (leave blank — XAMPP default)

  php artisan key:generate
  php artisan migrate --force
  php artisan db:seed --force

## B6 — Access the app

  API: http://localhost/kilismart/public/api/v1/products
  Frontend: open C:\Users\YourName\Downloads\kilismart-full\frontend\index.html directly in browser


## TROUBLESHOOTING — Windows

  Problem: "wsl command not found"
  Fix: Update Windows 10 to version 2004 or later, or upgrade to Windows 11

  Problem: MySQL won't start in WSL
  Fix: sudo service mysql start (WSL doesn't auto-start services)

  Problem: Redis won't start in WSL  
  Fix: sudo service redis-server start

  Problem: Cannot access WSL from browser
  Fix: Use localhost — WSL automatically forwards ports to Windows

  Problem: "php not found" in Windows cmd
  Fix: Add PHP to Windows PATH: 
       Control Panel → System → Advanced → Environment Variables
       Add C:\xampp\php to Path variable

  Problem: Composer "memory exhausted"
  Fix: php -d memory_limit=-1 /usr/local/bin/composer install

  Problem: Files saved in Windows but changes not seen in WSL
  Fix: Save files to the WSL filesystem (/home/username/) not /mnt/c/
       /mnt/c/ has much slower I/O performance
