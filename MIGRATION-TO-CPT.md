# Migration Guide: Custom Tables → Custom Post Types

## Overview

This guide explains the migration from custom database tables to WordPress Custom Post Types (CPTs), following WordPress best practices.

## Why This Change?

**The WordPress Way:**
- ✅ Use Custom Post Types for structured data
- ✅ Use post meta for additional fields
- ✅ Leverage WordPress admin UI
- ✅ Use standard WordPress APIs (WP_Query, get_post_meta, etc.)
- ❌ Only create custom tables when absolutely necessary (high-volume logs, complex performance requirements)

**Benefits:**
- Native WordPress admin UI (edit, bulk actions, search)
- Better extensibility for other plugins
- Familiar APIs for WordPress developers
- Automatic REST API support
- Built-in capabilities and permissions
- Standard WordPress hooks and filters

## What Changed

### Before (Custom Tables)
```php
// Custom table: wp_gamify_rooms
// Custom table: wp_gamify_events
```

### After (Custom Post Types)
```php
// CPT: gamify_room (with post meta)
// CPT: gamify_event (with post meta)
```

## Post Type Structure

### gamify_room
**Post Fields:**
- `post_title` - Room name
- `post_author` - Created by (user ID)
- `post_status` - 'publish' (active) or 'draft' (inactive)
- `post_date` - Created at

**Post Meta:**
- `_room_id` - Unique room identifier (room-xxxxxxxx)
- `_max_players` - Maximum players allowed
- `_room_data` - JSON data for players array
- `_player_count` - Current player count (cached)

### gamify_event
**Post Fields:**
- `post_title` - Event type (e.g., "level_complete")
- `post_author` - User ID
- `post_date` - Event timestamp

**Post Meta:**
- `_event_type` - Event type slug
- `_room_id` - Associated room ID (if any)
- `_score` - Event score
- `_event_data` - JSON event data

## Data Migration

### Step 1: Backup Your Database
```bash
wp db export backup-before-cpt-migration.sql
```

### Step 2: Run Migration Script
```bash
# Via WP-CLI
wp eval-file migrate-to-cpt.php

# Or via browser
# Navigate to: /wp-content/plugins/wp-retro-emulator-gamification-bridge/migrate-to-cpt.php
```

### Step 3: Verify Migration
```bash
# Check room count
wp post list --post_type=gamify_room --format=count

# Check event count
wp post list --post_type=gamify_event --format=count
```

### Step 4: Delete Old Tables (After Verification)
```sql
DROP TABLE IF EXISTS wp_gamify_rooms;
DROP TABLE IF EXISTS wp_gamify_events;
```

## API Changes

### Room Manager

**Before:**
```php
// Direct database queries
$room_id = $room_manager->create_room( 'My Room', 10 );
$room = $wpdb->get_row( "SELECT * FROM wp_gamify_rooms WHERE room_id = '$room_id'" );
```

**After:**
```php
// WordPress post functions
$room_id = $room_manager->create_room( 'My Room', 10 );
$post_id = $room_manager->get_post_id_by_room_id( $room_id );
$room = get_post( $post_id );
$max_players = get_post_meta( $post_id, '_max_players', true );
```

### Database/Events

**Before:**
```php
$event_id = $database->log_event( 'level_complete', $user_id, $room_id, $data, $score );
```

**After:**
```php
$event_id = $database->log_event( 'level_complete', $user_id, $room_id, $data, $score );
// Same API, different implementation using wp_insert_post()
```

## Querying Data

### Get Active Rooms

**Before:**
```php
$rooms = $wpdb->get_results( "SELECT * FROM wp_gamify_rooms WHERE is_active = 1" );
```

**After:**
```php
$rooms = get_posts( array(
    'post_type'      => 'gamify_room',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
) );
```

### Get Events by User

**Before:**
```php
$events = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM wp_gamify_events WHERE user_id = %d",
    $user_id
) );
```

**After:**
```php
$events = get_posts( array(
    'post_type'   => 'gamify_event',
    'author'      => $user_id,
    'numberposts' => -1,
) );
```

### Filter Events by Type

**Before:**
```php
$events = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM wp_gamify_events WHERE event_type = %s",
    'level_complete'
) );
```

**After:**
```php
$events = get_posts( array(
    'post_type'   => 'gamify_event',
    'post_title'  => 'level_complete',
    'numberposts' => -1,
) );
```

## Performance Considerations

### Custom Tables vs CPTs

**When to use Custom Tables:**
- High-volume data (millions of rows)
- Complex queries that can't be optimized with WP_Query
- Real-time performance requirements
- Data that doesn't fit post/meta model

**When to use CPTs:**
- Structured content/data
- Moderate volume (< 100k posts)
- Need WordPress admin UI
- Want extensibility for other plugins
- Standard CRUD operations

### Optimization Tips for CPTs

1. **Use Object Caching:**
```php
wp_cache_set( "room_{$room_id}", $room_data, 'gamify_rooms', 3600 );
$room_data = wp_cache_get( "room_{$room_id}", 'gamify_rooms' );
```

2. **Index Meta Keys:**
```php
// WordPress automatically indexes meta_key
// Use specific meta queries for better performance
```

3. **Limit Query Results:**
```php
$events = get_posts( array(
    'post_type'      => 'gamify_event',
    'posts_per_page' => 50, // Don't use -1 for large datasets
    'fields'         => 'ids', // Only get IDs if you don't need full posts
) );
```

4. **Use Transients for Expensive Queries:**
```php
$stats = get_transient( 'gamify_room_stats' );
if ( false === $stats ) {
    $stats = $this->calculate_stats();
    set_transient( 'gamify_room_stats', $stats, HOUR_IN_SECONDS );
}
```

## Troubleshooting

### Migration Issues

**Problem:** Migration script times out
**Solution:** Increase PHP max_execution_time or migrate in batches

**Problem:** Memory issues during migration
**Solution:** Process events in chunks of 100-500

**Problem:** Data mismatch after migration
**Solution:** Run verification queries to compare counts

### Performance Issues

**Problem:** Event logs slowing down site
**Solution:**
- Implement cleanup of old events (30+ days)
- Use `fields => 'ids'` in queries
- Add pagination to admin views
- Consider using action scheduler for async processing

## Rollback Plan

If you need to roll back:

1. **Keep old tables** - Don't drop them until 100% verified
2. **Revert plugin code** - Use git to revert to previous version
3. **Delete CPT data** (optional):
```sql
DELETE FROM wp_posts WHERE post_type IN ('gamify_room', 'gamify_event');
DELETE FROM wp_postmeta WHERE post_id NOT IN (SELECT ID FROM wp_posts);
```

## Support

For issues or questions about this migration:
- Check existing GitHub issues
- Review this documentation thoroughly
- Test on staging environment first
- Backup data before migrating production

---

**Last Updated:** 2025-01-05
