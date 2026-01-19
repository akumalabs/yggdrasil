#!/bin/bash

set -e

echo "🌳 Yggdrasil - Proxmox Control Panel Installer"
echo "================================================"

# Check if running as root
if [ "$EUID" -eq 0 ]; then 
   echo "❌ Please do not run as root"
   exit 1
fi

# Update system
echo "📦 Updating system packages..."
sudo apt update

# Install dependencies
echo "📦 Installing required packages..."
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-common php8.3-pgsql php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-redis \
    postgresql postgresql-contrib redis-server nginx git curl unzip

# Install Composer
if ! command -v composer &> /dev/null; then
    echo "📦 Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

# Install Node.js 20
if ! command -v node &> /dev/null; then
    echo "📦 Installing Node.js 20..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt install -y nodejs
fi

# Clone repository
if [ -d "yggdrasil" ]; then
    echo "⚠️  Directory 'yggdrasil' already exists. Skipping clone."
    cd yggdrasil
else
    echo "📥 Cloning Yggdrasil repository..."
    git clone https://github.com/akumalabs/yggdrasil.git
    cd yggdrasil
fi

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

# Install Node dependencies
echo "📦 Installing Node dependencies..."
npm install

# Build assets
echo "🔨 Building frontend assets..."
npm run build

# Setup environment
if [ ! -f .env ]; then
    echo "⚙️  Setting up environment..."
    cp .env.example .env
    php artisan key:generate
fi

# Database setup
echo "🗄️  Setting up database..."
read -p "Enter PostgreSQL database name [yggdrasil]: " db_name
db_name=${db_name:-yggdrasil}

read -p "Enter PostgreSQL username [postgres]: " db_user
db_user=${db_user:-postgres}

read -sp "Enter PostgreSQL password: " db_pass
echo

# Update .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=$db_name/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=$db_user/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$db_pass/" .env

# Create database
echo "🗄️  Creating database..."
sudo -u postgres psql -c "CREATE DATABASE $db_name;" 2>/dev/null || echo "Database already exists"

# Run migrations
echo "🗄️  Running migrations..."
php artisan migrate --force

# Seed IP pool
echo "🌐 Seeding IP address pool..."
php artisan db:seed --class=IpAddressSeeder --force

# Setup systemd service for queue worker
echo "⚙️  Setting up queue worker service..."
sudo tee /etc/systemd/system/yggdrasil-queue.service > /dev/null <<EOF
[Unit]
Description=Yggdrasil Queue Worker
After=network.target

[Service]
Type=simple
User=$USER
WorkingDirectory=$(pwd)
ExecStart=/usr/bin/php $(pwd)/artisan queue:work --daemon
Restart=always

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable yggdrasil-queue
sudo systemctl start yggdrasil-queue

echo "✅ Installation complete!"
echo ""
echo "📝 Next steps:"
echo "1. Configure your Proxmox API token:"
echo "   php artisan tinker"
echo "   > App\\Models\\ProxmoxToken::create(['host' => 'pve.example.com', 'token_id' => 'user@pam!token', 'token_secret' => 'secret']);"
echo ""
echo "2. Start the development server:"
echo "   php artisan serve"
echo ""
echo "3. Visit: http://localhost:8000"
