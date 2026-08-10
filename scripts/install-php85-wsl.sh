#!/usr/bin/env bash
set -euo pipefail

echo "==> Instalando PHP 8.5 no WSL (Ubuntu)..."

sudo apt-get update
sudo apt-get install -y software-properties-common ca-certificates apt-transport-https
sudo add-apt-repository -y ppa:ondrej/php
sudo apt-get update

sudo apt-get install -y \
  php8.5-cli \
  php8.5-common \
  php8.5-xml \
  php8.5-mbstring \
  php8.5-curl \
  php8.5-zip \
  php8.5-sqlite3 \
  php8.5-mysql \
  php8.5-pgsql

sudo update-alternatives --install /usr/bin/php php /usr/bin/php8.5 95
sudo update-alternatives --set php /usr/bin/php8.5

echo
echo "==> PHP ativo:"
php -v
php -m | grep -iE 'pdo|mysql|pgsql|sqlite|curl|mbstring|openssl|xml' || true

echo
echo "==> Pronto. No projeto Mazarbul rode:"
echo "    cd /mnt/c/Users/Phales/Mazarbul && composer check"
