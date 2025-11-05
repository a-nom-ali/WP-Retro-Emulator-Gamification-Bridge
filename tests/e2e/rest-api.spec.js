/**
 * REST API Smoke Tests
 *
 * @package WP_Gamify_Bridge
 */

import { test, expect } from '@playwright/test';
import { wpLogin, wpRestRequest } from './helpers.js';

test.describe('REST API Endpoints', () => {
  test.beforeEach(async ({ page }) => {
    // Login as admin to get nonce.
    await wpLogin(page);
    await page.goto('/wp-admin/');
  });

  test('health check endpoint returns OK', async ({ page }) => {
    const response = await page.request.get('/wp-json/gamify/v1/health');
    const data = await response.json();

    expect(response.ok()).toBeTruthy();
    expect(data.status).toBe('ok');
    expect(data).toHaveProperty('version');
    expect(data).toHaveProperty('database');
    expect(data).toHaveProperty('integrations');
    expect(data).toHaveProperty('features');
  });

  test('event endpoint requires authentication', async ({ page, context }) => {
    // Create new context without auth.
    const newPage = await context.newPage();

    const response = await newPage.request.post('/wp-json/gamify/v1/event', {
      data: {
        event: 'level_complete',
        score: 1000,
      },
    });

    expect(response.status()).toBe(401);
    await newPage.close();
  });

  test('event endpoint accepts valid event', async ({ page }) => {
    const response = await wpRestRequest(page, '/wp-json/gamify/v1/event', {
      method: 'POST',
      body: JSON.stringify({
        event: 'level_complete',
        score: 1000,
        data: { level: 5 },
      }),
    });

    const data = await response.json();

    expect(response.ok()).toBeTruthy();
    expect(data.success).toBe(true);
    expect(data).toHaveProperty('event_id');
    expect(data.event_type).toBe('level_complete');
  });

  test('event endpoint validates event type', async ({ page }) => {
    const response = await wpRestRequest(page, '/wp-json/gamify/v1/event', {
      method: 'POST',
      body: JSON.stringify({
        event: 'invalid_event_type',
        score: 1000,
      }),
    });

    expect(response.status()).toBe(400);

    const data = await response.json();
    expect(data.code).toBe('invalid_event');
  });

  test('event endpoint validates score range', async ({ page }) => {
    const response = await wpRestRequest(page, '/wp-json/gamify/v1/event', {
      method: 'POST',
      body: JSON.stringify({
        event: 'level_complete',
        score: -100, // Negative score.
      }),
    });

    expect(response.status()).toBe(400);
  });

  test('rate limit endpoint returns status', async ({ page }) => {
    const response = await wpRestRequest(page, '/wp-json/gamify/v1/rate-limit');
    const data = await response.json();

    expect(response.ok()).toBeTruthy();
    expect(data).toHaveProperty('user_id');
    expect(data).toHaveProperty('status');
    expect(data.status).toHaveProperty('requests_this_minute');
    expect(data.status).toHaveProperty('minute_limit');
    expect(data.status.minute_limit).toBe(60);
  });

  test('room endpoint lists rooms', async ({ page }) => {
    const response = await wpRestRequest(page, '/wp-json/gamify/v1/room');
    const data = await response.json();

    expect(response.ok()).toBeTruthy();
    expect(Array.isArray(data)).toBeTruthy();
  });

  test('room endpoint creates new room', async ({ page }) => {
    const response = await wpRestRequest(page, '/wp-json/gamify/v1/room', {
      method: 'POST',
      body: JSON.stringify({
        name: 'Test Room E2E',
        max_players: 10,
      }),
    });

    const data = await response.json();

    expect(response.ok()).toBeTruthy();
    expect(data.success).toBe(true);
    expect(data).toHaveProperty('room_id');
    expect(data.room_id).toMatch(/^room-/);
  });

  test('event endpoint supports all event types', async ({ page }) => {
    const eventTypes = [
      'level_complete',
      'game_over',
      'score_milestone',
      'death',
      'game_start',
      'achievement_unlock',
    ];

    for (const eventType of eventTypes) {
      const response = await wpRestRequest(page, '/wp-json/gamify/v1/event', {
        method: 'POST',
        body: JSON.stringify({
          event: eventType,
          score: 100,
        }),
      });

      const data = await response.json();

      expect(response.ok()).toBeTruthy();
      expect(data.success).toBe(true);
      expect(data.event_type).toBe(eventType);
    }
  });

  test('event endpoint includes rate limit info in response', async ({ page }) => {
    const response = await wpRestRequest(page, '/wp-json/gamify/v1/event', {
      method: 'POST',
      body: JSON.stringify({
        event: 'game_start',
        score: 0,
      }),
    });

    const data = await response.json();

    expect(response.ok()).toBeTruthy();
    expect(data).toHaveProperty('rate_limit');
    expect(data.rate_limit).toHaveProperty('remaining_minute');
    expect(data.rate_limit).toHaveProperty('remaining_hour');
  });
});
