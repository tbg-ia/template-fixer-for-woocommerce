#!/bin/bash

# Template Fixer for WooCommerce - Development Setup Script
# This script automates the local development environment setup

set -e

echo "🚀 Setting up Template Fixer for WooCommerce development environment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Check if we're in the plugin directory
if [[ ! -f "template-fixer-for-woocommerce.php" ]]; then
    print_error "Please run this script from the plugin root directory"
    exit 1
fi

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed. Please install Composer first."
    exit 1
fi

# Check if wp-cli is installed
if ! command -v wp &> /dev/null; then
    print_warning "WP-CLI is not installed. Installing WP-CLI..."
    curl -O https://raw.githubusercontent.com/wp-cli/wp-cli/v2.8.1/wp-cli.phar
    chmod +x wp-cli.phar
    sudo mv wp-cli.phar /usr/local/bin/wp
    print_status "WP-CLI installed successfully"
fi

# Install Composer dependencies
print_info "Installing Composer dependencies..."
composer install --dev
print_status "Composer dependencies installed"

# Install WordPress Coding Standards
print_info "Setting up WordPress Coding Standards..."
composer global require "squizlabs/php_codesniffer=*"
composer global require wp-coding-standards/wpcs
composer global require phpcompatibility/phpcompatibility-wp
phpcs --config-set installed_paths ~/.composer/vendor/wp-coding-standards/wpcs,~/.composer/vendor/phpcompatibility/phpcompatibility-wp
print_status "WordPress Coding Standards configured"

# Create necessary directories
print_info "Creating development directories..."
mkdir -p tests/reports
mkdir -p coverage
mkdir -p logs
print_status "Development directories created"

# Setup Git hooks (if .git exists)
if [[ -d ".git" ]]; then
    print_info "Setting up Git hooks..."
    
    # Pre-commit hook
    cat > .git/hooks/pre-commit << 'EOF'
#!/bin/bash
echo "Running pre-commit checks..."

# Run PHP CodeSniffer
echo "Checking coding standards..."
composer run lint
if [ $? -ne 0 ]; then
    echo "❌ Coding standards check failed. Please fix the issues before committing."
    exit 1
fi

# Run tests
echo "Running tests..."
composer run test
if [ $? -ne 0 ]; then
    echo "❌ Tests failed. Please fix the issues before committing."
    exit 1
fi

echo "✅ All pre-commit checks passed!"
EOF
    
    chmod +x .git/hooks/pre-commit
    print_status "Git pre-commit hook installed"
fi

# Check WordPress installation
print_info "Checking WordPress installation..."
if wp core is-installed 2>/dev/null; then
    print_status "WordPress is installed and configured"
    
    # Check if WooCommerce is installed
    if wp plugin is-installed woocommerce 2>/dev/null; then
        print_status "WooCommerce is installed"
        
        if wp plugin is-active woocommerce 2>/dev/null; then
            print_status "WooCommerce is active"
        else
            print_warning "WooCommerce is installed but not active"
            read -p "Would you like to activate WooCommerce? (y/n): " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                wp plugin activate woocommerce
                print_status "WooCommerce activated"
            fi
        fi
    else
        print_warning "WooCommerce is not installed"
        read -p "Would you like to install and activate WooCommerce? (y/n): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            wp plugin install woocommerce --activate
            print_status "WooCommerce installed and activated"
        fi
    fi
    
    # Check if our plugin is active
    if wp plugin is-active template-fixer-for-woocommerce 2>/dev/null; then
        print_status "Template Fixer for WooCommerce is active"
    else
        print_warning "Template Fixer for WooCommerce is not active"
        read -p "Would you like to activate the plugin? (y/n): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            wp plugin activate template-fixer-for-woocommerce
            print_status "Template Fixer for WooCommerce activated"
        fi
    fi
else
    print_warning "WordPress is not installed or not configured properly"
    print_info "Please ensure WordPress is properly installed and wp-config.php is configured"
fi

# Create development configuration file
print_info "Creating development configuration..."
cat > dev-config.json << EOF
{
    "plugin_name": "Template Fixer for WooCommerce",
    "plugin_slug": "template-fixer-for-woocommerce",
    "text_domain": "template-fixer-for-woocommerce",
    "php_version": "7.4",
    "wordpress_version": "6.0",
    "woocommerce_version": "7.0",
    "development": {
        "debug": true,
        "log_level": "debug",
        "test_mode": true
    },
    "testing": {
        "phpunit": true,
        "codeception": false,
        "e2e": false
    },
    "quality": {
        "phpcs": true,
        "psalm": true,
        "phpstan": false
    }
}
EOF
print_status "Development configuration created"

# Run initial quality checks
print_info "Running initial quality checks..."

echo "Checking coding standards..."
if composer run lint --quiet; then
    print_status "Coding standards check passed"
else
    print_warning "Coding standards issues found. Run 'composer run lint:fix' to auto-fix"
fi

echo "Running security analysis..."
if composer run security --quiet; then
    print_status "Security analysis passed"
else
    print_warning "Security issues found. Please review and fix"
fi

# Display summary
echo ""
echo "🎉 Development environment setup complete!"
echo ""
echo "📋 Next steps:"
echo "   1. Run 'composer run check-all' to run all quality checks"
echo "   2. Run 'composer run test' to run the test suite"
echo "   3. Start developing and testing your changes"
echo ""
echo "🔧 Available commands:"
echo "   composer run lint          - Check coding standards"
echo "   composer run lint:fix      - Fix coding standards issues"
echo "   composer run test          - Run tests"
echo "   composer run test:coverage - Run tests with coverage"
echo "   composer run security      - Run security analysis"
echo "   composer run check-all     - Run all quality checks"
echo ""
echo "📚 Documentation:"
echo "   - WORKFLOW.md - Complete development workflow"
echo "   - README.md   - Plugin documentation"
echo ""
print_status "Happy coding! 🚀"
