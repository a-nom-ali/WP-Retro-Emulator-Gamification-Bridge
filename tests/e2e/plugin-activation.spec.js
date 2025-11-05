/**
 * Plugin Activation Smoke Tests
 *
 * @package WP_Gamify_Bridge
 */

import { test, expect } from '@playwright/test';
import { wpLogin, isPluginActive, activatePlugin, navigateToPluginPage } from './helpers.js';

test.describe('Plugin Activation', () => {
  test.beforeEach(async ({ page }) => {
    // Login as admin before each test.
    await wpLogin(page);
  });

  test('plugin is installed and can be activated', async ({ page }) => {
    await page.goto('/wp-admin/plugins.php');

    // Check if plugin appears in list.
    const pluginRow = page.locator('tr:has-text("WP Retro Emulator Gamification Bridge")');
    await expect(pluginRow).toBeVisible();

    // Check plugin details.
    await expect(pluginRow.locator('.plugin-title strong')).toContainText('WP Retro Emulator Gamification Bridge');
    await expect(pluginRow.locator('.plugin-description')).toContainText('Bridges JavaScript-based retro game emulators');
  });

  test('plugin activates without errors', async ({ page }) => {
    // Ensure plugin is active.
    if (!await isPluginActive(page)) {
      await activatePlugin(page);
    }

    // Check for activation success.
    const pluginRow = page.locator('tr:has-text("WP Retro Emulator Gamification Bridge")');
    await expect(pluginRow.locator('.deactivate')).toBeVisible();

    // Check for no error notices.
    const errorNotice = page.locator('.notice-error');
    await expect(errorNotice).toHaveCount(0);
  });

  test('plugin admin menu appears after activation', async ({ page }) => {
    // Ensure plugin is active.
    if (!await isPluginActive(page)) {
      await activatePlugin(page);
    }

    await page.goto('/wp-admin/');

    // Check for admin menu item.
    const menuItem = page.locator('#adminmenu a:has-text("Gamify Bridge")');
    await expect(menuItem).toBeVisible();
  });

  test('plugin dashboard page loads', async ({ page }) => {
    // Ensure plugin is active.
    if (!await isPluginActive(page)) {
      await activatePlugin(page);
    }

    await navigateToPluginPage(page, 'dashboard');

    // Check page title.
    await expect(page.locator('h1')).toContainText('Gamify Bridge Dashboard');

    // Check for statistics cards.
    await expect(page.locator('.stat-card')).toHaveCount(4);

    // Check no error messages.
    const errorNotice = page.locator('.notice-error');
    await expect(errorNotice).toHaveCount(0);
  });

  test('database tables are created on activation', async ({ page }) => {
    // Ensure plugin is active.
    if (!await isPluginActive(page)) {
      await activatePlugin(page);
    }

    // Navigate to health check endpoint.
    const response = await page.request.get('/wp-json/gamify/v1/health');
    const health = await response.json();

    expect(response.ok()).toBeTruthy();
    expect(health.status).toBe('ok');
    expect(health.database.connected).toBe(true);
    expect(health.database.tables.events).toBe('exists');
    expect(health.database.tables.rooms).toBe('exists');
  });
});
