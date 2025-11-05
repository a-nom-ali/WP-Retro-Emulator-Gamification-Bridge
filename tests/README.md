# Testing Guide for WP Retro Emulator Gamification Bridge

This document provides comprehensive instructions for running and writing tests for the WP Retro Emulator Gamification Bridge plugin.

## Table of Contents

1. [Overview](#overview)
2. [PHPUnit Tests](#phpunit-tests)
3. [Playwright E2E Tests](#playwright-e2e-tests)
4. [Test Environment Setup](#test-environment-setup)
5. [Running Tests](#running-tests)
6. [Writing New Tests](#writing-new-tests)
7. [Continuous Integration](#continuous-integration)
8. [Troubleshooting](#troubleshooting)

---

## Overview

The plugin has two types of tests:

- **PHPUnit Tests**: Unit and integration tests for PHP code
- **Playwright Tests**: End-to-end smoke tests for browser interactions

### Test Coverage

Current test coverage includes:

- Database operations (CRUD, queries, pagination)
- Event validation (types, scores, users, rooms)
- Rate limiting (counters, limits, whitelisting)
- Room management (create, join, leave, players, cleanup)
- REST API endpoints (events, rooms, health check)
- Admin dashboard (pages, forms, charts)
- JavaScript bridge (WPGamifyBridge, event triggering)
- Plugin activation and deactivation

---

## PHPUnit Tests

### Prerequisites

1. **WordPress Test Suite**

   Install the WordPress test suite:

   ```bash
   # Install WordPress test library
   bash bin/install-wp-tests.sh wordpress_test root 'password' localhost latest
   ```

   Or manually set up:
   ```bash
   # Clone WordPress develop repo
   svn co https://develop.svn.wordpress.org/trunk/ /tmp/wordpress-develop

   # Set environment variable
   export WP_TESTS_DIR=/tmp/wordpress-develop/tests/phpunit
   export WP_CORE_DIR=/tmp/wordpress-develop/src/
   ```

2. **Composer Dependencies**

   ```bash
   composer install
   ```

### Test Structure

```
tests/
├── bootstrap.php                    # PHPUnit bootstrap file
├── class-wp-gamify-bridge-test-case.php  # Base test case class
├── test-database.php                # Database tests
├── test-event-validator.php         # Validator tests
├── test-rate-limiter.php            # Rate limiter tests
├── test-room-manager.php            # Room manager tests
└── test-gamipress-integration.php   # GamiPress integration tests
```

### Running PHPUnit Tests

**Run all tests:**
```bash
vendor/bin/phpunit
```

**Run specific test file:**
```bash
vendor/bin/phpunit tests/test-database.php
```

**Run specific test:**
```bash
vendor/bin/phpunit --filter test_log_event
```

**Run with coverage:**
```bash
vendor/bin/phpunit --coverage-html tests/coverage
```

Then open `tests/coverage/index.html` in your browser.

### Environment Variables

Set these in `.env` or export before running tests:

```bash
export WP_PHPUNIT__DIR=/path/to/wordpress-develop/tests/phpunit
export WP_TESTS_DIR=/path/to/wordpress-develop/tests/phpunit
export WP_CORE_DIR=/path/to/wordpress-develop/src/
```

---

## Playwright E2E Tests

### Prerequisites

1. **Node.js and npm** (v18 or higher)

2. **Install Playwright:**

   ```bash
   npm init -y  # If package.json doesn't exist
   npm install --save-dev @playwright/test
   npx playwright install chromium
   ```

3. **WordPress Installation**

   You need a working WordPress installation with the plugin installed.

### Test Structure

```
tests/e2e/
├── helpers.js                       # Helper functions (login, navigation, etc.)
├── plugin-activation.spec.js        # Plugin activation tests
├── rest-api.spec.js                 # REST API tests
├── admin-dashboard.spec.js          # Admin dashboard tests
└── javascript-bridge.spec.js        # JavaScript bridge tests
```

### Configuration

Edit `playwright.config.js` to set your WordPress URL:

```javascript
use: {
  baseURL: process.env.WP_BASE_URL || 'http://campaign-forge.local',
  // ...
}
```

Or set environment variable:

```bash
export WP_BASE_URL=http://your-wordpress-site.local
```

### Running Playwright Tests

**Run all tests:**
```bash
npx playwright test
```

**Run specific test file:**
```bash
npx playwright test tests/e2e/plugin-activation.spec.js
```

**Run in headed mode (see browser):**
```bash
npx playwright test --headed
```

**Run in UI mode (interactive):**
```bash
npx playwright test --ui
```

**Run specific browser:**
```bash
npx playwright test --project=chromium
npx playwright test --project=firefox
npx playwright test --project=webkit
```

**Debug tests:**
```bash
npx playwright test --debug
```

**View test report:**
```bash
npx playwright show-report tests/e2e-reports
```

### WordPress Credentials

Default credentials in helpers.js:
- Username: `admin`
- Password: `password`

To use different credentials, modify `tests/e2e/helpers.js` or set environment variables:

```bash
export WP_ADMIN_USER=your_username
export WP_ADMIN_PASS=your_password
```

---

## Test Environment Setup

### Docker Environment (Recommended)

1. **Create docker-compose.yml:**

```yaml
version: '3.8'
services:
  wordpress:
    image: wordpress:latest
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress
    volumes:
      - ./:/var/www/html/wp-content/plugins/wp-retro-emulator-gamification-bridge

  db:
    image: mysql:5.7
    environment:
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
      MYSQL_ROOT_PASSWORD: rootpassword
    volumes:
      - db_data:/var/lib/mysql

volumes:
  db_data:
```

2. **Start environment:**

```bash
docker-compose up -d
```

3. **Access WordPress:**

Navigate to `http://localhost:8080` and complete setup.

### Local Environment

Ensure you have:
- WordPress 6.0+
- PHP 7.4+
- MySQL 5.7+
- Plugin activated
- Admin user created

---

## Writing New Tests

### Writing PHPUnit Tests

1. **Create test file** in `tests/` directory:

```php
<?php
require_once __DIR__ . '/class-wp-gamify-bridge-test-case.php';

class Test_My_Feature extends WP_Gamify_Bridge_Test_Case {

    public function test_my_feature() {
        $user_id = $this->create_test_user();
        $room_id = $this->create_test_room();

        // Your test code here
        $this->assertTrue( true );
    }
}
```

2. **Use base test case helpers:**

- `$this->create_test_user( $role )` - Create test user
- `$this->create_test_room( $args )` - Create test room
- `$this->factory` - WordPress factory for creating test data

3. **Run your test:**

```bash
vendor/bin/phpunit tests/test-my-feature.php
```

### Writing Playwright Tests

1. **Create test file** in `tests/e2e/` directory:

```javascript
import { test, expect } from '@playwright/test';
import { wpLogin, navigateToPluginPage } from './helpers.js';

test.describe('My Feature', () => {
  test.beforeEach(async ({ page }) => {
    await wpLogin(page);
  });

  test('my feature works', async ({ page }) => {
    await navigateToPluginPage(page, 'dashboard');
    await expect(page.locator('h1')).toBeVisible();
  });
});
```

2. **Use helper functions:**

- `wpLogin(page, username, password)` - Login to WordPress
- `wpLogout(page)` - Logout from WordPress
- `navigateToPluginPage(page, subpage)` - Navigate to plugin page
- `wpRestRequest(page, endpoint, options)` - Make REST API request with nonce
- `isPluginActive(page)` - Check if plugin is active
- `activatePlugin(page)` - Activate the plugin

3. **Run your test:**

```bash
npx playwright test tests/e2e/my-feature.spec.js
```

---

## Continuous Integration

### GitHub Actions Example

Create `.github/workflows/tests.yml`:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  phpunit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - name: Install dependencies
        run: composer install
      - name: Setup WordPress test suite
        run: bash bin/install-wp-tests.sh wordpress_test root root localhost latest
      - name: Run PHPUnit
        run: vendor/bin/phpunit

  playwright:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup Node.js
        uses: actions/setup-node@v2
        with:
          node-version: '18'
      - name: Install dependencies
        run: npm ci
      - name: Install Playwright
        run: npx playwright install --with-deps
      - name: Setup WordPress
        run: |
          docker-compose up -d
          sleep 30
      - name: Run Playwright tests
        run: npx playwright test
      - name: Upload test results
        uses: actions/upload-artifact@v2
        if: always()
        with:
          name: playwright-report
          path: tests/e2e-reports/
```

---

## Troubleshooting

### PHPUnit Issues

**"Class 'WP_UnitTestCase' not found"**

- Ensure `WP_PHPUNIT__DIR` environment variable is set correctly
- Verify WordPress test suite is installed
- Run `bash bin/install-wp-tests.sh` again

**"Database connection failed"**

- Check MySQL is running
- Verify database credentials in test setup
- Ensure test database exists

**"Cannot modify header information"**

- This is usually safe to ignore in tests
- Add `@runInSeparateProcess` annotation to test if needed

### Playwright Issues

**"Target closed" or "Browser closed"**

- Increase timeout: `test.setTimeout(60000);`
- Check if WordPress URL is accessible
- Verify WordPress is running

**"Element not found"**

- Add explicit waits: `await page.waitForSelector('.selector')`
- Check element selector is correct
- Use Playwright Inspector: `npx playwright test --debug`

**"Authentication failed"**

- Verify WordPress admin credentials
- Check if login URL is correct
- Ensure nonce is being retrieved correctly

**"Tests fail on CI but pass locally"**

- Check WordPress setup in CI
- Ensure plugin is activated
- Verify environment variables are set
- Add longer timeouts for CI

### General Issues

**"Permission denied"**

```bash
chmod +x bin/install-wp-tests.sh
```

**"Out of memory"**

Increase PHP memory limit:
```bash
php -d memory_limit=512M vendor/bin/phpunit
```

**"Port already in use"**

Change port in `docker-compose.yml` or playwright config.

---

## Test Data Cleanup

Tests automatically clean up test data, but you can manually clean:

```bash
# Clean WordPress test database
mysql -u root -p -e "DROP DATABASE IF EXISTS wordpress_test; CREATE DATABASE wordpress_test;"

# Clean transients
wp transient delete --all

# Clean test rooms
wp db query "DELETE FROM wp_gamify_rooms WHERE name LIKE 'E2E Test%'"
```

---

## Best Practices

### PHPUnit

1. **Isolation**: Each test should be independent
2. **Cleanup**: Use `tear_down()` to clean up test data
3. **Assertions**: Use specific assertions (`assertEquals` over `assertTrue`)
4. **Naming**: Test names should describe what they test
5. **Data Providers**: Use data providers for testing multiple inputs

### Playwright

1. **Selectors**: Use stable selectors (data attributes, roles)
2. **Waits**: Use explicit waits, avoid arbitrary timeouts
3. **Page Objects**: Create page objects for complex pages
4. **Screenshots**: Take screenshots on failure for debugging
5. **Isolation**: Each test should start from a clean state

---

## Code Coverage Goals

Target coverage levels:
- **Overall**: 80%+
- **Core Classes**: 90%+
- **Integration Classes**: 70%+
- **Admin Classes**: 60%+

Generate coverage report:
```bash
vendor/bin/phpunit --coverage-html tests/coverage
```

---

## Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Playwright Documentation](https://playwright.dev/)
- [WordPress PHPUnit Guide](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [WordPress Test Suite](https://develop.svn.wordpress.org/trunk/)

---

## Support

For issues or questions:
- Open an issue on GitHub
- Check existing tests for examples
- Review troubleshooting section above

---

**Last Updated:** 2025-01-05
