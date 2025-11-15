# Migration Guide - Retro Game Emulator Integration

This guide documents the migration from the standalone **Retro Game Emulator** plugin to the integrated **WP Retro Emulator Gamification Bridge** system.

## Overview

The legacy Retro Game Emulator plugin has been fully integrated into WP Gamify Bridge with significant enhancements:

- **WordPress Media Library Integration**: ROMs are now proper WordPress attachments
- **Comprehensive Metadata**: Auto-extraction of checksums, file sizes, and emulator compatibility
- **Enhanced Security**: Multi-layer validation (MIME type, file extension, size limits)
- **Gamification**: Automatic XP/points/achievements through GamiPress and MyCred
- **Multi-emulator Support**: 6 emulator adapters (JSNES, jSNES/SNES, GBA, MAME, RetroArch, EmulatorJS)
- **Room System**: Multiplayer and social features

## Pre-Migration Checklist

Before migrating, ensure you have:

1. ✅ **Backup your database**: `wp db export backup.sql` or use your backup tool
2. ✅ **Backup ROM files**: Copy `/wp-content/uploads/retro-game-emulator/` to a safe location
3. ✅ **WordPress 6.0+** and **PHP 7.4+** installed
4. ✅ **WP Gamify Bridge** plugin activated
5. ✅ **Sufficient disk space**: ROMs will be duplicated during migration (original + attachment)

## Migration Steps

### Step 1: Run the Migration Script

1. Navigate to the migration script URL in your browser:
   ```
   https://your-site.local/wp-content/plugins/wp-retro-emulator-gamification-bridge/migrate-legacy-roms.php
   ```

2. You will see a preview of all ROM files to be migrated:
   - Filename, size, adapter assignment, and system detection
   - Total file count and supported extensions

3. Click **"▶️ Start Migration"** to begin

4. The script will:
   - Create WordPress attachments for each ROM file
   - Generate `retro_rom` posts with metadata
   - Auto-assign adapters based on file extension
   - Set taxonomy terms (system, difficulty, multiplayer mode)
   - Calculate MD5 checksums for duplicate detection
   - Skip already-migrated ROMs (checksum-based)

### Step 2: Verify Migration

1. **Check ROM Library**: Navigate to **WordPress Admin → Retro ROMs**
   - Verify all ROMs appear with correct titles
   - Check adapter assignments are correct
   - Confirm file sizes and checksums are populated

2. **Test ROM Playback**: Create a test page with `[retro_emulator]` shortcode
   - Select a migrated ROM from dropdown
   - Verify ROM loads and plays correctly
   - Test touch controls (if on mobile/tablet)
   - Confirm events are being logged (check browser console)

3. **Verify Media Library**: Navigate to **WordPress Admin → Media**
   - Confirm ROM files appear as attachments
   - Check file types show as `application/octet-stream` or similar

### Step 3: Update Content

The `[nes]` shortcode is still supported for backward compatibility:

**Before** (Legacy):
```
[nes]
```

**After** (Recommended):
```
[retro_emulator id="123"]
```

**Note**: The `[nes]` shortcode will automatically:
- Use the new emulator interface
- Display a migration notice to users
- Work with all migrated ROMs

**No immediate action required** - content using `[nes]` will continue working, but you should eventually update to `[retro_emulator]` for:
- Multi-system support (not just NES)
- ROM selection dropdown
- Touch control toggles
- Better mobile experience

### Step 4: Test Gamification (Optional)

If you have GamiPress or MyCred installed:

1. Play a migrated ROM
2. Complete a level or reach a score milestone
3. Check your profile for XP/points awards
4. Verify events appear in **Gamify Bridge → Event Logs**

### Step 5: Remove Legacy Plugin

**⚠️ Only after confirming everything works:**

1. **Deactivate** the legacy "Retro Game Emulator" plugin
2. Navigate to **Plugins** and click **Delete** on "Retro Game Emulator"
3. Optionally, remove the ROM files from `/wp-content/uploads/retro-game-emulator/`
   - **Keep as backup** for at least 30 days
   - The migrated ROMs are now WordPress attachments in the standard uploads directory

## Migration Script Details

### What Gets Created

For each ROM file (e.g., `BombSweeper.nes`):

1. **WordPress Attachment**:
   - File: `/wp-content/uploads/2025/01/BombSweeper.nes`
   - Post type: `attachment`
   - MIME type: `application/octet-stream`
   - Title: `Bomb Sweeper`

2. **ROM Post**:
   - Post type: `retro_rom`
   - Title: `Bomb Sweeper`
   - Meta:
     - `_retro_rom_adapter`: `jsnes`
     - `_retro_rom_source`: `{attachment_id}` (not a path!)
     - `_retro_rom_checksum`: `a1b2c3d4e5f6...` (MD5 hash)
     - `_retro_rom_file_size`: `24576` (bytes)
   - Taxonomies:
     - `retro_system`: `NES`
     - `retro_difficulty`: `normal` (default)
     - `retro_multiplayer_mode`: `Single Player` (default)

### Supported File Extensions

The migration script supports 32 file extensions:

| System | Extensions |
|--------|-----------|
| NES/Famicom | `.nes`, `.fds`, `.unif`, `.unf` |
| SNES/Super Famicom | `.smc`, `.sfc`, `.fig`, `.swc`, `.bs` |
| Game Boy / GBC / GBA | `.gb`, `.gbc`, `.gba`, `.agb` |
| Genesis / Mega Drive | `.md`, `.gen`, `.bin`, `.smd` |
| N64 | `.z64`, `.n64`, `.v64` |
| PlayStation | `.iso`, `.cue`, `.bin` |
| Arcade | `.zip`, `.7z` |
| Atari | `.a26`, `.a52`, `.a78` |

### Adapter Assignment

Adapters are auto-assigned based on file extension:

- `.nes`, `.fds`, `.unif`, `.unf` → **JSNES** adapter
- `.smc`, `.sfc`, `.fig`, `.swc`, `.bs` → **jSNES (SNES)** adapter
- `.gb`, `.gbc`, `.gba`, `.agb` → **GBA.js** adapter
- Other extensions → See `migrate-legacy-roms.php` for full mapping

You can manually change the adapter after migration via **Edit ROM → ROM Details meta box**.

## Troubleshooting

### Migration Script Errors

**Error: "Directory not found"**
- The legacy ROM directory `/wp-content/uploads/retro-game-emulator/` doesn't exist
- Place ROM files in that directory and refresh

**Error: "No ROM files found"**
- The directory exists but is empty
- Add ROM files with supported extensions

**Error: "Failed to create attachment"**
- WordPress file permissions issue
- Ensure `/wp-content/uploads/` is writable (755 or 775)
- Check PHP `upload_max_filesize` and `post_max_size` settings

**Skipped: "already imported - matching checksum"**
- A ROM with the same MD5 checksum already exists
- This prevents duplicate imports
- Safe to ignore

### ROM Playback Issues

**Error: "No ROMs available"**
- Migration didn't complete successfully
- Check **WordPress Admin → Retro ROMs** for ROM posts
- Verify posts are Published (not Draft)

**ROM loads but doesn't play**
- Browser console errors? Check JavaScript console
- Adapter mismatch? Verify adapter in ROM Details meta box
- File corrupted? Re-upload via **Edit ROM → ROM Upload meta box**

**Events not logging**
- Check browser console for REST API errors
- Verify user is logged in (events require authentication)
- Check **Gamify Bridge → Event Logs** for rate limiting

## What's New in the Integrated System

### For Users

- **ROM Dropdown**: Select from all available ROMs without editing shortcode
- **Touch Controls**: Mobile-friendly D-pad and buttons with toggle
- **Live Notifications**: Real-time event feedback (XP awards, level completion)
- **Room System**: Join rooms for multiplayer notifications and leaderboards
- **Multiple Emulators**: Not limited to NES anymore

### For Administrators

- **ROM Library**: WordPress admin interface for ROM management
- **Upload Interface**: Drag-and-drop ROM uploads via Media Library
- **Validation**: Automatic file extension, MIME type, and size checks
- **Metadata**: Auto-calculated checksums and file sizes
- **Adapters**: Per-ROM adapter selection with inline help
- **Gamification**: Built-in XP/points/achievements configuration
- **Event Logs**: Track all game events with filtering
- **Dashboard**: Statistics, leaderboards, and analytics

### For Developers

- **REST API**: `/wp-json/gamify/v1/event` for custom integrations
- **JavaScript Bridge**: `window.triggerWPEvent()` for custom emulators
- **Hooks & Filters**: 20+ WordPress hooks for customization
- **Adapter System**: Extensible emulator adapter pattern
- **Room API**: Complete room management via REST
- **Rate Limiting**: Built-in abuse prevention

## Support and Resources

- **Documentation**: See `README.md` and `CLAUDE.md` in plugin directory
- **Roadmap**: See `RETRO_GAME_EMULATOR_INTEGRATION_AND_AUGMENTATION_ROADMAP.md`
- **Event Testing**: Use **Gamify Bridge → Event Tester** admin page
- **GitHub Issues**: Report bugs and request features

## Rollback Plan

If you need to rollback:

1. **Reactivate** the legacy "Retro Game Emulator" plugin
2. **Deactivate** WP Gamify Bridge
3. **Restore** your database backup: `wp db import backup.sql`
4. ROM files in `/wp-content/uploads/retro-game-emulator/` remain untouched

**Note**: You will lose any new event logs, rooms, or gamification data created after migration.

## Version History

- **v0.1.0 (Phase 6)**: Initial integrated release with migration script
- **Phase 2**: Upload infrastructure with Media Library integration
- **Phase 3**: Admin UI with adapter metadata tooltips
- **Phase 4**: Frontend emulator with touch controls
- **Phase 5**: Enhanced adapter metadata system
- **Phase 7**: Multi-emulator adapter pattern

---

**Questions?** Check the plugin dashboard at **Gamify Bridge → Dashboard** for system information and statistics.
