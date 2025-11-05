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

- **WP_Gamify_Bridge_Room_Manager** (`inc/class-room-manager.php`) 🆕 Enhanced in Phase 4
  - **Lines**: 625 (fully enhanced from 175-line skeleton)
  - **Purpose**: Complete room management system with CRUD operations and player tracking
  - **Core Methods**:
    - `create_room($name, $max_players)` - Create new room, returns room_id
    - `get_room($room_id, $use_cache)` - Get room with caching support
    - `list_rooms($args)` - List rooms with filtering (is_active, created_by, pagination)
    - `update_room($room_id, $data)` - Update room name, max_players, is_active, room_data
    - `delete_room($room_id)` - Permanently delete room
  - **Player Management**:
    - `join_room($room_id, $user_id)` - Join room (checks capacity, validates room)
    - `leave_room($room_id, $user_id)` - Leave room
    - `get_room_players($room_id)` - Get all players in room
    - `is_user_in_room($room_id, $user_id)` - Check membership
    - `update_player_presence($room_id, $user_id)` - Update last_seen timestamp
  - **Cleanup & Stats**:
    - `cleanup_inactive_players()` - Removes players inactive for 30+ minutes (hourly cron)
    - `get_room_stats($room_id)` - Get room statistics (player count, event count, etc.)
  - **Shortcode**: `[retro_room id="room-abc123"]`
    - Auto-joins logged-in users
    - Displays player list with online status
    - Shows room status and capacity
    - Includes notification area for activity
  - **Room State**: Stored in `room_data` JSON field
    - `players` array with user_id, user_name, joined_at, last_seen
    - Custom data can be added via filters
  - **Caching**: Internal room_cache for performance
  - **Scheduled Tasks**: Hourly cleanup via `wp_gamify_bridge_cleanup_rooms` action
  - **Action Hooks**:
    - `wp_gamify_bridge_room_updated` - After room updated
    - `wp_gamify_bridge_room_deleted` - After room deleted
    - `wp_gamify_bridge_player_joined_room` - When player joins
    - `wp_gamify_bridge_player_left_room` - When player leaves
    - `wp_gamify_bridge_player_timeout` - When inactive player removed
  - **Filters**:
    - `wp_gamify_bridge_player_timeout` - Customize timeout duration (default: 1800s / 30min)
    - `wp_gamify_bridge_presence_interval` - Client-side presence update interval (default: 30000ms)

- **WP_Gamify_Bridge_Room_Endpoint** (`inc/class-room-endpoint.php`) 🆕 Created in Phase 4
  - **Lines**: 535
  - **Purpose**: Complete REST API for room operations
  - **Endpoints**:
    - `GET /wp-json/gamify/v1/room` - List rooms (requires auth)
    - `POST /wp-json/gamify/v1/room` - Create room (requires auth)
    - `GET /wp-json/gamify/v1/room/{id}` - Get room details (requires auth)
    - `PUT /wp-json/gamify/v1/room/{id}` - Update room (requires room owner or admin)
    - `DELETE /wp-json/gamify/v1/room/{id}` - Delete room (requires room owner or admin)
    - `POST /wp-json/gamify/v1/room/{id}/join` - Join room (requires auth)
    - `POST /wp-json/gamify/v1/room/{id}/leave` - Leave room (requires auth)
    - `POST /wp-json/gamify/v1/room/{id}/presence` - Update presence (requires auth)
    - `GET /wp-json/gamify/v1/room/{id}/players` - Get players (requires auth)
    - `GET /wp-json/gamify/v1/room/{id}/stats` - Get statistics (requires auth)
  - **Permissions**: Room owner or admin for modify/delete operations
  - **Validation**: Name (1-255 chars), max_players (2-100)

- **WP_Gamify_Bridge_Admin_Page** (`admin/class-admin-page.php`) 🆕 Created in Phase 4, Enhanced in Phase 6
  - **Lines**: 514 (enhanced from 411 lines)
  - **Purpose**: WordPress admin interface for room management and event logs
  - **Features**:
    - Admin menu: "Gamify Bridge" with dashicons-games icon
    - Submenu pages: Rooms, Event Logs (with filtering)
    - Room creation form with validation
    - Room listing table with player counts and status
    - Toggle room active/inactive status
    - Delete rooms with confirmation
    - Copy shortcode to clipboard button
    - Event logs viewer with advanced filtering (50 per page)
  - **Event Log Filtering** 🆕 (Phase 6):
    - Filter by event type (dropdown of all types)
    - Filter by user (wp_dropdown_users)
    - Filter by room (dropdown of all rooms)
    - Date range filtering (from/to dates)
    - Dynamic WHERE clause building
    - Shows filtered event count
    - Reset filters button
  - **Admin Actions**:
    - `admin_post_gamify_create_room` - Handle room creation
    - `admin_post_gamify_delete_room` - Handle room deletion
    - `admin_post_gamify_toggle_room` - Toggle room status
  - **Security**: Nonce verification and capability checks on all actions

- **WP_Gamify_Bridge_Dashboard** (`admin/class-dashboard.php`) 🆕 Created in Phase 6
  - **Lines**: 1095
  - **Purpose**: Comprehensive admin dashboard with statistics, settings, leaderboard, and testing tools
  - **Dashboard Page**:
    - 4 stat cards: Total Events, Active Rooms, Active Players, Events Today
    - Event timeline chart (last 7 days) using Chart.js
    - Event types breakdown (doughnut chart)
    - Recent events table (10 most recent)
    - Top rooms by activity (10 most active)
  - **Settings Page**:
    - Enable debug mode toggle
    - Polling frequency (1-60 seconds, default: 3)
    - Presence update frequency (10-300 seconds, default: 30)
    - Player timeout (5-120 minutes, default: 30)
    - Max notifications (5-100, default: 20)
    - System information display (WordPress, PHP, GamiPress, MyCred)
  - **Leaderboard Page**:
    - 3 leaderboard types: Events, GamiPress XP, MyCred Points
    - Top 50 users with rank badges (🥇🥈🥉)
    - Displays user email and last active time
    - Tab-based navigation between leaderboard types
  - **Event Tester Page**:
    - Form to trigger test events manually
    - Select event type, user, score, level, difficulty
    - Useful for testing integrations without emulator
    - Shows recent test events (20 most recent)
  - **Admin Actions**:
    - `admin_post_gamify_test_event` - Handle test event submission
  - **Key Methods**:
    - `get_statistics()` - Get dashboard stats and chart data
    - `get_leaderboard($type, $limit)` - Get leaderboard by type
    - `render_dashboard_page()` - Statistics dashboard
    - `render_leaderboard_page()` - User rankings
    - `render_settings_page()` - Plugin settings
    - `render_tester_page()` - Event testing tool
    - `render_recent_events($limit)` - Recent events table
    - `render_top_rooms($limit)` - Top active rooms
  - **Assets**:
    - Chart.js 4.4.1 (CDN) for visualizations
    - Custom grid layouts for responsive stats cards
  - **Settings**:
    - Registered as `wp_gamify_bridge_options`
    - Settings section: `wp_gamify_bridge_general`
    - All settings use WordPress Settings API

- **WP_Gamify_Bridge_Script_Enqueuer** (`inc/class-script-enqueuer.php`)
  - Handles JavaScript/CSS asset loading

- **WP_Gamify_Bridge_Emulator_Manager** (`inc/class-emulator-manager.php`) 🆕 Created in Phase 7
  - **Lines**: 273
  - **Purpose**: Centralized management of emulator adapters
  - **Key Methods**:
    - `register_adapter($adapter)` - Register an emulator adapter
    - `get_adapter($name)` - Get adapter by name
    - `get_adapters()` - Get all registered adapters
    - `get_enabled_adapters()` - Get only enabled adapters
    - `transform_event($event_data, $emulator)` - Transform event through correct adapter
    - `get_statistics()` - Get emulator statistics (events by emulator/system)
    - `get_adapters_metadata()` - Get metadata for all adapters
  - **Registered Adapters**: JSNES, jSNES, GBA, MAME, RetroArch, EmulatorJS
  - **Filters**:
    - `wp_gamify_bridge_transform_event` - Transform event data via adapter
  - **Actions**:
    - `wp_gamify_bridge_register_adapters` - Register custom adapters

### Emulator Adapter System 🆕 (Phase 7)

The plugin uses an adapter pattern to support multiple emulator platforms. Each adapter handles:
- Event mapping (emulator-specific events → standard events)
- Event validation
- Event transformation
- Score multipliers
- JavaScript hooks
- Auto-detection

**Base Adapter** (`inc/adapters/class-emulator-adapter.php`):
- Abstract class all adapters extend
- **Key Properties**:
  - `$name` - Adapter identifier
  - `$display_name` - Human-readable name
  - `$description` - Adapter description
  - `$supported_systems` - Array of supported systems
  - `$js_detection` - JavaScript detection code
  - `$config` - Adapter configuration
- **Abstract Methods**:
  - `get_event_mappings()` - Return array of emulator events → standard events
  - `get_js_hooks()` - Return JavaScript hook code
- **Methods**:
  - `validate_event_data($event_data)` - Validate event (can override)
  - `transform_event_data($event_data)` - Transform event (can override)
  - `get_config_fields()` - Configuration form fields
  - `get_score_multiplier()` - Get score multiplier for this emulator
  - `apply_score_multiplier($event_data)` - Apply multiplier to scores

**Supported Emulators**:

1. **JSNES** (`class-jsnes-adapter.php`) - NES
   - Systems: NES, Famicom
   - Detection: `typeof window.JSNES !== 'undefined'`
   - Events: level_cleared, game_completed, high_score, player_died
   - Default score multiplier: 1.0

2. **jSNES** (`class-jsnes-snes-adapter.php`) - SNES
   - Systems: SNES, Super Famicom
   - Detection: `typeof window.jSNES !== 'undefined'`
   - Events: stage_complete, game_complete, boss_defeated, continue_used
   - Default score multiplier: 1.0

3. **GBA.js** (`class-gba-adapter.php`) - Game Boy Advance
   - Systems: GBA, Game Boy Advance
   - Detection: `typeof window.GBA !== 'undefined'`
   - Events: level_complete, game_complete, checkpoint, player_ko, badge_earned
   - Default score multiplier: 1.0

4. **MAME.js** (`class-mame-adapter.php`) - Arcade
   - Systems: Arcade
   - Detection: `typeof window.MAME !== 'undefined' || typeof window.JSMAME !== 'undefined'`
   - Events: round_complete, game_over, high_score, coin_inserted, extra_life
   - Default score multiplier: 0.1 (arcade scores are typically very high)

5. **RetroArch** (`class-retroarch-adapter.php`) - Multi-system
   - Systems: NES, SNES, Genesis, GBA, PlayStation, N64, Arcade, Multiple
   - Detection: `typeof window.Module !== 'undefined' && window.Module.canvas`
   - Events: achievement_earned, level_beaten, game_finished, player_death
   - Core-to-system mapping for proper system detection
   - RetroAchievements support
   - Default score multiplier: 1.0

6. **EmulatorJS** (`class-emulatorjs-adapter.php`) - Web-based multi-system
   - Systems: NES, SNES, GBA, N64, Genesis, PlayStation, Atari, Multiple
   - Detection: `typeof window.EJS_player !== 'undefined'`
   - Events: stage_cleared, game_completed, milestone, save_state
   - System auto-detection from EJS_core
   - Save state tracking option
   - Default score multiplier: 1.0

**Creating Custom Adapters**:

```php
class My_Custom_Emulator_Adapter extends WP_Gamify_Bridge_Emulator_Adapter {

    public function __construct() {
        $this->name = 'my_emulator';
        $this->display_name = 'My Emulator';
        $this->description = 'Description of my emulator';
        $this->supported_systems = array('System Name');
        $this->js_detection = 'typeof window.MyEmulator !== \'undefined\'';

        $options = get_option('wp_gamify_bridge_emulators', array());
        $this->config = isset($options['my_emulator']) ? $options['my_emulator'] : $this->get_default_config();
    }

    public function get_event_mappings() {
        return array(
            'my_event' => 'level_complete',
            'my_other_event' => 'game_over',
        );
    }

    public function get_js_hooks() {
        return <<<'JS'
hookMyEmulator: function() {
    const self = this;
    this.emulatorType = 'MyEmulator';

    document.addEventListener('myemulator:event', function(e) {
        self.onLevelComplete(e.detail.level, e.detail.score, e.detail.time);
    });
},
JS;
    }
}

// Register custom adapter
add_action('wp_gamify_bridge_register_adapters', function($manager) {
    $manager->register_adapter(new My_Custom_Emulator_Adapter());
});
```

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

- **room.js** (`js/room.js`) 🆕 Enhanced in Phase 5
  - **Lines**: 704 (completely rewritten from 236-line skeleton)
  - **Purpose**: Real-time room communication with polling and WebSocket upgrade path
  - **Core Object**: `window.WPGamifyRoom`

  - **Polling System**:
    - `pollingFrequency: 3000` - Poll every 3 seconds for room updates
    - `presenceFrequency: 30000` - Update presence every 30 seconds
    - Automatic polling start/stop based on network status
    - Immediate poll when page becomes visible again
    - Polls endpoints:
      - `GET /wp-json/gamify/v1/room/{id}/players` - Check player joins/leaves
      - `GET /wp-json/gamify/v1/room/{id}/stats` - Check for new events

  - **Notification System**:
    - 6 notification types: info, join, leave, event, success, error
    - Color-coded notifications with icons and timestamps
    - Max 20 notifications (auto-cleanup of oldest)
    - slideDown animation for new notifications
    - Notification queue with automatic display
    - Custom event trigger: `wp_gamify_room_notification`

  - **Player Presence**:
    - 30-second presence updates to server (POST /presence)
    - Real-time player list updates on frontend
    - Join/leave detection with notifications
    - Player status indicators with pulse animation
    - Last seen timestamps

  - **Network Resilience**:
    - Online/offline event listeners
    - Automatic polling stop when offline
    - Automatic polling resume when online
    - Network status indicator (fixed position)
    - Graceful degradation without errors

  - **WebSocket Upgrade Path**:
    - Prepared `connectWebSocket()` method (commented implementation)
    - Fallback to polling if WebSocket unavailable
    - Example Supabase Realtime integration code
    - Easy upgrade path for production deployment

  - **Page Visibility Handling**:
    - Detects when user switches tabs
    - Immediate poll when tab becomes visible
    - Potential future optimization: reduce polling when hidden

  - **Event Handling**:
    - Listens to `wp_gamify_event` custom event from emulator
    - Shows notifications for game events (level complete, game over, etc.)
    - Broadcasts events to room via REST API

  - **Debug Features**:
    - `wpGamifyRoomStats()` - Display room statistics in console.table
    - Debug mode controlled by `config.debug`
    - Color-coded console logging
    - Poll count and player count tracking

  - **Key Methods**:
    - `init()` - Initialize room system
    - `loadRoomData()` - Get room info from DOM data attributes
    - `startPolling()` - Begin polling loop
    - `stopPolling()` - Stop polling (on offline)
    - `pollRoomUpdates()` - Check for new players and events
    - `updatePlayers(newPlayers)` - Update player list and detect joins/leaves
    - `showNotification(type, message)` - Display notification to user
    - `updatePresence()` - Send presence update to server
    - `handleGamifyEvent(data)` - Handle events from emulator
    - `connectWebSocket()` - WebSocket connection (future upgrade)

  - **Configuration**:
    - Room ID from `data-room-id` attribute
    - User info from `data-current-user-id` and `data-current-user-name`
    - REST URL from `data-rest-url`
    - Nonce from `data-nonce`

  - **Statistics Tracking**:
    - `pollCount` - Number of polls performed
    - `lastPollTime` - Timestamp of last poll
    - `players` - Current player list
    - `isOnline` - Network status
    - `pollingInterval` - Interval ID for polling
    - `presenceInterval` - Interval ID for presence updates

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

**Room System Actions** 🆕 (Phase 4):
- `wp_gamify_bridge_room_updated` - After room updated
  - Args: `$room_id`, `$update_data` (array of changed fields)
- `wp_gamify_bridge_room_deleted` - After room deleted
  - Args: `$room_id`
- `wp_gamify_bridge_player_joined_room` - When player joins room
  - Args: `$room_id`, `$user_id`
- `wp_gamify_bridge_player_left_room` - When player leaves room
  - Args: `$room_id`, `$user_id`
- `wp_gamify_bridge_player_timeout` - When inactive player removed
  - Args: `$room_id`, `$user_id`
- `wp_gamify_bridge_cleanup_rooms` - Scheduled hourly cleanup
  - No args - hook for custom cleanup tasks

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

**Room System Filters** 🆕 (Phase 4):
- `wp_gamify_bridge_player_timeout` - Customize player timeout duration
  - Args: `$timeout` (int, default: 1800 seconds / 30 minutes)
  - Return: Timeout in seconds
- `wp_gamify_bridge_presence_interval` - Customize client-side presence update interval
  - Args: `$interval` (int, default: 30000 milliseconds / 30 seconds)
  - Return: Interval in milliseconds
- `wp_gamify_bridge_require_room_membership` - Require users to join room before triggering events
  - Args: `$require` (bool, default: true)
  - Return: Boolean

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

### Room.js Usage Examples 🆕 (Phase 5)

#### Basic Room Initialization
```javascript
// Room automatically initializes on page load if room shortcode present
// Access room instance globally
console.log(WPGamifyRoom.roomId);
console.log(WPGamifyRoom.players);
```

#### Checking Room Statistics
```javascript
// In browser console
wpGamifyRoomStats();

// Returns and displays:
// Room ID: room-abc123
// Current User: johndoe (ID: 1)
// Players: 5
// Poll Count: 42
// Last Poll: 2025-01-05 12:34:56
// Online: Yes
```

#### Manual Notifications
```javascript
// Show custom notification
WPGamifyRoom.showNotification('info', 'Welcome to the room!');
WPGamifyRoom.showNotification('success', 'Achievement unlocked!');
WPGamifyRoom.showNotification('error', 'Connection lost');
```

#### Listening to Room Events
```javascript
// Listen for notifications
document.addEventListener('wp_gamify_room_notification', function(e) {
    console.log('Notification:', e.detail.type, e.detail.message);
});

// Listen for emulator events
document.addEventListener('wp_gamify_event', function(e) {
    console.log('Game event:', e.detail);
    // WPGamifyRoom automatically handles and shows notifications
});
```

#### Manual Presence Update
```javascript
// Force immediate presence update (normally automatic every 30s)
WPGamifyRoom.updatePresence();
```

#### Integrating Custom Emulator Events
```javascript
// In your game code
function onPlayerAchievement(achievementName) {
    // Trigger WordPress event
    const event = new CustomEvent('wp_gamify_event', {
        detail: {
            event: 'achievement_unlock',
            data: { achievement: achievementName }
        }
    });
    document.dispatchEvent(event);

    // WPGamifyRoom will:
    // 1. Show notification in room
    // 2. Send to WordPress REST API
    // 3. Broadcast to other players (via polling)
}
```

## Project Status

**Current Phase:** Phase 9 (Testing & Documentation) - see ROADMAP.md for detailed phases.

**Completed Phases:**
- ✅ Phase 0: Foundation & Setup - Plugin skeleton complete
- ✅ Phase 1: Core REST API - Security, validation, rate limiting implemented
- ✅ Phase 2: Emulator Integration - JavaScript bridge with retry logic and offline support
- ✅ Phase 3: Gamification System Integration - GamiPress & MyCred with intelligent multipliers
- ✅ Phase 4: Room System - Complete room management with CRUD, player tracking, admin UI
- ✅ Phase 5: Real-time Broadcasting - Polling-based real-time updates with WebSocket upgrade path
- ✅ Phase 6: Admin Dashboard - Statistics dashboard, settings, leaderboard, event tester, advanced filtering
- ✅ Phase 7: Extended Emulator Support - Adapter pattern with 6 emulator adapters (JSNES, jSNES, GBA, MAME, RetroArch, EmulatorJS)
- ✅ Phase 8: Advanced Features - Performance, analytics, and developer tools substantially complete (social features deferred to v2.0)

**Plugin Status:** Feature-complete for v1.0 release. Ready for testing and documentation.

**Phase 8 Summary:**
Phase 8 marked as substantially complete because core advanced features were already implemented in earlier phases:
- **Performance:** Caching (Phase 4), query optimization (Phase 1-4), rate limiting (Phase 1)
- **Analytics:** Event tracking, statistics dashboard with Chart.js, leaderboard system (Phases 1, 6, 7)
- **Developer Tools:** Event tester, REST API, 20+ hooks/filters, adapter pattern (Phases 6-7)
- **Deferred:** Social features (chat, challenges, teams) deferred to v2.0 as optional enhancements

**Next Steps:**
- Phase 9: Testing & Documentation (PHPUnit tests, security audit, user/developer documentation)
- Phase 10: Release & Community (WordPress.org submission, demo site, community channels)

Plugin is feature-complete for v1.0 release (v0.1.0). Comprehensive emulator support with extensible adapter system.
