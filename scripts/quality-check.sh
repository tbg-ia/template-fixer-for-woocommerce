#!/bin/bash

# Template Fixer for WooCommerce - Quality Check Script
# This script runs comprehensive quality checks before commits/releases

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
PASSED=0
FAILED=0
WARNINGS=0

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
    ((PASSED++))
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    ((WARNINGS++))
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
    ((FAILED++))
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_header() {
    echo ""
    echo -e "${BLUE}===================================================${NC}"
    echo -e "${BLUE} $1${NC}"
    echo -e "${BLUE}===================================================${NC}"
}

# Check if we're in the plugin directory
if [[ ! -f "template-fixer-for-woocommerce.php" ]]; then
    print_error "Please run this script from the plugin root directory"
    exit 1
fi

echo "🔍 Running comprehensive quality checks for Template Fixer for WooCommerce..."

# 1. PHP Syntax Check
print_header "PHP SYNTAX CHECK"
print_info "Checking PHP syntax in all files..."

find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*" | while read -r file; do
    if ! php -l "$file" > /dev/null 2>&1; then
        print_error "PHP syntax error in: $file"
        php -l "$file"
        exit 1
    fi
done

if [ $? -eq 0 ]; then
    print_status "All PHP files have valid syntax"
fi

# 2. WordPress Coding Standards
print_header "WORDPRESS CODING STANDARDS"
print_info "Running PHP CodeSniffer with WordPress standards..."

if composer run lint --quiet; then
    print_status "WordPress coding standards check passed"
else
    print_error "WordPress coding standards violations found"
    echo "Run 'composer run lint' to see details"
    echo "Run 'composer run lint:fix' to auto-fix issues"
fi

# 3. PHP Compatibility Check
print_header "PHP COMPATIBILITY CHECK"
print_info "Checking PHP 7.4+ compatibility..."

if phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4- --extensions=php --ignore=*/vendor/*,*/node_modules/* . --quiet; then
    print_status "PHP 7.4+ compatibility check passed"
else
    print_error "PHP compatibility issues found"
fi

# 4. Security Analysis
print_header "SECURITY ANALYSIS"
print_info "Running security checks..."

# Check for common security issues
echo "Checking for eval() usage..."
if grep -r "eval(" . --include="*.php" --exclude-dir=vendor --exclude-dir=node_modules; then
    print_error "Found eval() usage - potential security risk"
else
    print_status "No eval() usage found"
fi

echo "Checking for unescaped output..."
UNESCAPED_GET=$(grep -r "\$_GET\[" . --include="*.php" --exclude-dir=vendor --exclude-dir=node_modules | grep -v "esc_\|sanitize_" | wc -l)
if [ "$UNESCAPED_GET" -gt 0 ]; then
    print_warning "Found $UNESCAPED_GET potentially unescaped \$_GET variables"
else
    print_status "All \$_GET variables appear to be properly escaped"
fi

UNESCAPED_POST=$(grep -r "\$_POST\[" . --include="*.php" --exclude-dir=vendor --exclude-dir=node_modules | grep -v "esc_\|sanitize_" | wc -l)
if [ "$UNESCAPED_POST" -gt 0 ]; then
    print_warning "Found $UNESCAPED_POST potentially unescaped \$_POST variables"
else
    print_status "All \$_POST variables appear to be properly escaped"
fi

echo "Checking for hardcoded credentials..."
if grep -r -i "password.*=" . --include="*.php" --exclude-dir=vendor --exclude-dir=node_modules | grep -v "password_hash\|wp_hash_password\|'password'" | head -5; then
    print_warning "Potential hardcoded passwords found"
else
    print_status "No hardcoded passwords detected"
fi

# 5. Internationalization Check
print_header "INTERNATIONALIZATION CHECK"
print_info "Checking i18n implementation..."

# Check for text domain consistency
WRONG_DOMAIN=$(grep -r "__(\|_e(\|esc_html__(\|esc_html_e(" . --include="*.php" --exclude-dir=vendor --exclude-dir=node_modules | grep -v "template-fixer-for-woocommerce" | wc -l)
if [ "$WRONG_DOMAIN" -gt 0 ]; then
    print_warning "Found $WRONG_DOMAIN strings with incorrect or missing text domain"
else
    print_status "All translatable strings use correct text domain"
fi

# Check for translator comments
MISSING_COMMENTS=$(grep -r -B1 "__(\|esc_html__(" . --include="*.php" --exclude-dir=vendor --exclude-dir=node_modules | grep -A1 "%[ds]" | grep -v "translators:" | wc -l)
if [ "$MISSING_COMMENTS" -gt 0 ]; then
    print_warning "Some translatable strings with placeholders may be missing translator comments"
else
    print_status "Translator comments appear to be properly implemented"
fi

# 6. File Structure Check
print_header "FILE STRUCTURE CHECK"
print_info "Validating plugin file structure..."

# Check required files
REQUIRED_FILES=("template-fixer-for-woocommerce.php" "readme.txt" "includes/class-template-scanner.php" "includes/class-template-updater.php")
for file in "${REQUIRED_FILES[@]}"; do
    if [[ -f "$file" ]]; then
        print_status "Required file exists: $file"
    else
        print_error "Missing required file: $file"
    fi
done

# Check plugin header
if grep -q "Plugin Name:" template-fixer-for-woocommerce.php; then
    print_status "Plugin header found in main file"
else
    print_error "Plugin header missing in main file"
fi

# 7. WordPress Integration Check
print_header "WORDPRESS INTEGRATION CHECK"
print_info "Checking WordPress integration..."

# Check for WordPress functions usage
if grep -q "add_action\|add_filter" template-fixer-for-woocommerce.php; then
    print_status "WordPress hooks properly used"
else
    print_warning "No WordPress hooks found in main file"
fi

# Check for proper plugin initialization
if grep -q "class.*Template.*Fixer" includes/*.php; then
    print_status "Plugin classes found"
else
    print_error "No plugin classes found"
fi

# 8. WooCommerce Integration Check
print_header "WOOCOMMERCE INTEGRATION CHECK"
print_info "Checking WooCommerce integration..."

# Check for WooCommerce compatibility
if grep -q "WC requires at least" readme.txt; then
    print_status "WooCommerce version requirement specified"
else
    print_warning "WooCommerce version requirement not found in readme.txt"
fi

# Check for WooCommerce class usage
if grep -q "WC_\|woocommerce" includes/*.php; then
    print_status "WooCommerce integration found"
else
    print_error "No WooCommerce integration found"
fi

# 9. Documentation Check
print_header "DOCUMENTATION CHECK"
print_info "Checking documentation completeness..."

if [[ -f "README.md" ]]; then
    print_status "README.md exists"
else
    print_warning "README.md not found"
fi

if [[ -f "CHANGELOG.md" ]]; then
    print_status "CHANGELOG.md exists"
else
    print_warning "CHANGELOG.md not found"
fi

if [[ -f "WORKFLOW.md" ]]; then
    print_status "WORKFLOW.md exists"
else
    print_warning "WORKFLOW.md not found"
fi

# 10. Version Consistency Check
print_header "VERSION CONSISTENCY CHECK"
print_info "Checking version consistency across files..."

# Extract versions
PLUGIN_VERSION=$(grep "Version:" template-fixer-for-woocommerce.php | sed 's/.*Version: *//' | tr -d ' ')
README_VERSION=$(grep "Stable tag:" readme.txt | sed 's/.*Stable tag: *//' | tr -d ' ')

if [[ "$PLUGIN_VERSION" == "$README_VERSION" ]]; then
    print_status "Version consistency check passed ($PLUGIN_VERSION)"
else
    print_error "Version mismatch: Plugin ($PLUGIN_VERSION) vs README ($README_VERSION)"
fi

# Summary
print_header "QUALITY CHECK SUMMARY"
echo ""
echo -e "${GREEN}✅ Passed: $PASSED${NC}"
echo -e "${YELLOW}⚠️  Warnings: $WARNINGS${NC}"
echo -e "${RED}❌ Failed: $FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    if [ $WARNINGS -eq 0 ]; then
        echo -e "${GREEN}🎉 All quality checks passed! Plugin is ready for release.${NC}"
        exit 0
    else
        echo -e "${YELLOW}⚠️  Quality checks passed with warnings. Consider addressing warnings before release.${NC}"
        exit 0
    fi
else
    echo -e "${RED}❌ Quality checks failed. Please fix the issues before proceeding.${NC}"
    exit 1
fi
