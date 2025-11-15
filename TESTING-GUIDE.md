# Testing Guide - WP Retro Emulator Gamification Bridge

This guide explains how to test Phases 2, 3, and 6 of the plugin using the provided testing resources.

---

## Overview

**What's Being Tested**:
- **Phase 2**: ROM Upload Infrastructure (Media Library integration, file validation, metadata extraction)
- **Phase 3**: ROM Library Admin Page (WP_List_Table, bulk actions, drag-drop uploader, contextual help)
- **Phase 6**: Migration System (legacy ROM import with attachments, duplicate detection, [nes] shortcode compatibility)

**Testing Resources**:
1. `TESTING_SCHEDULE.md` - Weekly testing cadence and MCP helper commands
2. `TEST-EXECUTION-CHECKLIST.md` - 24 detailed test scenarios with step-by-step instructions
3. `TEST-REPORT-TEMPLATE.md` - Comprehensive test report template for documenting results

---

## Quick Start

### Option 1: Manual Testing (Recommended for First Run)
1. Follow `TEST-EXECUTION-CHECKLIST.md` sequentially (Tests 1-24)
2. Check off each test as you complete it
3. Document results in `TEST-REPORT-TEMPLATE.md`
4. Take screenshots of critical features
5. Log any issues found in the Issues Log section

### Option 2: Automated Testing (Playwright MCP)
1. Ensure Playwright MCP server is running
2. Use MCP helpers from `TESTING_SCHEDULE.md`:
   ```bash
   mcp run admin:navigate --page="rom-library"
   mcp run rom:upload --file="path/to/rom.nes"
   mcp run rom:bulk-action --action=delete --roms="1,2,3"
   mcp run migration:execute --verify-attachments
   ```
3. Combine automated checks with manual verification for UI/UX

### Option 3: Hybrid Approach
1. Use Playwright for repetitive tasks (navigation, bulk actions)
2. Manual verification for UI elements (modals, help tabs, notifications)
3. Manual testing for subjective criteria (usability, visual design)

---

## Prerequisites

### WordPress Environment
- WordPress 6.4+ installed and running
- Site accessible at local URL (e.g., `http://campaign-forge.local`)
- Admin access with username/password
- PHP 8.1+ and MySQL 5.7+

### Plugin Installation
1. WP Retro Emulator Gamification Bridge plugin activated
2. All dependencies installed:
   ```bash
   cd /path/to/plugin
   composer install
   ```
3. Database tables created (check `wp_gamify_events` and `wp_gamify_rooms` exist)

### Test Data Preparation

#### ROM Files
Prepare at least 6 test ROM files for migration testing:

1. **Place legacy ROMs** in `/wp-content/uploads/retro-game-emulator/`:
   - `BombSweeper.nes` (24 KB)
   - `Elite-PD.nes` (128 KB)
   - Additional .nes, .gba, .smc files (optional)

2. **Prepare upload test files**:
   - Valid files: `.nes`, `.gba`, `.smc`, `.zip`
   - Invalid files: `.exe`, `.txt`, `.php` (for rejection testing)
   - Oversized file: 15MB+ file (for size limit testing)

#### Browser Setup
- **Browser Console**: Open developer tools (F12) to monitor JavaScript errors
- **Network Tab**: Open for debugging AJAX requests
- **PHP Error Log**: Ensure error logging enabled in `wp-config.php`:
  ```php
  define( 'WP_DEBUG', true );
  define( 'WP_DEBUG_LOG', true );
  define( 'WP_DEBUG_DISPLAY', false );
  ```

---

## Test Execution Workflow

### Step 1: Pre-Test Setup (10 minutes)
1. Verify WordPress site accessible
2. Confirm plugin activated
3. Clear browser cache
4. Open browser console (F12)
5. Prepare test ROM files
6. Backup database (optional but recommended):
   ```bash
   wp db export backup-before-testing.sql
   ```

### Step 2: Phase 2 Testing - Upload Infrastructure (30 minutes)
Execute Tests 1-6 from `TEST-EXECUTION-CHECKLIST.md`:

**Test 1**: Upload via Media Library (5 min)
- Navigate to **Retro ROMs → Add New**
- Test WordPress Media Library integration
- Verify metadata auto-extraction

**Test 2**: Valid File Uploads (5 min)
- Upload .nes, .gba, .zip files
- Confirm acceptance and proper MIME type handling

**Test 3**: Invalid File Rejection (5 min)
- Attempt .exe, .txt, oversized file uploads
- Verify error messages display correctly

**Test 4**: Metadata Extraction (5 min)
- Check MD5 checksum generation
- Verify file size calculation

**Test 5**: Replace ROM File (5 min)
- Test file replacement workflow
- Confirm metadata updates

**Test 6**: Remove ROM File (5 min)
- Test file removal
- Verify attachment preservation

### Step 3: Phase 3 Testing - ROM Library Admin (60 minutes)
Execute Tests 7-18 from `TEST-EXECUTION-CHECKLIST.md`:

**Test 7**: List Table Display (5 min)
- Navigate to **Gamify Bridge → ROM Library**
- Verify all columns display correctly
- Check emoji icons, file sizes, statuses

**Test 8**: Sorting (5 min)
- Test sortable columns (Title, Date, File Size)
- Verify URL parameters update

**Test 9**: Filtering (5 min)
- Filter by adapter and system
- Verify filtered results

**Test 10**: Row Actions (10 min)
- Test Edit, Delete, View links
- Verify confirmation dialogs

**Test 11-13**: Bulk Actions - Delete/Publish/Draft (15 min)
- Test each bulk action separately
- Verify success notices
- Confirm status changes

**Test 14**: Bulk Action - Change Adapter (10 min)
- Test modal appearance
- Verify adapter updates across multiple ROMs

**Test 15**: Drag-and-Drop Uploader (5 min)
- Test multi-file upload
- Verify ROM posts auto-created

**Test 16**: Contextual Help (5 min)
- Test all 4 help tabs
- Verify help sidebar links

**Test 17**: Screen Options (3 min)
- Change ROMs per page
- Verify pagination updates

**Test 18**: Empty State (2 min)
- Delete all ROMs temporarily
- Verify empty state message

### Step 4: Phase 6 Testing - Migration (30 minutes)
Execute Tests 19-24 from `TEST-EXECUTION-CHECKLIST.md`:

**Test 19**: Migration Dry Run (5 min)
- Navigate to `migrate-legacy-roms.php`
- Review preview table
- Verify adapter auto-assignment

**Test 20**: Migration Execution (10 min)
- Click "Start Migration"
- Monitor progress
- Verify success message

**Test 21**: Attachment Creation (5 min)
- Check **Media → Library**
- Verify new attachments created
- Test download links

**Test 22**: ROM Post Creation (5 min)
- Check **Retro ROMs** or **ROM Library**
- Verify post titles, adapters, systems, checksums

**Test 23**: Duplicate Detection (3 min)
- Run migration again
- Verify all files skipped

**Test 24**: [nes] Shortcode Compatibility (2 min)
- Create page with `[nes]` shortcode
- Verify emulator loads and legacy notice displays

### Step 5: Test Reporting (30 minutes)
1. Open `TEST-REPORT-TEMPLATE.md`
2. Fill in all sections:
   - Executive Summary
   - Test Environment details
   - Detailed test results (✅/❌ for each test)
   - Issues Log (Critical/Major/Minor)
   - Performance metrics
   - Screenshots and logs
3. Calculate statistics:
   - Tests Passed / Failed / Skipped
   - Overall Status (PASS/FAIL/PARTIAL)
4. Save as `TEST-REPORT-YYYY-MM-DD.md`

---

## Common Issues & Troubleshooting

### Issue: Media Library Won't Open
**Symptoms**: Clicking "Upload ROM File" button does nothing
**Solution**:
- Check browser console for JavaScript errors
- Verify `wp.media` is loaded (WordPress Media Library scripts)
- Ensure jQuery is enqueued

### Issue: File Upload Rejected with Generic Error
**Symptoms**: All files rejected, error message unclear
**Solution**:
- Check PHP error log: `wp-content/debug.log`
- Verify file permissions: `/wp-content/uploads/` writable
- Check `upload_max_filesize` in `php.ini` (should be ≥ 10MB)

### Issue: Bulk Actions Not Working
**Symptoms**: Bulk action dropdown doesn't submit, no changes occur
**Solution**:
- Verify nonce is present (check page source for `_wpnonce` field)
- Check browser console for JavaScript errors
- Ensure admin URL is correct (no mixed HTTP/HTTPS)

### Issue: Migration Script 404 Error
**Symptoms**: Navigating to migration script returns 404
**Solution**:
- Verify file exists: `/wp-content/plugins/wp-retro-emulator-gamification-bridge/migrate-legacy-roms.php`
- Check file permissions (readable)
- Try accessing via full URL: `http://campaign-forge.local/wp-content/plugins/wp-retro-emulator-gamification-bridge/migrate-legacy-roms.php`

### Issue: Checksum Always Empty
**Symptoms**: Metadata extraction fails, checksum field blank
**Solution**:
- Verify `md5_file()` function available (PHP)
- Check file permissions (readable)
- Ensure file path is correct (not relative)

### Issue: Playwright Browser Lock
**Symptoms**: "Browser is already in use" error
**Solution**:
- Kill all Chrome/Playwright processes:
  ```bash
  pkill -9 -f "chrome.*playwright"
  ```
- Restart Playwright MCP server
- Use `--isolated` flag if needed

---

## Test Data Cleanup

### After Testing
1. **Delete Test ROMs** (optional):
   ```sql
   DELETE FROM wp_posts WHERE post_type = 'retro_rom';
   DELETE FROM wp_postmeta WHERE post_id NOT IN (SELECT ID FROM wp_posts);
   ```

2. **Reset Migration**:
   - Delete ROM posts created during migration
   - Optionally remove attachments from Media Library

3. **Restore Database** (if backup created):
   ```bash
   wp db import backup-before-testing.sql
   ```

### Keep for Reference
- Test ROM files (for regression testing)
- Screenshots of working features
- Test report document

---

## Success Criteria

### Phase 2 (Upload Infrastructure)
- ✅ All file validations work correctly (extension, MIME, size)
- ✅ Metadata auto-extracts (checksum, file size)
- ✅ Replace/remove functionality works
- ✅ No JavaScript errors in console
- ✅ No PHP errors in logs

### Phase 3 (ROM Library Admin)
- ✅ List table displays all data correctly
- ✅ Sorting and filtering work
- ✅ All bulk actions complete successfully
- ✅ Drag-and-drop uploader creates ROM posts
- ✅ Contextual help tabs display correctly
- ✅ User experience is intuitive and smooth

### Phase 6 (Migration)
- ✅ Migration script imports legacy ROMs
- ✅ Attachments created in Media Library
- ✅ ROM posts populated with metadata
- ✅ Duplicate detection works (no re-imports)
- ✅ [nes] shortcode maintains compatibility

### Overall Plugin Health
- ✅ No JavaScript console errors
- ✅ No PHP errors in debug log
- ✅ All WordPress admin notices display correctly
- ✅ Database queries optimized (< 50 queries per page)
- ✅ Page load times acceptable (< 3 seconds)

---

## Next Steps After Testing

1. **If All Tests Pass**:
   - Mark phases as tested in `ROADMAP.md`
   - Update `TESTING_SCHEDULE.md` with test run date and status
   - Create final test report: `TEST-REPORT-YYYY-MM-DD.md`
   - Proceed to Phase 9 (Testing & Documentation) - security audit

2. **If Critical Issues Found**:
   - Log all issues in test report
   - Create GitHub issues for each bug
   - Fix critical issues first (blockers)
   - Re-test failed scenarios
   - Update test report with re-test results

3. **If Partial Pass**:
   - Prioritize major issues
   - Fix and re-test incrementally
   - Document known issues in release notes
   - Decide on release readiness (conditional release?)

---

## Resources

### Documentation
- `ROADMAP.md` - Project phases and features
- `CLAUDE.md` - Technical documentation for developers
- `MIGRATION.md` - Migration guide for users
- `RELEASE-NOTES.md` - v0.1.0 release notes

### Testing Files
- `TESTING_SCHEDULE.md` - Weekly cadence and MCP helpers
- `TEST-EXECUTION-CHECKLIST.md` - Detailed test steps (24 tests)
- `TEST-REPORT-TEMPLATE.md` - Test report template
- `TESTING-GUIDE.md` - This file

### MCP Helpers (when Playwright working)
```bash
# Navigation
mcp run admin:navigate --page="rom-library"

# File Upload
mcp run rom:upload --file="path/to/rom.nes"
mcp run rom:validate --file="malicious.exe"

# Bulk Actions
mcp run rom:bulk-action --action=delete --roms="1,2,3"
mcp run rom:bulk-action --action=publish --roms="4,5"

# Migration
mcp run rom:migrate --path=/wp-content/uploads/retro-game-emulator
mcp run migration:execute --verify-attachments

# Emulator Testing
mcp run emulator:load-rom --rom="bombsweeper"
mcp run rest:event --type=game_start --rom=1
```

---

## Contact & Support

**Issues**: Report bugs at https://github.com/nielowait/WP-Retro-Emulator-Gamification-Bridge/issues

**Questions**: Refer to `CLAUDE.md` for technical details and API documentation

**Updates**: Check `ROADMAP.md` for latest project status and upcoming features

---

**Happy Testing! 🎮**
