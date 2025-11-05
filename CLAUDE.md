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

### Gamification Integrations 🆕 Enhanced in Phase 3

Located in `inc/integrations/`:

- **WP_Gamify_Bridge_GamiPress** (`inc/integrations/gamipress.php`)
  - **Lines**: 424 (enhanced from 105-line skeleton)
  - **Purpose**: Complete GamiPress integration with intelligent XP awards
  - **XP Multiplier System**:
    - Score bonus: 1 XP per 100 game points
    - Level multiplier: 10% bonus per level (e.g., level 5 = 50% bonus)
    - Difficulty multipliers: easy (1.0x), normal (1.5x), hard (2.0x), expert (3.0x)
    - Speed bonus: 50% extra XP for completing levels in <60 seconds
  - **Custom Point Types**:
    - `arcade_tokens` - "Arcade Token" / "Arcade Tokens"
    - `game_coins` - "Game Coin" / "Game Coins"
  - **Custom Triggers**: Registered with GamiPress
    - `wp_gamify_game_start` - Start a game
    - `wp_gamify_level_complete` - Complete a level (with conditional requirements)
    - `wp_gamify_game_over` - Game over (with conditional requirements)
    - `wp_gamify_score_milestone` - Reach score milestone (with conditional requirements)
    - `wp_gamify_achievement_unlock` - Unlock achievement
    - `wp_gamify_death` - Player death
  - **Achievement System**:
    - Score achievements: 10k, 50k, 100k
    - Level achievements: level 10, 25, 50
    - Speed achievements: <30s level completions
    - Extensible via `wp_gamify_bridge_gamipress_achievements` filter
  - **Rank System**: 7 tiers based on XP
    - 0 XP: Beginner
    - 100 XP: Casual Player
    - 500 XP: Regular Gamer
    - 1000 XP: Skilled Player
    - 2000 XP: Pro Gamer
    - 5000 XP: Gaming Master
    - 10000 XP: Arcade Legend
  - **Activity Logging**: Human-readable activity entries for GamiPress reports
  - **Key Methods**:
    - `handle_event($event_type, $user_id, $score, $data)` - Main event handler
    - `award_points($event_type, $user_id, $score, $data)` - XP awards with multipliers
    - `check_achievements($event_type, $user_id, $score, $data)` - Achievement checking
    - `get_user_total_xp($user_id)` - Get user's XP balance
    - `get_user_rank($user_id)` - Get user's rank title
  - **Filters**:
    - `wp_gamify_bridge_gamipress_point_type` - Customize point type (default: 'points')
    - `wp_gamify_bridge_gamipress_xp_award` - Modify XP amount before award
    - `wp_gamify_bridge_gamipress_achievements` - Modify achievements list
  - **Actions**:
    - `wp_gamify_bridge_gamipress_xp_awarded` - After XP awarded
    - `wp_gamify_bridge_award_achievement` - When achievement earned

- **WP_Gamify_Bridge_MyCred** (`inc/integrations/mycred.php`)
  - **Lines**: 463 (enhanced from 109-line skeleton)
  - **Purpose**: Complete MyCred integration with intelligent point awards
  - **Points Multiplier System** (same as GamiPress + streak bonus):
    - Score bonus: 1 point per 100 game points
    - Level bonus: 5 points per level (e.g., level 5 = +25 points)
    - Difficulty multipliers: easy (1.0x), normal (1.5x), hard (2.0x), expert (3.0x)
    - Speed bonus: 50% extra points for completing levels in <60 seconds
    - Streak bonus: 5% per streak level, max 50% at 10 streak
  - **Custom Hook**: Registered with MyCred
    - Hook ID: `retro_emulator`
    - Title: "Retro Emulator Events"
    - Description: "Award points for retro game events"
  - **Rank Progression System**:
    - Automatic rank updates based on point balance
    - Same 7-tier system as GamiPress (Beginner → Arcade Legend)
    - Uses MyCred's native rank functions when available
  - **Badge System**:
    - Score badges: score_achiever (10k), score_champion (50k), high_score_master (100k)
    - Level badges: level_expert (25), level_master (50)
    - Simplified badge storage using user meta
    - Extensible via `wp_gamify_bridge_mycred_badges` filter
  - **Descriptive Log Entries**: Human-readable point award reasons
    - "Completed level 3 with score 1500"
    - "Game over - Final score: 5000"
    - "Reached score milestone: 10000 points"
  - **Key Methods**:
    - `handle_event($event_type, $user_id, $score, $data)` - Main event handler
    - `award_points($event_type, $user_id, $score, $data)` - Points with multipliers
    - `check_rank_progression($user_id)` - Automatic rank updates
    - `check_badges($event_type, $user_id, $score, $data)` - Badge checking
    - `get_user_total_points($user_id)` - Get user's point balance
    - `get_user_rank($user_id)` - Get user's rank slug
    - `get_user_rank_title($user_id)` - Get user's rank display name
  - **Filters**:
    - `wp_gamify_bridge_mycred_point_type` - Customize point type (default: 'mycred_default')
    - `wp_gamify_bridge_mycred_points_award` - Modify points amount before award
    - `wp_gamify_bridge_mycred_badges` - Modify badges list
  - **Actions**:
    - `wp_gamify_bridge_mycred_points_awarded` - After points awarded
    - `wp_gamify_bridge_mycred_rank_changed` - When rank changes
    - `wp_gamify_bridge_mycred_badge_awarded` - When badge earned
    - `wp_gamify_bridge_mycred_activity_logged` - After activity logged

**Both Integrations**:
- Singleton pattern for memory efficiency
- Conditionally loaded only if parent plugin is active
- Hooked to `wp_gamify_bridge_gamipress_event` and `wp_gamify_bridge_mycred_event` actions
- Work independently - can run both, either, or neither
- Extensible via WordPress hooks and filters
- Support for custom point/achievement tracking
- Intelligent multiplier systems that consider context (score, level, difficulty, time, streak)

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

**Core Event Actions**:
- `wp_gamify_bridge_gamipress_event` - Fired when event should award GamiPress rewards
  - Args: `$event_type`, `$user_id`, `$score`, `$data`
- `wp_gamify_bridge_mycred_event` - Fired when event should award MyCred points
  - Args: `$event_type`, `$user_id`, `$score`, `$data`
- `wp_gamify_bridge_broadcast_event` - Fired when event should be broadcast to room (WebSocket integration point)
  - Args: `$room_id`, `$event_type`, `$user_id`, `$response`
- `wp_gamify_bridge_event_processed` 🆕 - Fired after successful event processing
  - Args: `$log_id`, `$event_type`, `$user_id`, `$response`

**GamiPress Actions** 🆕 (Phase 3):
- `wp_gamify_bridge_gamipress_xp_awarded` - After XP awarded to user
  - Args: `$user_id`, `$xp`, `$event_type`, `$data`
- `wp_gamify_bridge_award_achievement` - When achievement earned
  - Args: `$user_id`, `$achievement` (achievement slug)
- Custom GamiPress triggers (for GamiPress requirements):
  - `wp_gamify_game_start` - Args: `$user_id`, `$score`, `$data`
  - `wp_gamify_level_complete` - Args: `$user_id`, `$score`, `$data`
  - `wp_gamify_game_over` - Args: `$user_id`, `$score`, `$data`
  - `wp_gamify_score_milestone` - Args: `$user_id`, `$score`, `$data`
  - `wp_gamify_achievement_unlock` - Args: `$user_id`, `$score`, `$data`
  - `wp_gamify_death` - Args: `$user_id`, `$score`, `$data`

**MyCred Actions** 🆕 (Phase 3):
- `wp_gamify_bridge_mycred_points_awarded` - After points awarded to user
  - Args: `$user_id`, `$points`, `$event_type`, `$data`
- `wp_gamify_bridge_mycred_rank_changed` - When user's rank changes
  - Args: `$user_id`, `$old_rank`, `$new_rank`
- `wp_gamify_bridge_mycred_badge_awarded` - When badge earned
  - Args: `$user_id`, `$badge_slug`
- `wp_gamify_bridge_mycred_activity_logged` - After activity logged
  - Args: `$user_id`, `$event_type`, `$score`, `$data`

### Filters

**Core Filters**:
- `wp_gamify_bridge_allowed_events` 🆕 - Modify list of allowed event types
  - Args: `$allowed_events` (array)
  - Return: Modified array of allowed events
- `wp_gamify_bridge_rate_limiting_enabled` 🆕 - Enable/disable rate limiting
  - Args: `$enabled` (bool, default: true)
  - Return: Boolean
- `wp_gamify_bridge_rate_limit_whitelist` 🆕 - Whitelist users from rate limiting
  - Args: `$whitelisted_users` (array of user IDs)
  - Return: Modified array of user IDs

**GamiPress Filters** 🆕 (Phase 3):
- `wp_gamify_bridge_gamipress_point_type` - Customize point type used
  - Args: `$point_type` (string, default: 'points')
  - Return: Point type slug
- `wp_gamify_bridge_gamipress_xp_award` - Modify XP amount before awarding
  - Args: `$xp`, `$event_type`, `$user_id`, `$score`, `$data`
  - Return: Modified XP amount (integer)
- `wp_gamify_bridge_gamipress_achievements` - Modify achievements to award
  - Args: `$achievements` (array), `$event_type`, `$score`, `$data`
  - Return: Modified array of achievement slugs

**MyCred Filters** 🆕 (Phase 3):
- `wp_gamify_bridge_mycred_point_type` - Customize point type used
  - Args: `$point_type` (string, default: 'mycred_default')
  - Return: Point type slug
- `wp_gamify_bridge_mycred_points_award` - Modify points amount before awarding
  - Args: `$points`, `$event_type`, `$user_id`, `$score`, `$data`
  - Return: Modified points amount (integer)
- `wp_gamify_bridge_mycred_badges` - Modify badges to award
  - Args: `$badges` (array), `$event_type`, `$score`, `$data`, `$user_id`
  - Return: Modified array of badge slugs

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

**Current Phase:** Phase 4 (Room System) - see ROADMAP.md for detailed phases.

**Completed Phases:**
- ✅ Phase 0: Foundation & Setup - Plugin skeleton complete
- ✅ Phase 1: Core REST API - Security, validation, rate limiting implemented
- ✅ Phase 2: Emulator Integration - JavaScript bridge with retry logic and offline support
- ✅ Phase 3: Gamification System Integration - GamiPress & MyCred with intelligent multipliers

Plugin is in active development (v0.1.0). WebSocket/real-time features and advanced room management are planned for future phases.
