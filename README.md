# 🔐 Enterprise SSO Server - Open Source

A modern, enterprise-grade Single Sign-On (SSO) server built with Laravel 11, providing OAuth2/OIDC authentication, social login integration, and advanced security features.

![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php)
![OAuth2](https://img.shields.io/badge/OAuth2-OIDC-4285F4?style=for-the-badge)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

## ✨ Features

### 🔑 Authentication & Security
- **OAuth2/OIDC Server** - Full compliance with OAuth 2.0 and OpenID Connect standards
- **Magic Link Authentication** - Passwordless login via secure email links
- **Two-Factor Authentication (2FA)** - TOTP-based MFA using OTPHP library
- **Social Login Integration** - 8 providers: Google, GitHub, Facebook, Instagram, LinkedIn, Twitter, Microsoft, Apple
- **Enterprise Security** - XSS protection, CSRF tokens, secure headers, rate limiting

### 📊 Monitoring & Analytics
- **SIEM Integration** - Security Information and Event Management
- **Anomaly Detection** - Advanced threat detection and monitoring
- **Audit Logging** - Comprehensive activity tracking
- **Real-time Alerts** - Security event notifications

### 🛠️ Developer Experience
- **Production-ready PHP SDK** - PSR-compliant client library
- **Comprehensive API** - RESTful endpoints with full documentation
- **Docker Support** - Ready for containerized deployment
- **Laravel 11** - Built on the latest Laravel framework

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- Node.js & NPM
- MySQL/PostgreSQL
- Redis (recommended)

### Installation

```bash
# Clone the repository
git clone https://github.com/Today-is-Life/sso-server-os.git
cd sso-server-os

# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate
php artisan passport:install

# Build assets
npm run build

# Start development server
php artisan serve
```

### Environment Configuration

```env
# Basic Configuration
APP_NAME="Enterprise SSO Server"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sso.yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sso_server
DB_USERNAME=your_username
DB_PASSWORD=your_password

# OAuth2 Configuration
PASSPORT_PRIVATE_KEY=path/to/oauth-private.key
PASSPORT_PUBLIC_KEY=path/to/oauth-public.key

# Social Login (configure as needed)
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GITHUB_CLIENT_ID=your_github_client_id
GITHUB_CLIENT_SECRET=your_github_client_secret
```

## 📚 API Documentation

### Authentication Endpoints

```http
POST /api/auth/register          # User registration
POST /api/auth/login             # User login
POST /api/auth/logout            # User logout
POST /api/auth/refresh           # Token refresh
GET  /api/auth/user              # Get authenticated user
```

### OAuth2 Endpoints

```http
GET  /oauth/authorize            # Authorization endpoint
POST /oauth/token                # Token endpoint
GET  /oauth/userinfo             # User information endpoint
```

### 2FA Endpoints

```http
POST /api/auth/2fa/enable        # Enable 2FA
POST /api/auth/2fa/verify        # Verify 2FA token
POST /api/auth/2fa/disable       # Disable 2FA
```

## 🔌 SDK Usage

### PHP SDK

```bash
composer require today-is-life/sso-client
```

```php
use TodayIsLife\SSOClient\SSOClient;

$sso = new SSOClient([
    'base_url' => 'https://sso.yourdomain.com',
    'client_id' => 'your_client_id',
    'client_secret' => 'your_client_secret',
]);

// User registration
$user = $sso->auth()->register([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'secure_password'
]);

// OAuth2 authorization URL
$authUrl = $sso->oauth2()->getAuthorizationUrl([
    'scope' => 'openid email profile',
    'redirect_uri' => 'https://yourapp.com/callback'
]);
```

## 🏗️ Architecture

### Security Features
- **Rate Limiting** - Sliding window algorithm with Redis
- **CSRF Protection** - Laravel's built-in CSRF token validation
- **XSS Prevention** - Content Security Policy headers
- **SQL Injection Protection** - Eloquent ORM with prepared statements
- **Secure Headers** - HSTS, X-Frame-Options, X-Content-Type-Options

### Database Schema
- **UUID Primary Keys** - For enhanced security and scalability
- **Soft Deletes** - Maintain data integrity with logical deletion
- **Indexed Columns** - Optimized for performance
- **Foreign Key Constraints** - Data consistency enforcement

## 🔧 Configuration

### Social Providers Setup

#### Google OAuth2
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create OAuth2 credentials
3. Add redirect URI: `https://yourdomain.com/auth/google/callback`

#### GitHub OAuth2
1. Go to [GitHub Developer Settings](https://github.com/settings/developers)
2. Create OAuth App
3. Add callback URL: `https://yourdomain.com/auth/github/callback`

### Email Configuration

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Enterprise SSO"
```

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Test with coverage
php artisan test --coverage
```

## 🚀 Deployment

### Docker Deployment

```bash
# Build container
docker build -t sso-server .

# Run with environment variables
docker run -d \
  --name sso-server \
  -p 80:80 \
  -e DB_HOST=your_db_host \
  -e DB_DATABASE=sso_server \
  sso-server
```

### Production Checklist
- [ ] Configure environment variables
- [ ] Set up SSL certificates
- [ ] Configure database with proper credentials
- [ ] Set up Redis for caching and sessions
- [ ] Configure email service
- [ ] Set up monitoring and logging
- [ ] Configure social login providers
- [ ] Test OAuth2 flows
- [ ] Set up backup strategy

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Workflow
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new features
5. Ensure all tests pass
6. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🏢 Enterprise Support

Need enterprise features or professional support?

- **Pro Version** (€59/year): Priority support, advanced features
- **Enterprise Version** (€199/year): Custom integrations, SLA, dedicated support

Contact: [info@todayislife.de](mailto:info@todayislife.de)

## 🔗 Links

- **Documentation**: [API Docs](docs/api.md)
- **PHP SDK**: [GitHub Repository](https://github.com/Today-is-Life/sso-client-php)
- **Issues**: [GitHub Issues](https://github.com/Today-is-Life/sso-server-os/issues)
- **Company**: [Today is Life GmbH](https://todayislife.de)

---

**Built with ❤️ by [Today is Life GmbH](https://todayislife.de)**

*Empowering secure authentication for modern applications*