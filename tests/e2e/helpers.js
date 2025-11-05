/**
 * Helper functions for Playwright E2E tests
 *
 * @package WP_Gamify_Bridge
 */

/**
 * WordPress admin login helper.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @param {string} username - WordPress username.
 * @param {string} password - WordPress password.
 */
export async function wpLogin(page, username = 'admin', password = 'password') {
  await page.goto('/wp-login.php');

  // Fill login form.
  await page.fill('#user_login', username);
  await page.fill('#user_pass', password);

  // Submit form.
  await page.click('#wp-submit');

  // Wait for redirect to dashboard.
  await page.waitForURL('**/wp-admin/**', { timeout: 10000 });
}

/**
 * WordPress admin logout helper.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 */
export async function wpLogout(page) {
  await page.goto('/wp-login.php?action=logout');
  await page.click('a'); // Click logout confirmation link.
  await page.waitForURL('**/wp-login.php*loggedout=true*');
}

/**
 * Navigate to plugin admin page.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @param {string} subpage - Subpage slug (e.g., 'dashboard', 'rooms', 'settings').
 */
export async function navigateToPluginPage(page, subpage = '') {
  const pageParam = subpage ? `&page=wp-gamify-bridge-${subpage}` : '';
  await page.goto(`/wp-admin/admin.php?page=wp-gamify-bridge${pageParam}`);
}

/**
 * Check if plugin is active.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @returns {Promise<boolean>} True if plugin is active.
 */
export async function isPluginActive(page) {
  await page.goto('/wp-admin/plugins.php');

  const pluginRow = page.locator('tr[data-slug="wp-retro-emulator-gamification-bridge"], tr:has-text("WP Retro Emulator Gamification Bridge")');

  if (await pluginRow.count() === 0) {
    return false;
  }

  const isActive = await pluginRow.locator('.deactivate').count() > 0;
  return isActive;
}

/**
 * Activate plugin.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 */
export async function activatePlugin(page) {
  await page.goto('/wp-admin/plugins.php');

  const pluginRow = page.locator('tr[data-slug="wp-retro-emulator-gamification-bridge"], tr:has-text("WP Retro Emulator Gamification Bridge")');
  const activateLink = pluginRow.locator('.activate a');

  if (await activateLink.count() > 0) {
    await activateLink.click();
    await page.waitForURL('**/plugins.php?*activate=true*');
  }
}

/**
 * Create a REST API request with WordPress nonce.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @param {string} endpoint - REST API endpoint (e.g., '/wp-json/gamify/v1/event').
 * @param {object} options - Fetch options (method, body, etc.).
 * @returns {Promise<Response>} Fetch response.
 */
export async function wpRestRequest(page, endpoint, options = {}) {
  // Get nonce from page.
  const nonce = await page.evaluate(() => {
    return window.wpApiSettings?.nonce || '';
  });

  const defaultOptions = {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
  };

  const mergedOptions = {
    ...defaultOptions,
    ...options,
    headers: {
      ...defaultOptions.headers,
      ...(options.headers || {}),
    },
  };

  return await page.request.fetch(endpoint, mergedOptions);
}

/**
 * Wait for element to be visible with custom timeout.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @param {string} selector - Element selector.
 * @param {number} timeout - Timeout in milliseconds.
 */
export async function waitForVisible(page, selector, timeout = 5000) {
  await page.waitForSelector(selector, { state: 'visible', timeout });
}

/**
 * Get WordPress environment info.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @returns {Promise<object>} Environment info.
 */
export async function getWpEnvironment(page) {
  return await page.evaluate(() => {
    return {
      ajaxUrl: window.ajaxurl || '',
      restUrl: window.wpApiSettings?.root || '',
      nonce: window.wpApiSettings?.nonce || '',
    };
  });
}
