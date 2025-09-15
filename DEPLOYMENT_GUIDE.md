# SSO Server Deployment Guide

## 🚀 Repository Setup

### 1. GitHub Repository erstellen
```bash
# Auf GitHub: github.com/today-is-life/sso-server-os
git remote add origin git@github.com:today-is-life/sso-server-os.git
git push -u origin main
```

### 2. Open Source Version vorbereiten
```bash
# Features für Open Source
cp -r /current/features/core/* /open-source/
rm -rf /open-source/features/enterprise/*
rm -rf /open-source/features/pro/*
```

## 📦 Distribution Strategy

### Open Source (MIT License)
- **Repository**: `github.com/today-is-life/sso-server-os`
- **Package**: `todayislife/sso-server-os`
- **Features**: Basic SSO, 2FA, Magic Links
- **Limit**: 100 aktive User

### Pro Version (59€/Jahr)
- **Repository**: `gitlab.com/todayislife/sso-server-pro` (private)
- **Package**: `todayislife/sso-server-pro` (private registry)
- **Features**: Unlimited Users, alle Social Logins, SIEM
- **Distribution**: Private Packagist

### Enterprise (199€/Jahr)
- **Repository**: `gitlab.com/todayislife/sso-server-enterprise` (private)
- **Package**: `todayislife/sso-server-enterprise` (private registry)
- **Features**: Advanced SIEM, SAML, LDAP, Compliance
- **Distribution**: Private Packagist

## 🔧 License Integration

### License Check Implementation
```php
// In Open Source Version
class LicenseManager
{
    public function checkLicense(): string
    {
        $userCount = User::count();

        if ($userCount > 100) {
            return 'upgrade_required';
        }

        return 'valid';
    }

    public function hasFeature(string $feature): bool
    {
        $openSourceFeatures = [
            'basic_auth', 'email_verification',
            '2fa', 'magic_links', 'password_reset'
        ];

        return in_array($feature, $openSourceFeatures);
    }
}
```

### Pro/Enterprise License Validation
```php
// In Pro/Enterprise Versions
use TodayIsLife\LicenseClient\LicenseClient;

class LicenseManager
{
    private LicenseClient $licenseClient;

    public function __construct()
    {
        $this->licenseClient = new LicenseClient(
            config('license.token'),
            config('license.server_url')
        );
    }

    public function validateLicense(): bool
    {
        try {
            $result = $this->licenseClient->validate(
                config('license.key'),
                request()->getHost()
            );

            return $result->isValid();
        } catch (LicenseException $e) {
            // Handle license errors
            return false;
        }
    }
}
```

## 🐳 Docker Configuration

### Dockerfile
```dockerfile
FROM php:8.2-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite \
    && docker-php-ext-install pdo_sqlite pdo_mysql

# Copy application
COPY . /var/www/html
WORKDIR /var/www/html

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Configure nginx & supervisor
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

### docker-compose.yml
```yaml
version: '3.8'

services:
  sso-server:
    build: .
    ports:
      - "8080:80"
    environment:
      - APP_ENV=production
      - DB_CONNECTION=sqlite
      - DB_DATABASE=/data/database.sqlite
    volumes:
      - sso_data:/data
    depends_on:
      - redis

  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data

volumes:
  sso_data:
  redis_data:
```

## ⚙️ Environment Configuration

### Production .env
```env
APP_NAME="SSO Server"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sso.yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sso_server
DB_USERNAME=sso_user
DB_PASSWORD=secure_password

# Cache & Sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1

# License (Pro/Enterprise only)
LICENSE_SERVER_URL=https://license.todayislife.de
LICENSE_API_TOKEN=lsv_your_token_here
LICENSE_KEY=XXXX-XXXX-XXXX-XXXX

# Social Logins (Pro/Enterprise)
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GITHUB_CLIENT_ID=your_github_client_id
GITHUB_CLIENT_SECRET=your_github_client_secret

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=mail_password
MAIL_ENCRYPTION=tls
```

## 🚀 Deployment Steps

### 1. Server Preparation
```bash
# Install PHP 8.2, Nginx, MySQL, Redis
sudo apt update
sudo apt install php8.2-fpm nginx mysql-server redis-server

# Install PHP extensions
sudo apt install php8.2-mysql php8.2-sqlite3 php8.2-redis php8.2-xml php8.2-curl

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Application Deployment
```bash
# Clone repository
git clone https://github.com/today-is-life/sso-server-os.git
cd sso-server-os

# Install dependencies
composer install --no-dev --optimize-autoloader

# Configure environment
cp .env.example .env
php artisan key:generate

# Setup database
php artisan migrate --force
php artisan db:seed --class=AdminSeeder

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3. Web Server Configuration
```nginx
# /etc/nginx/sites-available/sso
server {
    listen 80;
    server_name sso.yourdomain.com;
    root /var/www/sso-server/public;

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
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 4. SSL Certificate (Let's Encrypt)
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d sso.yourdomain.com
```

### 5. Monitoring & Maintenance
```bash
# Setup log rotation
sudo nano /etc/logrotate.d/sso-server

# Setup cron jobs
crontab -e
# Add: * * * * * cd /var/www/sso-server && php artisan schedule:run

# Setup backup script
sudo nano /usr/local/bin/sso-backup.sh
```

## 📊 Performance Optimization

### PHP-FPM Configuration
```ini
; /etc/php/8.2/fpm/pool.d/sso.conf
[sso]
user = www-data
group = www-data
listen = /run/php/php8.2-fpm-sso.sock
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

### Redis Configuration
```conf
# /etc/redis/redis.conf
maxmemory 256mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

## 🔒 Security Checklist

- [ ] SSL/TLS konfiguriert
- [ ] Firewall aktiv (nur 80/443 offen)
- [ ] Database User mit minimalen Rechten
- [ ] Secret Keys rotiert
- [ ] Log Monitoring aktiv
- [ ] Backup Strategy implementiert
- [ ] Rate Limiting konfiguriert
- [ ] Security Headers gesetzt

## 📈 Monitoring

### Health Check Endpoint
```bash
curl https://sso.yourdomain.com/health
```

### Log Monitoring
```bash
# Application logs
tail -f storage/logs/laravel.log

# Nginx logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# System logs
journalctl -f -u php8.2-fpm
```

---

## 🎯 Go-Live Checklist

1. [ ] Domain & DNS konfiguriert
2. [ ] SSL Zertifikat installiert
3. [ ] Database migrations ausgeführt
4. [ ] Admin Account erstellt
5. [ ] Email Versand getestet
6. [ ] Social Logins konfiguriert (Pro/Enterprise)
7. [ ] License Server verbunden (Pro/Enterprise)
8. [ ] Monitoring aktiv
9. [ ] Backup konfiguriert
10. [ ] Performance Tests durchgeführt

**Ready for Production! 🚀**