/**
 * Admin Dashboard Smoke Tests
 *
 * @package WP_Gamify_Bridge
 */

import { test, expect } from '@playwright/test';
import { wpLogin, navigateToPluginPage } from './helpers.js';

test.describe('Admin Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await wpLogin(page);
  });

  test('dashboard page displays statistics cards', async ({ page }) => {
    await navigateToPluginPage(page, 'dashboard');

    // Check for 4 stat cards.
    const statCards = page.locator('.stat-card');
    await expect(statCards).toHaveCount(4);

    // Check card titles.
    await expect(page.locator('.stat-card:has-text("Total Events")')).toBeVisible();
    await expect(page.locator('.stat-card:has-text("Active Rooms")')).toBeVisible();
    await expect(page.locator('.stat-card:has-text("Active Players")')).toBeVisible();
    await expect(page.locator('.stat-card:has-text("Events Today")')).toBeVisible();
  });

  test('dashboard displays event timeline chart', async ({ page }) => {
    await navigateToPluginPage(page, 'dashboard');

    // Wait for Chart.js to load.
    await page.waitForSelector('canvas#eventTimelineChart', { timeout: 10000 });

    const canvas = page.locator('canvas#eventTimelineChart');
    await expect(canvas).toBeVisible();
  });

  test('dashboard displays event types breakdown chart', async ({ page }) => {
    await navigateToPluginPage(page, 'dashboard');

    // Wait for Chart.js to load.
    await page.waitForSelector('canvas#eventTypesChart', { timeout: 10000 });

    const canvas = page.locator('canvas#eventTypesChart');
    await expect(canvas).toBeVisible();
  });

  test('dashboard displays recent events table', async ({ page }) => {
    await navigateToPluginPage(page, 'dashboard');

    const eventsTable = page.locator('h3:has-text("Recent Events")');
    await expect(eventsTable).toBeVisible();
  });

  test('rooms page loads and displays room list', async ({ page }) => {
    await navigateToPluginPage(page, 'rooms');

    await expect(page.locator('h1')).toContainText('Rooms');
    await expect(page.locator('form[action*="gamify_create_room"]')).toBeVisible();
  });

  test('rooms page can create new room', async ({ page }) => {
    await navigateToPluginPage(page, 'rooms');

    // Fill create room form.
    await page.fill('input[name="room_name"]', 'E2E Test Room');
    await page.fill('input[name="max_players"]', '10');

    // Submit form.
    await page.click('input[type="submit"][value="Create Room"]');

    // Wait for redirect.
    await page.waitForURL('**/admin.php?page=wp-gamify-bridge-rooms*', { timeout: 5000 });

    // Check for success message.
    await expect(page.locator('.notice-success')).toBeVisible();
    await expect(page.locator('.notice-success')).toContainText('Room created successfully');
  });

  test('event logs page displays events with filtering', async ({ page }) => {
    await navigateToPluginPage(page, 'event-logs');

    await expect(page.locator('h1')).toContainText('Event Logs');

    // Check filter form exists.
    await expect(page.locator('form')).toBeVisible();
    await expect(page.locator('select[name="filter_event_type"]')).toBeVisible();
  });

  test('leaderboard page displays tabs', async ({ page }) => {
    await navigateToPluginPage(page, 'leaderboard');

    await expect(page.locator('h1')).toContainText('Leaderboard');

    // Check for leaderboard tabs.
    await expect(page.locator('.nav-tab:has-text("By Events")')).toBeVisible();
    await expect(page.locator('.nav-tab:has-text("By GamiPress XP")')).toBeVisible();
    await expect(page.locator('.nav-tab:has-text("By MyCred Points")')).toBeVisible();
  });

  test('settings page loads and displays options', async ({ page }) => {
    await navigateToPluginPage(page, 'settings');

    await expect(page.locator('h1')).toContainText('Settings');

    // Check for settings form.
    await expect(page.locator('form[method="post"]')).toBeVisible();

    // Check for settings sections.
    await expect(page.locator('h2:has-text("General Settings")')).toBeVisible();
    await expect(page.locator('h2:has-text("System Information")')).toBeVisible();
  });

  test('event tester page allows triggering test events', async ({ page }) => {
    await navigateToPluginPage(page, 'tester');

    await expect(page.locator('h1')).toContainText('Event Tester');

    // Check for test event form.
    await expect(page.locator('form[action*="gamify_test_event"]')).toBeVisible();
    await expect(page.locator('select[name="event_type"]')).toBeVisible();
    await expect(page.locator('select[name="test_user_id"]')).toBeVisible();
  });

  test('submenu navigation works', async ({ page }) => {
    await navigateToPluginPage(page);

    // Check all submenu items are present.
    const submenuItems = page.locator('#adminmenu .wp-submenu a');

    await expect(submenuItems.filter({ hasText: 'Dashboard' })).toBeVisible();
    await expect(submenuItems.filter({ hasText: 'Rooms' })).toBeVisible();
    await expect(submenuItems.filter({ hasText: 'Event Logs' })).toBeVisible();
    await expect(submenuItems.filter({ hasText: 'Leaderboard' })).toBeVisible();
    await expect(submenuItems.filter({ hasText: 'Settings' })).toBeVisible();
    await expect(submenuItems.filter({ hasText: 'Event Tester' })).toBeVisible();
  });
});
