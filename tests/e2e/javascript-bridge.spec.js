/**
 * JavaScript Bridge Smoke Tests
 *
 * @package WP_Gamify_Bridge
 */

import { test, expect } from '@playwright/test';
import { wpLogin } from './helpers.js';

test.describe('JavaScript Bridge', () => {
  test.beforeEach(async ({ page }) => {
    await wpLogin(page);
    // Navigate to any frontend page where scripts are loaded.
    await page.goto('/');
  });

  test('WPGamifyBridge object is available', async ({ page }) => {
    const bridgeExists = await page.evaluate(() => {
      return typeof window.WPGamifyBridge !== 'undefined';
    });

    expect(bridgeExists).toBeTruthy();
  });

  test('triggerWPEvent function is available', async ({ page }) => {
    const functionExists = await page.evaluate(() => {
      return typeof window.triggerWPEvent === 'function';
    });

    expect(functionExists).toBeTruthy();
  });

  test('WPGamifyBridge has required methods', async ({ page }) => {
    const methods = await page.evaluate(() => {
      const bridge = window.WPGamifyBridge;
      return {
        init: typeof bridge.init === 'function',
        sendEvent: typeof bridge.sendEvent === 'function',
        queueEvent: typeof bridge.queueEvent === 'function',
        processQueue: typeof bridge.processQueue === 'function',
        onGameLoad: typeof bridge.onGameLoad === 'function',
        onLevelComplete: typeof bridge.onLevelComplete === 'function',
        onGameOver: typeof bridge.onGameOver === 'function',
        onScoreMilestone: typeof bridge.onScoreMilestone === 'function',
        onDeath: typeof bridge.onDeath === 'function',
      };
    });

    expect(methods.init).toBeTruthy();
    expect(methods.sendEvent).toBeTruthy();
    expect(methods.queueEvent).toBeTruthy();
    expect(methods.processQueue).toBeTruthy();
    expect(methods.onGameLoad).toBeTruthy();
    expect(methods.onLevelComplete).toBeTruthy();
    expect(methods.onGameOver).toBeTruthy();
    expect(methods.onScoreMilestone).toBeTruthy();
    expect(methods.onDeath).toBeTruthy();
  });

  test('WPGamifyBridge config is set', async ({ page }) => {
    const config = await page.evaluate(() => {
      return window.WPGamifyBridge?.config || null;
    });

    expect(config).not.toBeNull();
    expect(config).toHaveProperty('restUrl');
    expect(config).toHaveProperty('nonce');
  });

  test('event queue exists', async ({ page }) => {
    const queueExists = await page.evaluate(() => {
      return Array.isArray(window.WPGamifyBridge?.eventQueue);
    });

    expect(queueExists).toBeTruthy();
  });

  test('wpGamifyStats debug function is available', async ({ page }) => {
    const statsExists = await page.evaluate(() => {
      return typeof window.wpGamifyStats === 'function';
    });

    expect(statsExists).toBeTruthy();
  });

  test('can trigger game_start event', async ({ page }) => {
    const result = await page.evaluate(async () => {
      try {
        window.WPGamifyBridge.onGameStart('Test Game', 'normal');
        return { success: true };
      } catch (error) {
        return { success: false, error: error.message };
      }
    });

    expect(result.success).toBeTruthy();
  });

  test('can trigger level_complete event', async ({ page }) => {
    const result = await page.evaluate(async () => {
      try {
        window.WPGamifyBridge.onLevelComplete(1, 1000, 60);
        return { success: true };
      } catch (error) {
        return { success: false, error: error.message };
      }
    });

    expect(result.success).toBeTruthy();
  });

  test('event statistics are tracked', async ({ page }) => {
    // Trigger a couple of events.
    await page.evaluate(() => {
      window.WPGamifyBridge.onGameStart('Test Game');
      window.WPGamifyBridge.onLevelComplete(1, 100, 30);
    });

    // Wait a bit for events to process.
    await page.waitForTimeout(1000);

    const stats = await page.evaluate(() => {
      return {
        eventsSent: window.WPGamifyBridge.eventsSent,
        eventsSuccess: window.WPGamifyBridge.eventsSuccess,
        eventsFailed: window.WPGamifyBridge.eventsFailed,
      };
    });

    expect(stats.eventsSent).toBeGreaterThan(0);
  });

  test('network monitoring detects online status', async ({ page }) => {
    const isOnline = await page.evaluate(() => {
      return window.WPGamifyBridge?.isOnline;
    });

    expect(isOnline).toBe(true);
  });

  test('emulator detection methods exist', async ({ page }) => {
    const detectionMethods = await page.evaluate(() => {
      const bridge = window.WPGamifyBridge;
      return {
        detectEmulator: typeof bridge.detectEmulator === 'function',
        hookJSNES: typeof bridge.hookJSNES === 'function',
        hookGBA: typeof bridge.hookGBA === 'function',
        hookRetroArch: typeof bridge.hookRetroArch === 'function',
      };
    });

    expect(detectionMethods.detectEmulator).toBeTruthy();
    expect(detectionMethods.hookJSNES).toBeTruthy();
    expect(detectionMethods.hookGBA).toBeTruthy();
    expect(detectionMethods.hookRetroArch).toBeTruthy();
  });
});
