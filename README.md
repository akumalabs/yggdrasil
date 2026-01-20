# Yggdrasil - Proxmox VE Control Panel

A modern, self-hosted control panel for managing Proxmox VE virtual machines with multi-tenancy, IPAM, and advanced VM lifecycle management.

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![Laravel](https://img.shields.io/badge/Laravel-11-red.svg)
![PHP](https://img.shields.io/badge/PHP-8.3-purple.svg)

## ✨ Features

### Core VM Management
- 🖥️ **VM Lifecycle** - Create from templates, start, stop, migrate, and reinstall VMs
- 🎮 **NoVNC Console** - Browser-based VNC access to VMs
- 📸 **Snapshots** - Create, rollback, and manage VM snapshots
- 🔧 **Hot-plug Resources** - Adjust CPU and RAM without rebooting
- 🚨 **Rescue Mode** - Boot to rescue ISO for recovery with auto-mounting

### Advanced Features
- 👥 **RBAC** - Admin (full control) and Client (limited management) roles
- 📋 **Template System** - Convert VMs to templates, clone from templates with auto-VMID
- 💾 **Backup Management** - Create, restore, scheduled backups with retention policies
- 📊 **Resource Monitoring** - Real-time CPU/RAM/Disk/Network via QEMU Guest Agent
- 📡 **Bandwidth Tracking** - Allocated vs used (TB/month) with daily RRD snapshots
- ⚡ **Real-Time Progress** - WebSocket broadcasting for VM deployment status

### Infrastructure
- 🌐 **IPAM** - Automatic static IP assignment from pools
- 🛡️ **Firewall Management** - Configure VM firewall rules (admin-only)
- 🔐 **Cloud-Init Support** - Automated VM provisioning with SSH keys
- 📈 **Historical Metrics** - RRD data for graphs and trending

## 🚀 Quick Start (One-Liner Installation)

```bash
curl -fsSL https://raw.githubusercontent.com/akumalabs/yggdrasil/master/install.sh | bash
```

Or manual installation:

```bash
git clone https://github.com/akumalabs/yggdrasil.git && cd yggdrasil && composer install && npm install && npm run build && cp .env.example .env && php artisan key:generate && php artisan migrate && php artisan db:seed --class=IpAddressSeeder
```

## 📋 Requirements

- PHP 8.3+
- PostgreSQL 15+ (or MySQL 8+)
- Composer 2.x
- Node.js 20+ & npm
- Redis (for queues)
- Proxmox VE 8.x

## 🛠️ Installation

### 1. Clone the Repository

```bash
git clone https://github.com/akumalabs/yggdrasil.git
cd yggdrasil
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=yggdrasil
DB_USERNAME=postgres
DB_PASSWORD=your_password

RESCUE_ISO=local:iso/debian-live.iso
```

### 4. Run Migrations & Seed IP Pool

```bash
php artisan migrate
php artisan db:seed --class=IpAddressSeeder
```

### 5. Build Frontend Assets

```bash
npm run build
```

### 6. Start Queue Worker

```bash
php artisan queue:work --daemon
```

Or install Laravel Horizon:

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

### 7. Serve Application

**Development:**
```bash
php artisan serve
```

**Production (Nginx):**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/yggdrasil/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## 🔑 Proxmox API Token Setup

1. **Log in to Proxmox Web UI**
2. Navigate to **Datacenter → Permissions → API Tokens**
3. Click **Add** and create a token with **PVEAdmin** privileges
4. Copy the **Token ID** and **Secret**
5. Run the following command to store it:

```bash
php artisan tinker
```

```php
App\Models\ProxmoxToken::create([
    'host' => 'your-proxmox-host.com',
    'token_id' => 'user@pam!tokenname',
    'token_secret' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'
]);
```

## 📦 IP Pool Configuration

The seeder creates 10 IPs by default (`192.168.1.50-59`). To customize:

Edit `database/seeders/IpAddressSeeder.php`:

```php
for ($i = 100; $i < 200; $i++) {
    \App\Models\IpAddress::create([
        'ip' => "10.0.0.{$i}",
        'gateway' => '10.0.0.1',
        'netmask' => '24',
    ]);
}
```

Then reseed:

```bash
php artisan db:seed --class=IpAddressSeeder
```

## 🧪 Testing

```bash
php artisan test
```

Run unit tests only:

```bash
php artisan test --testsuite=Unit
```

## 📚 Architecture

### Tech Stack

- **Backend**: Laravel 11, PHP 8.3
- **Frontend**: Vue 3, Inertia.js, Tailwind CSS
- **Database**: PostgreSQL
- **Queue**: Laravel Queues (Database/Redis)
- **API Client**: Guzzle HTTP (Custom wrapper)

### Key Components

- **ProxmoxClient** - HTTP client wrapper for Proxmox API
- **Jobs** - Async handlers for long-running operations (VM creation, migration)
- **Multi-tenancy** - User-scoped queries via `user_id` foreign keys
- **IPAM** - `IpAddress` model with `free()` scope for assignment

## 🤝 Contributing

Pull requests are welcome! For major changes, please open an issue first.

## 📄 License

MIT License - see [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Built with [Laravel Breeze](https://github.com/laravel/breeze)
- Console powered by [noVNC](https://github.com/novnc/noVNC)

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/akumalabs/yggdrasil/issues)
- **Documentation**: [Wiki](https://github.com/akumalabs/yggdrasil/wiki)

---

Made with ❤️ for the Proxmox community
