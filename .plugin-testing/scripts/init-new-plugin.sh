#!/bin/bash

# Initialize a new plugin with the testing framework
# Usage: ./init-new-plugin.sh plugin-name

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
FRAMEWORK_DIR="$(dirname "$SCRIPT_DIR")"
ROOT_DIR="$(dirname "$FRAMEWORK_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if plugin name is provided
if [ -z "$1" ]; then
    echo -e "${RED}Error: Plugin name is required${NC}"
    echo "Usage: $0 plugin-name"
    exit 1
fi

PLUGIN_NAME="$1"
PLUGIN_DIR="$ROOT_DIR/$PLUGIN_NAME"

# Check if plugin directory already exists
if [ -d "$PLUGIN_DIR" ]; then
    echo -e "${RED}Error: Plugin directory already exists: $PLUGIN_DIR${NC}"
    exit 1
fi

echo -e "${YELLOW}Initializing new plugin: $PLUGIN_NAME${NC}"

# Create directory structure
echo "Creating directory structure..."
mkdir -p "$PLUGIN_DIR/includes/services"
mkdir -p "$PLUGIN_DIR/tests/Unit/Services"
mkdir -p "$PLUGIN_DIR/bin"

# Copy template files
echo "Copying template files..."

# Copy composer.json and adapt it
cp "$FRAMEWORK_DIR/templates/composer.json" "$PLUGIN_DIR/composer.json"
sed -i "" "s/plugin-name/$PLUGIN_NAME/g" "$PLUGIN_DIR/composer.json"

# Copy phpunit-unit.xml
cp "$FRAMEWORK_DIR/templates/phpunit-unit.xml" "$PLUGIN_DIR/phpunit-unit.xml"

# Copy bootstrap-unit.php
cp "$FRAMEWORK_DIR/templates/bootstrap-unit.php" "$PLUGIN_DIR/tests/bootstrap-unit.php"

# Copy TESTING.md
cp "$FRAMEWORK_DIR/templates/TESTING.md" "$PLUGIN_DIR/TESTING.md"

# Create bootstrap.php
cat > "$PLUGIN_DIR/tests/bootstrap.php" << 'EOF'
<?php
/**
 * Bootstrap for WordPress integration tests
 *
 * This file loads WordPress before running tests that need the full WordPress environment
 */

// Prevent errors from different WordPress versions
error_reporting(E_ALL);
ini_set('display_errors', '1');

// This would load WordPress if needed
// For now, we use unit tests which don't require full WordPress
require_once __DIR__ . '/bootstrap-unit.php';
EOF

# Create .phpunit.config
cat > "$PLUGIN_DIR/.phpunit.config" << 'EOF'
<?php
/**
 * PHPUnit Configuration for this plugin
 *
 * This file contains plugin-specific configuration for PHPUnit.
 * The main phpunit-unit.xml file is in the root of the plugin.
 */

// Plugin-specific configuration can go here if needed
EOF

# Copy template test
cp "$FRAMEWORK_DIR/templates/ProductServiceBasicTest.php" "$PLUGIN_DIR/tests/Unit/Services/ServiceBasicTest.php"

# Create a placeholder service class
cat > "$PLUGIN_DIR/includes/services/class-service.php" << 'EOF'
<?php

namespace BuyGoPlus\Services;

/**
 * Service class
 *
 * This is a placeholder service class.
 * Add your actual implementation here.
 */
class Service
{
    /**
     * Calculate total price
     *
     * @param array $items Array of items with 'price' and 'quantity'
     * @param float $discount Discount percentage (0-1)
     * @return float Total price
     */
    public function calculatePrice($items = [], $discount = 0)
    {
        if (empty($items)) {
            return 0;
        }

        $total = 0;
        foreach ($items as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        if ($discount > 0) {
            $total = $total * (1 - $discount);
        }

        return $total;
    }

    /**
     * Validate discount
     *
     * @param float $discount Discount percentage
     * @return bool
     */
    public function isValidDiscount($discount)
    {
        return $discount >= 0 && $discount <= 1;
    }

    /**
     * Format price
     *
     * @param float $price Price to format
     * @return string Formatted price
     */
    public function formatPrice($price)
    {
        return number_format($price, 2);
    }

    /**
     * Calculate average rating
     *
     * @param array $ratings Array of ratings
     * @return float Average rating
     */
    public function calculateAverageRating($ratings = [])
    {
        if (empty($ratings)) {
            return 0;
        }

        return array_sum($ratings) / count($ratings);
    }
}
EOF

# Initialize git (if not already a git repo)
if [ ! -d "$ROOT_DIR/.git" ]; then
    echo "Initializing git repository..."
    cd "$ROOT_DIR"
    git init
fi

# Install dependencies
echo "Installing dependencies..."
cd "$PLUGIN_DIR"
composer install

# Run tests to verify setup
echo "Running tests to verify setup..."
composer test

echo -e "${GREEN}✓ Plugin '$PLUGIN_NAME' initialized successfully!${NC}"
echo ""
echo "Next steps:"
echo "  1. cd $PLUGIN_DIR"
echo "  2. Edit includes/services/class-service.php with your actual code"
echo "  3. Update tests/Unit/Services/ServiceBasicTest.php with your tests"
echo "  4. Run: composer test"
echo ""
echo "For more information, see: $FRAMEWORK_DIR/docs/"
