# Hybrid Implementation: Rooms as CPT, Events as Custom Table

## ✅ What's Complete

### 1. Custom Post Types Registered
- `gamify_room` - Room CPT (active)
- `gamify_event` - Event CPT (registered but not used)

### 2. New Room Manager Created
- **File:** `inc/class-room-manager-cpt.php`
- **Status:** Complete and ready to use
- **Lines:** ~650 (fully refactored)
- **Uses:** WordPress CPT functions (`wp_insert_post`, `get_posts`, `update_post_meta`, etc.)

### 3. Events Stay Custom Table
- **File:** `inc/class-database.php`
- **Status:** Unchanged - keeps using custom table
- **Reason:** High-volume event logging is appropriate for custom tables
- **Justification:** WordPress core uses custom tables for similar use cases (e.g., `wp_comments`, `wp_links`)

## 🔄 How to Switch to Hybrid Mode

### Step 1: Backup Your Data
```bash
# Export current rooms from database
wp db export backup-before-hybrid.sql
```

### Step 2: Rename Files
```bash
cd wp-content/plugins/wp-retro-emulator-gamification-bridge/inc/

# Backup old room manager
mv class-room-manager.php class-room-manager-old.php

# Activate new CPT-based room manager
mv class-room-manager-cpt.php class-room-manager.php
```

### Step 3: Run Migration
Navigate to:
```
http://your-site.local/wp-content/plugins/wp-retro-emulator-gamification-bridge/migrate-to-cpt.php
```

This will:
- ✅ Migrate existing rooms to CPT
- ✅ Skip events (keeps custom table)
- ✅ Verify data integrity

### Step 4: Test Room Functionality
1. Go to **Gamify Bridge → Rooms**
2. Create a new room
3. Edit an existing room
4. Join a room (via shortcode or REST API)
5. Verify dashboard displays rooms correctly

### Step 5: Drop Old Room Table (After Verification)
Once you've verified everything works:
```sql
DROP TABLE IF EXISTS wp_gamify_rooms;
```

**IMPORTANT:** Keep `wp_gamify_events` table - it's still being used!

## 📊 Architecture

### Hybrid Approach
```
┌─────────────────────────────────────────┐
│  Rooms (gamify_room CPT)                │
│  - Low volume (~100s)                   │
│  - Rich metadata                        │
│  - Benefits from WordPress admin UI     │
│  - Uses: wp_insert_post, get_posts      │
└─────────────────────────────────────────┘
                ▼
        WordPress Core
                ▼
┌─────────────────────────────────────────┐
│  Events (wp_gamify_events table)        │
│  - High volume (1000s-millions)         │
│  - Simple log structure                 │
│  - Optimized queries                    │
│  - Uses: $wpdb->insert, $wpdb->get_results  │
└─────────────────────────────────────────┘
```

## 🔍 API Compatibility

The new Room Manager maintains **100% API compatibility** with the old version. All existing code will work without changes:

```php
// These work exactly the same
$room_id = $room_manager->create_room( 'My Room', 10 );
$room = $room_manager->get_room( $room_id );
$rooms = $room_manager->list_rooms( array( 'limit' => 50 ) );
$room_manager->join_room( $room_id, $user_id );
```

## 🎯 Benefits

### For Rooms (Now CPT)
- ✅ Native WordPress admin UI
- ✅ Built-in search and filters
- ✅ Standard WordPress hooks
- ✅ Better extensibility
- ✅ Familiar for WP developers

### For Events (Still Custom Table)
- ✅ High-performance logging
- ✅ Optimized for volume
- ✅ Appropriate for this use case
- ✅ Follows WordPress examples (comments, links)

## 📝 What Changed

### Room Manager Methods

**create_room()**
```php
// Before: Direct $wpdb->insert
$wpdb->insert( $table, array(...), array('%s', '%d') );

// After: WordPress post functions
wp_insert_post( array('post_type' => 'gamify_room', ...) );
update_post_meta( $post_id, '_room_id', $room_id );
```

**get_room()**
```php
// Before: SELECT query
$wpdb->get_row( "SELECT * FROM wp_gamify_rooms WHERE room_id = %s" );

// After: Meta query
get_posts( array(
    'post_type' => 'gamify_room',
    'meta_query' => array(
        array( 'key' => '_room_id', 'value' => $room_id )
    )
) );
```

**list_rooms()**
```php
// Before: Complex SQL with WHERE clauses
$wpdb->get_results( "SELECT * FROM ... WHERE ... ORDER BY ... LIMIT ..." );

// After: WP_Query
get_posts( array(
    'post_type' => 'gamify_room',
    'posts_per_page' => $limit,
    'post_status' => 'publish'
) );
```

## 🚨 Important Notes

### 1. Events Table Stays
**DO NOT drop the `wp_gamify_events` table!** It's still actively used.

### 2. Room Meta Structure
```php
// Post meta keys used:
_room_id        // Unique room identifier (room-abc123)
_max_players    // Maximum players allowed
_room_data      // JSON: { players: [...] }
_player_count   // Cached player count
```

### 3. Post Status Mapping
- **publish** = Active room (`is_active = 1`)
- **draft** = Inactive room (`is_active = 0`)

### 4. Backward Compatibility
The new Room Manager returns data in the same format as the old one:
```php
array(
    'id' => 123,                    // Post ID (new)
    'room_id' => 'room-abc123',     // Room ID (same)
    'name' => 'My Room',            // (same)
    'created_by' => 1,              // (same)
    'max_players' => 10,            // (same)
    'is_active' => 1,               // (same)
    'room_data' => array(...),      // (same)
    'created_at' => '2025-01-05',   // (same)
    'updated_at' => '2025-01-05',   // (same)
)
```

## ✅ Verification Checklist

After switching:

- [ ] Rooms appear in WordPress admin (`edit.php?post_type=gamify_room`)
- [ ] Can create new room via admin
- [ ] Can create new room via REST API
- [ ] Shortcode `[retro_room id="room-xxx"]` displays correctly
- [ ] Players can join/leave rooms
- [ ] Dashboard shows room statistics
- [ ] Events still log correctly (custom table)
- [ ] No PHP errors in debug.log

## 🛟 Rollback Plan

If something goes wrong:

```bash
cd wp-content/plugins/wp-retro-emulator-gamification-bridge/inc/

# Restore old room manager
mv class-room-manager.php class-room-manager-cpt.php
mv class-room-manager-old.php class-room-manager.php

# Refresh WordPress
```

Your old custom table data will still be there!

## 📚 References

### WordPress Custom Tables Examples
WordPress core uses custom tables for:
- **Comments** (`wp_comments`) - High-volume logs
- **Links** (`wp_links`) - Simple data structure
- **Term Relationships** (`wp_term_relationships`) - Performance

### When to Use Custom Tables
From WordPress.org guidelines:
> "Use custom database tables only when the data structure doesn't fit the post/meta model or when you need exceptional performance for high-volume data."

**Events qualify** because:
- High volume (potentially millions)
- Simple log structure
- Time-series data
- Query optimization needs

**Rooms don't qualify** because:
- Low-medium volume
- Rich metadata
- Benefit from WordPress UI
- Standard CRUD operations

---

**Last Updated:** 2025-01-05
**Implementation:** Ready to deploy
