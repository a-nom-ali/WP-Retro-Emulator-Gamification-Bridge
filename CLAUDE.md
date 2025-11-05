# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WP Retro Emulator Gamification Bridge is a WordPress plugin that connects JavaScript-based retro game emulators (NES, SNES, GBA) with WordPress gamification systems (GamiPress, MyCred). It enables real-time XP, achievements, and room-based multiplayer events through REST API and WebSocket integrations.

## Development Commands

### Composer/PHP
```bash
# Install dependencies
composer install

# Run PHP CodeSniffer (linting)
composer run phpcs

# Auto-fix coding standards
composer run phpcbf

# Run PHPUnit tests
composer run test
```

### Standards
- Follows WordPress Coding Standards (WordPress-Core, WordPress-Docs)
- Text domain: `wp-gamify-bridge`
- Minimum PHP: 7.4+
- Minimum WordPress: 6.0+
- PHPCS config: `phpcs.xml`

### Git Commits
- **IMPORTANT**: Make regular commits using gitmoji specification for commit message format
- Commit frequently after completing discrete units of work
- Examples:
  - `✨ Add new event validation system`
  - `🐛 Fix nonce verification in REST endpoint`
  - `📝 Update API documentation`
  - `♻️ Refactor database query methods`
  - `✅ Add unit tests for room manager`
  - `🔒️ Improve security in event handler`

### Project Tracking
- **IMPORTANT**: Regularly update ROADMAP.md with progress as tasks are completed
- Check off completed items in the relevant phase
- Update the "Current Status" section when moving between phases
- Add any new tasks discovered during development to appropriate phases

## Architecture

### Core Plugin Flow

1. **Entry Point**: `wp-gamify-bridge.php`
   - Singleton pattern via `WP_Gamify_Bridge::instance()`
   - Loads all classes from `inc/` on `plugins_loaded`
   - Conditionally initializes gamification integrations (GamiPress/MyCred)

2. **Event Processing Pipeline**:
   ```
   JS Emulator → triggerWPEvent() → REST API /gamify/v1/event
       ↓
   WP_Gamify_Bridge_Endpoint (validation, auth)
       ↓
   WP_Gamify_Bridge_Database (event logging)
       ↓
   Gamification Integration (GamiPress/MyCred hooks)
       ↓
   WebSocket Broadcast (room notifications)
   ```

3. **Database Schema**:
   - `wp_gamify_events`: Event logs (event_type, user_id, room_id, score, event_data)
   - `wp_gamify_rooms`: Room metadata (room_id, name, max_players, is_active)

### Key Classes

- **WP_Gamify_Bridge_Database** (`inc/class-database.php`)
  - Manages database tables creation and queries
  - Methods: `log_event()`, `get_events()`, `create_tables()`

- **WP_Gamify_Bridge_Event_Validator** (`inc/class-event-validator.php`) 🆕
  - Comprehensive input validation for all event data
  - Validates event types, scores, user IDs, room IDs, and event data
  - Prevents abuse with max score (999999999) and max data size (10000 chars)
  - Returns WP_Error objects with HTTP status codes for failed validation
  - Extensible via filters: `wp_gamify_bridge_allowed_events`

- **WP_Gamify_Bridge_Rate_Limiter** (`inc/class-rate-limiter.php`) 🆕
  - Prevents API abuse with per-user rate limiting
  - Limits: 60 requests/minute, 500 requests/hour (configurable)
  - Uses transients for temporary storage (auto-expires)
  - Supports user whitelisting via filter: `wp_gamify_bridge_rate_limit_whitelist`
  - Methods: `check_rate_limit()`, `increment_counters()`, `get_rate_limit_status()`, `reset_counters()`

- **WP_Gamify_Bridge_Endpoint** (`inc/class-gamify-endpoint.php`)
  - REST API endpoints:
    - `POST /wp-json/gamify/v1/event` - Submit events (authenticated)
    - `GET /wp-json/gamify/v1/health` - Health check (public)
    - `GET /wp-json/gamify/v1/rate-limit` - Rate limit status (authenticated)
  - Integrates validator and rate limiter for security
  - Returns detailed error responses with proper HTTP status codes
  - Allowed event types: `level_complete`, `game_over`, `score_milestone`, `death`, `game_start`, `achievement_unlock`
  - Triggers action hooks:
    - `wp_gamify_bridge_gamipress_event`
    - `wp_gamify_bridge_mycred_event`
    - `wp_gamify_bridge_broadcast_event`
    - `wp_gamify_bridge_event_processed`

- **WP_Gamify_Bridge_Room_Manager** (`inc/class-room-manager.php`)
  - Manages room creation and retrieval
  - Provides `[retro_room id="X"]` shortcode
  - Enqueues room scripts with localized data (apiUrl, nonce, userId, userName)

- **WP_Gamify_Bridge_Script_Enqueuer** (`inc/class-script-enqueuer.php`)
  - Handles JavaScript/CSS asset loading

### JavaScript Bridge

- **emulator-hooks.js** (`js/emulator-hooks.js`) 🆕 Enhanced in Phase 2
  - **Core Functions:**
    - `window.WPGamifyBridge` - Main bridge object
    - `window.triggerWPEvent(eventType, eventData, options)` - Global shorthand
    - `window.wpGamifyStats()` - Debug statistics viewer

  - **Emulator Detection:** Automatically detects and hooks into:
    - JSNES (NES emulator)
    - GBA.js (Game Boy Advance)
    - RetroArch (multi-system emulator)
    - EmulatorJS (web-based emulator framework)

  - **Lifecycle Hooks:**
    - `onGameLoad(gameName, gameData)` - Game initialization
    - `onLevelComplete(level, score, time)` - Level completion with timing
    - `onGameOver(score, level, time)` - Game over with final stats
    - `onScoreMilestone(score, milestone)` - Score achievements
    - `onDeath(lives, level, cause)` - Player death events
    - `onGameStart(game, difficulty)` - Game start with difficulty
    - `onAchievementUnlock(achievement, description)` - Achievement unlocks

  - **Retry Logic:** 🆕
    - Exponential backoff (1s, 2s, 4s)
    - Max 3 retry attempts
    - Automatic retry for network errors (status 0) and server errors (5xx)
    - No retry for client errors (4xx, except rate limiting)

  - **Event Queue System:** 🆕
    - Offline mode support - events queued when network unavailable
    - localStorage persistence - queue survives page refreshes
    - Automatic processing when connection restored
    - Queue processing every 5 seconds
    - Max event age: 1 hour, max attempts: 5

  - **Network Monitoring:** 🆕
    - Real-time online/offline detection
    - Automatic queue processing when connection restored
    - Visual notifications for network status changes

  - **Debug Logging:** 🆕
    - Color-coded console output (info=blue, success=green, warning=orange, error=red)
    - Controlled by `config.debug` flag (set via WP_DEBUG)
    - Detailed event tracking with timestamps

  - **Statistics Tracking:** 🆕
    - `eventsSent` - Total events triggered
    - `eventsSuccess` - Successfully delivered events
    - `eventsFailed` - Failed events
    - `eventsRetried` - Retry attempts
    - `queueLength` - Current queue size
    - `emulatorType` - Detected emulator
    - `isOnline` - Network status

  - **Rate Limit Handling:** 🆕
    - Automatic detection of 429 responses
    - User-friendly error messages
    - Warning when < 10 requests remaining per minute
    - Response includes remaining quota in rate_limit object

- **room.js** (`js/room.js`)
  - WebSocket/real-time communication layer (implementation TBD)

### Gamification Integrations

Located in `inc/integrations/`:
- **gamipress.php**: Hooks into GamiPress events via `do_action('wp_gamify_bridge_gamipress_event', ...)`
- **mycred.php**: Hooks into MyCred points system via `do_action('wp_gamify_bridge_mycred_event', ...)`

Both are conditionally loaded only if their parent plugins are active.

## API Contract

### REST Endpoints

#### 1. Event Submission
```
POST /wp-json/gamify/v1/event
Headers: X-WP-Nonce: {wp_rest_nonce}
Auth: Required (logged-in user)

Request:
{
  "event": "level_complete",      // Required: one of allowed event types
  "player": "username",            // Optional: defaults to current user
  "room_id": "room-abc123",        // Optional: for room-scoped events
  "score": 1200,                   // Optional: numeric score value (max: 999999999)
  "data": { "level": 3 }          // Optional: arbitrary event metadata (max: 10000 chars)
}

Success Response (200):
{
  "success": true,
  "event_id": 123,
  "event_type": "level_complete",
  "reward": "XP awarded, Points awarded",
  "broadcast": true,
  "rate_limit": {
    "remaining_minute": 59,
    "remaining_hour": 499
  }
}

Error Responses:
- 400: Invalid event data (validation failed)
- 401: Not logged in
- 403: Invalid nonce
- 429: Rate limit exceeded
- 500: Database error
```

#### 2. Health Check
```
GET /wp-json/gamify/v1/health
Auth: Not required

Response (200):
{
  "status": "ok",
  "version": "0.1.0",
  "timestamp": "2025-01-05 00:00:00",
  "database": {
    "connected": true,
    "tables": {
      "events": "exists",
      "rooms": "exists"
    }
  },
  "integrations": {
    "gamipress": true,
    "mycred": false
  },
  "features": {
    "rate_limiting": true,
    "validation": true
  }
}
```

#### 3. Rate Limit Status
```
GET /wp-json/gamify/v1/rate-limit
Auth: Required (logged-in user)

Response (200):
{
  "user_id": 1,
  "status": {
    "requests_this_minute": 5,
    "requests_this_hour": 42,
    "minute_limit": 60,
    "hour_limit": 500,
    "minute_remaining": 55,
    "hour_remaining": 458
  }
}
```

### Event Types
When adding new event types, update `WP_Gamify_Bridge_Event_Validator::$allowed_events` in `inc/class-event-validator.php:20`

Alternatively, use the filter:
```php
add_filter( 'wp_gamify_bridge_allowed_events', function( $events ) {
    $events[] = 'custom_event_type';
    return $events;
} );
```

## Extension Points

### Action Hooks (for developers)
- `wp_gamify_bridge_gamipress_event` - Fired when event should award GamiPress rewards
  - Args: `$event_type`, `$user_id`, `$score`, `$data`
- `wp_gamify_bridge_mycred_event` - Fired when event should award MyCred points
  - Args: `$event_type`, `$user_id`, `$score`, `$data`
- `wp_gamify_bridge_broadcast_event` - Fired when event should be broadcast to room (WebSocket integration point)
  - Args: `$room_id`, `$event_type`, `$user_id`, `$response`
- `wp_gamify_bridge_event_processed` 🆕 - Fired after successful event processing
  - Args: `$log_id`, `$event_type`, `$user_id`, `$response`

### Filters
- `wp_gamify_bridge_allowed_events` 🆕 - Modify list of allowed event types
  - Args: `$allowed_events` (array)
  - Return: Modified array of allowed events
- `wp_gamify_bridge_rate_limiting_enabled` 🆕 - Enable/disable rate limiting
  - Args: `$enabled` (bool, default: true)
  - Return: Boolean
- `wp_gamify_bridge_rate_limit_whitelist` 🆕 - Whitelist users from rate limiting
  - Args: `$whitelisted_users` (array of user IDs)
  - Return: Modified array of user IDs

### Security Considerations
- All validation uses WP_Error for consistent error handling
- Rate limiting uses transients (no permanent database pollution)
- Nonce verification required for authenticated endpoints
- SQL injection prevention via $wpdb->prepare()
- Input sanitization on all user-provided data
- Maximum limits prevent abuse (score, data size)

## Development Workflow

1. **Adding New Event Types**:
   - Update allowed events array in `inc/class-event-validator.php::$allowed_events`
   - Add convenience method to `js/emulator-hooks.js` if needed
   - Update integration handlers in `inc/integrations/`

2. **Adding New Emulator Support**:
   - Add detection logic in `js/emulator-hooks.js::detectEmulator()`
   - Create `hookEmulatorName()` method
   - Hook into emulator lifecycle events

3. **Database Queries**:
   - Always use `$wpdb->prepare()` for SQL injection prevention
   - Use WordPress sanitization functions: `sanitize_text_field()`, `absint()`, `wp_json_encode()`

## JavaScript Usage Examples

### Basic Event Triggering
```javascript
// Simple event trigger
triggerWPEvent('level_complete', { level: 3, score: 1500 });

// Using the bridge object directly
WPGamifyBridge.onLevelComplete(3, 1500, 120); // level, score, time in seconds

// With options
triggerWPEvent('game_over', { score: 5000 }, {
    silent: true,  // Don't show notification
    timeout: 5000  // Custom timeout
});
```

### Checking Statistics (Debug Mode)
```javascript
// In browser console when WP_DEBUG is enabled
wpGamifyStats();

// Returns and displays:
// {
//   eventsSent: 15,
//   eventsSuccess: 14,
//   eventsFailed: 1,
//   eventsRetried: 2,
//   queueLength: 0,
//   emulatorType: 'JSNES',
//   isOnline: true
// }
```

### Manually Queuing Events
```javascript
// Force queue an event (useful for testing offline mode)
WPGamifyBridge.queueEvent({
    event: 'score_milestone',
    score: 10000,
    data: { milestone: '10k' }
});

// Check queue length
console.log(WPGamifyBridge.eventQueue.length);
```

### Custom Emulator Integration Example
```javascript
// In your emulator code:
function onPlayerDeath(lives, level) {
    WPGamifyBridge.onDeath(lives, level, 'enemy');
}

function onLevelCompleted(level, score, timeElapsed) {
    WPGamifyBridge.onLevelComplete(level, score, timeElapsed);
}

function onGameInitialized(gameName) {
    WPGamifyBridge.onGameLoad(gameName, {
        version: '1.0',
        region: 'US'
    });
}
```

## Project Status

**Current Phase:** Phase 3 (Gamification System Integration) - see ROADMAP.md for detailed phases.

**Completed Phases:**
- ✅ Phase 0: Foundation & Setup - Plugin skeleton complete
- ✅ Phase 1: Core REST API - Security, validation, rate limiting implemented
- ✅ Phase 2: Emulator Integration - JavaScript bridge with retry logic and offline support

Plugin is in active development (v0.1.0). WebSocket/real-time features and advanced room management are planned for future phases.
