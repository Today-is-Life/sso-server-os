# Contributing to Enterprise SSO Server

Thank you for your interest in contributing to the Enterprise SSO Server! We welcome contributions from the community.

## 🚀 How to Contribute

### 1. Fork the Repository
1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/your-username/sso-server-os.git
   cd sso-server-os
   ```

### 2. Set Up Development Environment
```bash
# Install dependencies
composer install
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Install Passport
php artisan passport:install
```

### 3. Create a Feature Branch
```bash
git checkout -b feature/your-feature-name
```

### 4. Make Your Changes
- Follow our coding standards (PSR-12)
- Write tests for new features
- Update documentation as needed
- Ensure all tests pass

### 5. Test Your Changes
```bash
# Run all tests
php artisan test

# Run coding standards check
./vendor/bin/phpcs

# Run static analysis
./vendor/bin/phpstan analyse
```

### 6. Submit a Pull Request
1. Push your changes to your fork
2. Create a pull request on GitHub
3. Provide a clear description of your changes
4. Reference any related issues

## 📝 Coding Standards

### PHP Code Style
- Follow PSR-12 coding standards
- Use meaningful variable and function names
- Add PHPDoc comments for all public methods
- Keep methods focused and small

### Commit Messages
Use conventional commit format:
```
type(scope): description

feat(auth): add magic link authentication
fix(oauth): resolve token refresh issue
docs(readme): update installation instructions
```

### Testing
- Write unit tests for new features
- Maintain test coverage above 80%
- Use meaningful test descriptions
- Test both success and failure scenarios

## 🏗️ Project Structure

```
app/
├── Http/Controllers/     # API and web controllers
├── Models/              # Eloquent models
├── Services/            # Business logic services
├── Middleware/          # Custom middleware
└── Events/              # Event classes

tests/
├── Feature/             # Integration tests
└── Unit/                # Unit tests

docs/
├── api.md              # API documentation
└── deployment.md       # Deployment guide
```

## 🐛 Bug Reports

When reporting bugs, please include:
- Laravel and PHP version
- Steps to reproduce the issue
- Expected vs actual behavior
- Error messages or logs
- Environment details

## 💡 Feature Requests

For feature requests:
- Check if the feature already exists
- Explain the use case and benefits
- Provide implementation suggestions if possible
- Be open to discussion and feedback

## 🔒 Security Issues

**DO NOT** report security vulnerabilities through public GitHub issues.

Instead, email security issues to: [security@todayislife.de](mailto:security@todayislife.de)

We'll acknowledge your email within 48 hours and provide a detailed response within 72 hours.

## 📋 Development Guidelines

### Database Changes
- Always create migrations for schema changes
- Use descriptive migration names
- Include rollback logic in migrations
- Test migrations on a copy of production data

### API Changes
- Maintain backward compatibility when possible
- Version new breaking changes
- Update API documentation
- Add integration tests for endpoints

### Dependencies
- Keep dependencies up to date
- Justify new dependencies in pull requests
- Use stable, well-maintained packages
- Check for security vulnerabilities

## 🤝 Code of Conduct

### Our Pledge
We pledge to make participation in our project a harassment-free experience for everyone, regardless of age, body size, disability, ethnicity, gender identity and expression, level of experience, nationality, personal appearance, race, religion, or sexual identity and orientation.

### Our Standards
- Be respectful and inclusive
- Accept constructive criticism gracefully
- Focus on what's best for the community
- Show empathy towards other contributors

### Enforcement
Instances of abusive, harassing, or otherwise unacceptable behavior may be reported to [conduct@todayislife.de](mailto:conduct@todayislife.de).

## 📞 Questions?

If you have questions about contributing:
- Create a GitHub Discussion
- Email us at [dev@todayislife.de](mailto:dev@todayislife.de)
- Join our Discord server: [discord.gg/todayislife](https://discord.gg/todayislife)

## 🎉 Recognition

Contributors will be:
- Listed in our README.md
- Mentioned in release notes
- Invited to our contributor Discord channel
- Eligible for contributor swag

Thank you for helping make Enterprise SSO Server better! 🚀

---

**© 2024-2025 Today is Life GmbH**