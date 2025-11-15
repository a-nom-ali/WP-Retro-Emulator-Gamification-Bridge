# TESTING_SCHEDULE.md

Testing calendar for CLAUDE CLI (using MCP test helpers) to validate the WP Retro Emulator Gamification Bridge.
Keep this schedule updated as new features land.

**Last Updated**: January 2025 - Post Phase 2, 3, 6 completion
**Next Test Run**: Full E2E testing of upload infrastructure, admin UI, and migration

---

## Weekly Cadence

| Day | Focus | Tools | Notes |
| --- | --- | --- | --- |
| Monday | ROM Library Data Model (CPT, taxonomies, REST) | `composer run phpcs`, `wp cli eval` scripts, ROM MCP import helper | Verify new ROM entries persist metadata, taxonomies, and REST payloads |
| Tuesday | Emulator Frontend (shortcode, JSNES runtime, touch controls) | Playwright smoke via MCP, manual mobile emulator check | Load `[retro_emulator]` page, switch ROMs, confirm lifecycle events fire |
| Wednesday | Gamification REST pipeline (event endpoint, adapters, room hooks) | MCP REST harness hitting `/wp-json/gamify/v1/event` | Use synthetic payloads from MCP to ensure adapter transforms work |
| Thursday | Admin UI (ROM Library screen, migration utilities, bulk actions) | MCP-headless WP admin automation + Playwright | Test ROM Library admin page, upload interface, bulk operations |
| Friday | Regression sweep + Docs | `composer run test`, `composer run phpcs`, manual doc diff | Ensure ROADMAP/AGENTS/README reflect current state |

---

## Phase-Specific Test Suites

### Phase 2: ROM Upload Infrastructure ✅ (Ready for Testing)

**Test Scenarios**:

1. **Upload via Media Library**
   - Navigate to Add New ROM → ROM Upload meta box
   - Click "Upload ROM File" button
   - Select a .nes file from Media Library
   - Verify attachment ID saved to `_retro_rom_source`
   - Verify checksum and file size auto-populated

2. **File Validation - Valid Files**
   - Upload .nes file (24KB) → Should succeed
   - Upload .gba file (2MB) → Should succeed
   - Upload .zip file (arcade ROM) → Should succeed
   - Verify MIME type accepted (application/octet-stream, etc.)

3. **File Validation - Invalid Files**
   - Upload .exe file → Should reject (invalid extension)
   - Upload .txt file → Should reject (invalid MIME type)
   - Upload 15MB ROM → Should reject (exceeds 10MB limit)
   - Verify WP_Error messages display in admin

4. **Metadata Extraction**
   - Upload ROM file
   - Verify `_retro_rom_checksum` contains MD5 hash
   - Verify `_retro_rom_file_size` matches actual file size
   - Verify checksum is unique (md5_file() output)

5. **Replace ROM File**
   - Edit existing ROM with file attached
   - Click "Replace ROM File"
   - Select different file
   - Verify old attachment replaced
   - Verify metadata updated (new checksum, new size)

6. **Remove ROM File**
   - Edit ROM with file attached
   - Click "Remove" button
   - Verify attachment ID cleared
   - Verify metadata preserved (checksum, size remain)
   - Verify file still exists in Media Library (not deleted)

**Expected Results**: All uploads validate correctly, metadata auto-extracts, errors display user-friendly messages

---

### Phase 3: ROM Library Admin Page ✅ (Ready for Testing)

**Test Scenarios**:

1. **List Table Display**
   - Navigate to Gamify Bridge → ROM Library
   - Verify table shows all ROMs
   - Verify columns: Thumbnail, Title, Adapter, System, File Size, Status, Date
   - Verify emoji icons display for each adapter type
   - Verify pagination (20 per page by default)

2. **Sorting**
   - Click "Title" column header → Sort A-Z
   - Click again → Sort Z-A
   - Click "Date" column → Sort newest first
   - Click "File Size" column → Sort largest to smallest
   - Verify URL parameter `?orderby=title&order=asc`

3. **Filtering**
   - Select adapter from dropdown (e.g., "JSNES")
   - Click "Filter" button
   - Verify only ROMs with JSNES adapter displayed
   - Select system from dropdown (e.g., "NES")
   - Click "Filter" button
   - Verify only NES ROMs displayed

4. **Row Actions**
   - Hover over ROM title
   - Verify "Edit" link appears
   - Click "Edit" → Opens ROM edit screen
   - Click "Delete" → Shows confirmation dialog
   - Confirm → ROM deleted permanently
   - Click "View" → Opens ROM permalink in new tab

5. **Bulk Actions - Delete**
   - Select 3 ROMs via checkboxes
   - Choose "Delete" from Bulk Actions dropdown
   - Click "Apply"
   - Verify ROMs deleted permanently
   - Verify success notice: "3 ROMs deleted."

6. **Bulk Actions - Publish**
   - Create draft ROM or set existing ROM to draft
   - Select ROM via checkbox
   - Choose "Publish" from Bulk Actions
   - Click "Apply"
   - Verify ROM status changed to "Published"
   - Verify success notice: "1 ROM published."

7. **Bulk Actions - Draft**
   - Select published ROM
   - Choose "Set to Draft" from Bulk Actions
   - Click "Apply"
   - Verify ROM status changed to "Draft"
   - Verify success notice: "1 ROM set to draft."

8. **Bulk Actions - Change Adapter**
   - Select 2 ROMs with JSNES adapter
   - Choose "Change Adapter" from Bulk Actions
   - Click "Apply"
   - Verify modal appears with adapter dropdown
   - Select "GBA.js" adapter
   - Click "Change Adapter" button
   - Verify both ROMs now have GBA adapter
   - Verify success notice: "2 ROM adapters changed."

9. **Drag-and-Drop Uploader**
   - Scroll to top of ROM Library page
   - Verify upload area displays (dashed border)
   - Click "Select ROM Files" button
   - Verify WordPress Media Library opens
   - Select multiple ROM files (3 files)
   - Click "Import ROMs"
   - Verify page refreshes
   - Verify 3 new ROM posts created (check list table)

10. **Contextual Help**
    - Click "Help" tab in top-right corner
    - Verify 4 help tabs display:
      - Overview
      - Uploading ROMs
      - Bulk Actions
      - Migration
    - Click each tab → Verify content displays correctly
    - Verify help sidebar with quick links

11. **Screen Options**
    - Click "Screen Options" in top-right
    - Change "ROMs per page" to 10
    - Click "Apply"
    - Verify pagination shows 10 ROMs per page

12. **Empty State**
    - Delete all ROMs or use fresh install
    - Navigate to ROM Library
    - Verify message: "No ROMs found. Upload your first ROM to get started!"

**Expected Results**: All list table features work correctly, bulk actions process successfully, UI is responsive and user-friendly

---

### Phase 6: Migration & Legacy Removal ✅ (Ready for Testing)

**Test Scenarios**:

1. **Migration Script - Dry Run**
   - Place 6 test ROMs in `/wp-content/uploads/retro-game-emulator/`
   - Navigate to migration script URL (via browser or MCP)
   - Verify preview table shows all 6 files
   - Verify adapter auto-assignment (e.g., .nes → JSNES)
   - Verify file sizes displayed correctly
   - Verify "Ready" status for supported files

2. **Migration Script - Execute**
   - Click "▶️ Start Migration" button
   - Wait for completion
   - Verify success message: "Imported 6 ROMs."
   - Verify no errors displayed
   - Verify skipped files listed (if any)

3. **Migration - Attachment Creation**
   - Navigate to Media Library
   - Verify 6 new attachments created
   - Verify file names match original ROMs
   - Verify MIME types correct (application/octet-stream)
   - Verify files accessible via attachment URL

4. **Migration - ROM Post Creation**
   - Navigate to Retro ROMs admin
   - Verify 6 new ROM posts created
   - Verify titles auto-generated from filenames
   - Verify adapters assigned correctly
   - Verify systems assigned (taxonomy terms)
   - Verify checksums populated
   - Verify file sizes populated

5. **Migration - Duplicate Detection**
   - Run migration script again (same files)
   - Verify all files skipped
   - Verify message: "already imported - matching checksum"
   - Verify no duplicate ROMs created

6. **[nes] Shortcode Compatibility**
   - Create page with `[nes]` shortcode
   - Visit page on frontend
   - Verify emulator interface loads
   - Verify legacy notice displays (migration warning)
   - Verify ROM dropdown populated
   - Verify ROM plays correctly

**Expected Results**: Migration completes successfully, attachments created, ROM posts populated with metadata, duplicate detection works, [nes] shortcode maintains compatibility

---

## Release Checklist Sessions

### 1. Pre-beta (Before v1.0 release)
- ✅ Run full MCP browser suite (desktop + mobile)
- ✅ Validate `[nes]` shim logs deprecation notice
- ✅ Confirm ROM migration imports all legacy files with attachments
- 🆕 Test ROM upload infrastructure end-to-end
- 🆕 Test ROM Library admin page with all bulk actions
- 🆕 Verify drag-and-drop uploader works in all browsers
- 🆕 Test contextual help tabs display correctly

### 2. Beta Sign-off (Before public release)
- End-to-end test: upload ROM → assign adapter → load emulator → trigger event → receive gamification reward
- QA Gutenberg block insertion + render on staging
- 🆕 Full bulk actions test (delete, publish, draft, change adapter)
- 🆕 Migration script test with 50+ ROMs
- 🆕 Performance test: List table with 100+ ROMs

### 3. General Availability (v1.0 release)
- Security scan (nonces, caps) via MCP audit scripts
- Backup/restore test to ensure ROM CPT survives deployments
- 🆕 File upload security test (attempt malicious file uploads)
- 🆕 MIME type validation test (attempt bypassing with crafted files)
- 🆕 File size limit test (attempt uploading oversized files)
- 🆕 Accessibility audit (keyboard navigation, screen reader compatibility)

---

## Current Test Run (January 2025)

### Focus Areas
1. **ROM Upload Infrastructure** (Phase 2)
   - Media Library integration
   - File validation (MIME, extension, size)
   - Metadata auto-extraction
   - Replace/remove functionality

2. **ROM Library Admin Page** (Phase 3)
   - WP_List_Table display
   - Sorting and filtering
   - Bulk actions (all 4 types)
   - Drag-and-drop uploader
   - Contextual help

3. **Migration System** (Phase 6)
   - Legacy ROM import with attachments
   - Duplicate detection
   - [nes] shortcode compatibility

### Test Environment
- WordPress: 6.4+
- PHP: 8.1+
- Browser: Chrome/Firefox (latest)
- Tools: Playwright MCP, WordPress MCP, Manual QA

### Success Criteria
- ✅ All file validations work correctly
- ✅ Metadata extracts accurately (checksum, size)
- ✅ List table displays all data correctly
- ✅ All bulk actions complete successfully
- ✅ Drag-and-drop uploader creates ROM posts
- ✅ Migration script imports legacy ROMs
- ✅ No JavaScript errors in console
- ✅ No PHP errors in logs
- ✅ User experience is intuitive and smooth

---

## MCP Helpers

### Existing Helpers
- `mcp run emulator:load-rom --rom="<slug>"` — loads ROM via headless browser and confirms canvas updates
- `mcp run rom:migrate --path=<dir>` — dry-runs migration to check metadata auto-fill
- `mcp run rest:event --type=game_start --rom=<id>` — fires REST payload and validates gamification response

### New Helpers (Phase 2/3/6)
- `mcp run rom:upload --file="path/to/rom.nes"` — tests upload via admin interface
- `mcp run rom:bulk-action --action=delete --roms="1,2,3"` — tests bulk operations
- `mcp run rom:validate --file="malicious.exe"` — tests file validation rejection
- `mcp run admin:navigate --page="rom-library"` — navigates to ROM Library admin page
- `mcp run migration:execute --verify-attachments` — runs migration and verifies attachment creation

---

## Test Execution Log

### Test Run #1: January 15, 2025
**Tested**: Phase 4 frontend emulator
**Status**: ✅ PASSED (100% event submission success after jQuery fix)
**Report**: TEST-REPORT-2025-01-15.md

### Test Run #2: January 2025 (Current)
**Testing**: Phase 2, 3, 6 (upload, admin UI, migration)
**Status**: 🚧 IN PROGRESS
**Report**: TEST-REPORT-2025-01-[DATE].md (to be created)

---

> **CLAUDE**: Update this schedule whenever cadence shifts or new MCP scripts appear. Always run full regression before marking phases complete.
