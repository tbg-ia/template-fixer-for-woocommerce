# Template Fixer for WooCommerce - Development Workflow

## 🚀 Overview

This document outlines the complete development workflow for the **Template Fixer for WooCommerce** plugin, including automated testing, code quality checks, and deployment processes.

## 📋 Table of Contents

- [Development Setup](#development-setup)
- [Code Quality Standards](#code-quality-standards)
- [Testing Strategy](#testing-strategy)
- [CI/CD Pipeline](#cicd-pipeline)
- [Release Process](#release-process)
- [Security Guidelines](#security-guidelines)
- [Contributing Guidelines](#contributing-guidelines)

## 🛠️ Development Setup

### Prerequisites

- **PHP**: 7.4 or higher
- **WordPress**: 6.0 or higher
- **WooCommerce**: 7.0 or higher
- **Composer**: Latest version
- **Node.js**: 18+ (if using build tools)

### Local Development Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-username/template-fixer-for-woocommerce.git
   cd template-fixer-for-woocommerce
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Setup WordPress development environment:**
   ```bash
   # Using wp-cli
   wp core download
   wp config create --dbname=your_db --dbuser=your_user --dbpass=your_pass
   wp core install --url=http://localhost --title="Dev Site" --admin_user=admin --admin_password=admin --admin_email=dev@example.com
   wp plugin install woocommerce --activate
   ```

4. **Activate the plugin:**
   ```bash
   wp plugin activate template-fixer-for-woocommerce
   ```

## 📏 Code Quality Standards

### WordPress Coding Standards

We follow the **WordPress Coding Standards** with some custom rules:

```bash
# Run coding standards check
composer run lint

# Auto-fix coding standards issues
composer run lint:fix
```

### Configuration Files

- **`phpcs.xml`**: PHP CodeSniffer configuration
- **`psalm.xml`**: Static analysis configuration
- **`phpunit.xml.dist`**: Unit testing configuration

### Key Standards

- ✅ **WordPress-Extra** coding standards
- ✅ **WordPress-Docs** documentation standards
- ✅ **WordPress-VIP-Go** security standards
- ✅ **PHP 7.4+** compatibility
- ✅ **Internationalization (i18n)** compliance
- ✅ **Security escaping** for all output
- ✅ **Nonce verification** for forms and AJAX

## 🧪 Testing Strategy

### 1. Unit Testing

```bash
# Run all tests
composer run test

# Run tests with coverage
composer run test:coverage
```

### 2. WordPress Compatibility Testing

Our CI pipeline automatically tests against:

- **WordPress versions**: 6.0, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8
- **PHP versions**: 7.4, 8.0, 8.1, 8.2, 8.3

### 3. WooCommerce Compatibility Testing

Automated testing against WooCommerce versions:

- 7.0.0, 7.5.0, 8.0.0, 8.5.0, 9.0.0, 9.5.0, 9.7.1

### 4. Security Testing

```bash
# Run security analysis
composer run security
```

## 🔄 CI/CD Pipeline

### GitHub Actions Workflow

Our CI/CD pipeline includes 6 main jobs:

#### 1. **Code Quality & Standards**
- PHP CodeSniffer (WordPress standards)
- PHP Compatibility checks
- Security vulnerability scanning

#### 2. **WordPress Compatibility Testing**
- Matrix testing across WordPress versions
- Plugin activation tests
- Basic functionality verification

#### 3. **WooCommerce Compatibility Testing**
- Matrix testing across WooCommerce versions
- Integration testing
- Template detection verification

#### 4. **Security Scanning**
- Psalm security analysis
- Hardcoded credentials detection
- Vulnerability scanning

#### 5. **Build and Package**
- Asset compilation
- Clean package creation
- Release artifact generation

#### 6. **Deploy to WordPress.org**
- Automated deployment to WordPress repository
- Version tagging and release notes

### Workflow Triggers

- **Push to main/develop**: Full CI pipeline
- **Pull requests**: Code quality and testing
- **Releases**: Full pipeline + deployment

## 🚢 Release Process

### Version Numbering

We follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

### Release Steps

1. **Update version numbers:**
   - `template-fixer-for-woocommerce.php` (plugin header)
   - `readme.txt` (stable tag)
   - `composer.json` (if applicable)

2. **Update changelog:**
   - Add new version section to `readme.txt`
   - Document all changes, fixes, and new features

3. **Create release:**
   ```bash
   git tag -a v2.0.2 -m "Release version 2.0.2"
   git push origin v2.0.2
   ```

4. **GitHub Release:**
   - Create release on GitHub
   - Upload plugin ZIP file
   - Include release notes

### Automated Deployment

When a release is created, the CI pipeline automatically:

- ✅ Runs all quality checks
- ✅ Creates clean plugin package
- ✅ Uploads to GitHub releases
- ✅ Deploys to WordPress.org (if configured)

## 🔒 Security Guidelines

### Code Security

- **Input Sanitization**: All user input must be sanitized
- **Output Escaping**: All output must be escaped
- **Nonce Verification**: All forms and AJAX requests must verify nonces
- **Capability Checks**: All admin functions must check user capabilities

### Security Checklist

- [ ] No `eval()` functions used
- [ ] No hardcoded credentials
- [ ] All `$_GET` and `$_POST` variables sanitized
- [ ] All database queries use prepared statements
- [ ] All file operations use WordPress filesystem API
- [ ] All admin URLs use proper capability checks

## 🤝 Contributing Guidelines

### Development Workflow

1. **Fork the repository**
2. **Create feature branch**: `git checkout -b feature/your-feature-name`
3. **Make changes** following coding standards
4. **Run quality checks**: `composer run check-all`
5. **Commit changes**: Use conventional commit messages
6. **Push to fork**: `git push origin feature/your-feature-name`
7. **Create pull request**

### Commit Message Format

```
type(scope): description

[optional body]

[optional footer]
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

### Pull Request Requirements

- ✅ All CI checks must pass
- ✅ Code coverage must not decrease
- ✅ Documentation must be updated
- ✅ Changelog must be updated

## 📊 Quality Metrics

### Code Quality Targets

- **Code Coverage**: > 80%
- **PHPCS Compliance**: 100%
- **Security Issues**: 0
- **WordPress Compatibility**: 100%
- **WooCommerce Compatibility**: 100%

### Monitoring

- **GitHub Actions**: Automated CI/CD pipeline
- **Code Climate**: Code quality monitoring
- **Dependabot**: Dependency security updates

## 🆘 Troubleshooting

### Common Issues

1. **PHPCS Errors**: Run `composer run lint:fix` to auto-fix
2. **Test Failures**: Check WordPress/WooCommerce versions
3. **Security Issues**: Review input sanitization and output escaping
4. **Compatibility Issues**: Test with different PHP/WordPress versions

### Getting Help

- **Issues**: [GitHub Issues](https://github.com/your-username/template-fixer-for-woocommerce/issues)
- **Discussions**: [GitHub Discussions](https://github.com/your-username/template-fixer-for-woocommerce/discussions)
- **Documentation**: [Plugin Documentation](https://your-docs-site.com)

---

## 📝 Quick Commands Reference

```bash
# Development
composer install                    # Install dependencies
composer run lint                   # Check coding standards
composer run lint:fix               # Fix coding standards
composer run test                   # Run tests
composer run test:coverage          # Run tests with coverage
composer run security               # Security analysis
composer run check-all              # Run all quality checks

# WordPress CLI
wp plugin activate template-fixer-for-woocommerce
wp plugin deactivate template-fixer-for-woocommerce
wp plugin uninstall template-fixer-for-woocommerce

# Git workflow
git checkout -b feature/new-feature  # Create feature branch
git add .                           # Stage changes
git commit -m "feat: add new feature" # Commit with conventional message
git push origin feature/new-feature # Push to remote
```

---

**Last Updated**: January 2025  
**Plugin Version**: 2.0.2  
**WordPress Tested**: 6.8  
**WooCommerce Tested**: 9.7.1
