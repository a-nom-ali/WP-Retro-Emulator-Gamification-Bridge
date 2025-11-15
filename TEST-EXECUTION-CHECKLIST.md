# Test Execution Checklist - Phase 2, 3, 6

**Test Date**: January 2025
**Phases Tested**: Phase 2 (Upload Infrastructure), Phase 3 (ROM Library Admin), Phase 6 (Migration)
**Tester**: _____________
**Environment**: Local (campaign-forge.local)

---

## Pre-Test Setup

- [ ] WordPress site accessible at http://campaign-forge.local
- [ ] WP Gamify Bridge plugin activated
- [ ] Admin access confirmed (username/password working)
- [ ] Test ROM files available:
  - [ ] BombSweeper.nes (24KB)
  - [ ] Elite-PD.nes (128KB)
  - [ ] Other test ROMs in `/wp-content/uploads/retro-game-emulator/`
- [ ] Browser console open for JavaScript error checking
- [ ] PHP error log monitored (if applicable)

---

## Phase 2: ROM Upload Infrastructure

### Test 1: Upload via Media Library
**Steps**:
1. Navigate to **Retro ROMs → Add New**
2. Scroll to **ROM Upload** meta box in sidebar
3. Click **"Upload ROM File"** button
4. WordPress Media Library should open
5. Select `BombSweeper.nes` from media or upload new file
6. Click **"Use this ROM"**
7. Save/Publish the ROM post

**Expected Results**:
- [ ] Media Library opens correctly
- [ ] File can be selected
- [ ] Success message appears: "ROM file selected. Save post to apply changes."
- [ ] After saving, verify in **Edit ROM**:
  - [ ] `_retro_rom_source` contains attachment ID (check post meta or ROM details)
  - [ ] Checksum field populated with MD5 hash
  - [ ] File Size field shows correct size (e.g., 24 KB)

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 2: File Validation - Valid Files
**Steps**:
1. Upload .nes file (24KB) → Should succeed
2. Upload .gba file (if available) → Should succeed
3. Upload .zip file (arcade ROM, if available) → Should succeed

**Expected Results**:
- [ ] All valid file types accepted
- [ ] MIME types `application/octet-stream`, `application/zip` accepted
- [ ] Files under 10MB accepted without issue

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 3: File Validation - Invalid Files
**Steps**:
1. Attempt to upload .exe file → Should reject
2. Attempt to upload .txt file → Should reject
3. Attempt to upload 15MB+ file → Should reject

**Expected Results**:
- [ ] .exe rejected with error: "Invalid file extension"
- [ ] .txt rejected with error: "Invalid file type"
- [ ] Oversized file rejected with error: "File size exceeds maximum allowed"
- [ ] Error messages display in WordPress admin notices (red error boxes)

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 4: Metadata Auto-Extraction
**Steps**:
1. Upload ROM file via upload meta box
2. Save ROM post
3. Check ROM Details meta box

**Expected Results**:
- [ ] **Checksum** field contains 32-character MD5 hash
- [ ] **File Size** field shows file size in bytes or KB
- [ ] Checksum is unique (not empty, not all zeros)
- [ ] File size matches actual file size

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 5: Replace ROM File
**Steps**:
1. Edit existing ROM with file attached
2. In **ROM Upload** meta box, current ROM should display
3. Click **"Replace ROM File"**
4. Select different ROM file
5. Save post

**Expected Results**:
- [ ] Current ROM displays with name, size, checksum, download link
- [ ] "Replace ROM File" button appears
- [ ] After replacement, new file details display
- [ ] Checksum updated to new file's MD5
- [ ] File size updated to new file's size

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 6: Remove ROM File
**Steps**:
1. Edit ROM with file attached
2. Click **"Remove"** button in upload meta box
3. Confirm removal (if prompted)
4. Save post

**Expected Results**:
- [ ] "Remove" button appears when file attached
- [ ] Confirmation dialog appears: "Remove this ROM file?"
- [ ] After removal, upload meta box shows: "No ROM file uploaded yet."
- [ ] Attachment ID cleared from post meta
- [ ] File still exists in **Media → Library** (not deleted)
- [ ] Checksum and file size preserved in post meta

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

## Phase 3: ROM Library Admin Page

### Test 7: List Table Display
**Steps**:
1. Navigate to **Gamify Bridge → ROM Library**
2. Verify table renders

**Expected Results**:
- [ ] Page loads without errors
- [ ] Table displays with columns:
  - [ ] Checkbox (for bulk selection)
  - [ ] Thumbnail (emoji icons: 🎮 for NES, 📱 for GBA, etc.)
  - [ ] Title (ROM name)
  - [ ] Adapter (e.g., "JSNES (NES)", "GBA.js")
  - [ ] System (taxonomy terms like "NES", "GBA")
  - [ ] File Size (formatted, e.g., "24 KB")
  - [ ] Status ("Published", "Draft", color-coded)
  - [ ] Date Added (with "X ago" subtitle)
- [ ] All ROMs display correctly
- [ ] Pagination shows if > 20 ROMs (default 20 per page)

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 8: Sorting
**Steps**:
1. On ROM Library page, click **"Title"** column header
2. Verify sort order (A→Z)
3. Click **"Title"** again → Verify sort order (Z→A)
4. Click **"Date"** column → Verify newest first
5. Click **"File Size"** column → Verify largest to smallest

**Expected Results**:
- [ ] Clicking column headers changes sort order
- [ ] Arrow indicator shows sort direction (↑ or ↓)
- [ ] URL updates with `?orderby=title&order=asc`
- [ ] Table re-renders with correct sort order

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 9: Filtering
**Steps**:
1. Select **adapter** from dropdown (e.g., "JSNES (NES)")
2. Click **"Filter"** button
3. Verify only JSNES ROMs displayed
4. Reset filters
5. Select **system** from dropdown (e.g., "NES")
6. Click **"Filter"**
7. Verify only NES system ROMs displayed

**Expected Results**:
- [ ] Adapter dropdown populated with all adapters
- [ ] System dropdown populated with all taxonomy terms
- [ ] Filtering works correctly (only matching ROMs shown)
- [ ] "Filter" button triggers filter action
- [ ] Page count updates after filtering

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 10: Row Actions
**Steps**:
1. Hover mouse over ROM title in list
2. Verify row actions appear
3. Click **"Edit"** → Should open ROM edit screen
4. Return to ROM Library
5. Click **"Delete"** → Should show confirmation
6. Confirm → ROM should be deleted
7. Click **"View"** (on published ROM) → Should open frontend in new tab

**Expected Results**:
- [ ] Row actions visible on hover: Edit | Delete | View/Preview
- [ ] "Edit" opens post edit screen
- [ ] "Delete" shows JavaScript confirmation: "Are you sure?"
- [ ] Confirming delete removes ROM permanently
- [ ] "View" opens ROM permalink (published ROMs)
- [ ] "Preview" appears for draft ROMs

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 11: Bulk Action - Delete
**Steps**:
1. Select **3 ROMs** via checkboxes
2. From **Bulk Actions** dropdown, choose **"Delete"**
3. Click **"Apply"**
4. Verify deletion

**Expected Results**:
- [ ] Checkboxes selectable
- [ ] Bulk Actions dropdown shows "Delete" option
- [ ] After clicking "Apply", ROMs deleted permanently
- [ ] Success notice displays: "3 ROMs deleted."
- [ ] Table refreshes without deleted ROMs

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 12: Bulk Action - Publish
**Steps**:
1. Create or set ROM to **Draft** status
2. Select ROM via checkbox
3. Choose **"Publish"** from Bulk Actions
4. Click **"Apply"**

**Expected Results**:
- [ ] "Publish" option available in Bulk Actions
- [ ] After applying, ROM status changes to "Published"
- [ ] Success notice: "1 ROM published."
- [ ] Status column shows green "Published" text

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 13: Bulk Action - Draft
**Steps**:
1. Select **published** ROM via checkbox
2. Choose **"Set to Draft"** from Bulk Actions
3. Click **"Apply"**

**Expected Results**:
- [ ] "Set to Draft" option available
- [ ] ROM status changes to "Draft"
- [ ] Success notice: "1 ROM set to draft."
- [ ] Status column shows gray "Draft" text

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 14: Bulk Action - Change Adapter
**Steps**:
1. Select **2 ROMs** with JSNES adapter
2. Choose **"Change Adapter"** from Bulk Actions
3. Click **"Apply"**
4. **Modal should appear** with adapter dropdown
5. Select **"GBA.js"** adapter
6. Click **"Change Adapter"** button in modal

**Expected Results**:
- [ ] "Change Adapter" option available in Bulk Actions
- [ ] Modal overlay appears (dark background)
- [ ] Modal contains:
  - [ ] Adapter dropdown with all adapters
  - [ ] "Change Adapter" submit button
  - [ ] "Cancel" button
- [ ] After submitting, both ROMs updated to GBA adapter
- [ ] Success notice: "2 ROM adapters changed."
- [ ] Adapter column shows "GBA.js" for both ROMs

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 15: Drag-and-Drop Uploader
**Steps**:
1. Scroll to top of ROM Library page
2. Verify upload area displays (dashed border box)
3. Click **"Select ROM Files"** button
4. WordPress Media Library opens
5. Select **3 ROM files** (multi-select)
6. Click **"Import ROMs"**
7. Page refreshes
8. Verify new ROMs created

**Expected Results**:
- [ ] Upload area visible at top with:
  - [ ] Heading: "Upload ROM Files"
  - [ ] Description: "Drag and drop ROM files here, or click to browse."
  - [ ] Button: "Select ROM Files"
  - [ ] Format reminder: "Supported: NES, SNES, GBA... (32 formats, 10MB max)"
- [ ] Clicking button opens Media Library
- [ ] Multiple files can be selected
- [ ] After import, page refreshes
- [ ] 3 new ROM posts created in list table
- [ ] Success notice: "3 ROMs uploaded successfully." (if implemented)

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 16: Contextual Help
**Steps**:
1. On ROM Library page, click **"Help"** tab (top-right)
2. Help panel slides down
3. Click each help tab to view content

**Expected Results**:
- [ ] "Help" tab clickable in top-right corner
- [ ] Help panel displays with 4 tabs:
  - [ ] **Overview** - Describes ROM Library features
  - [ ] **Uploading ROMs** - Supported formats, size limits, drag-drop instructions
  - [ ] **Bulk Actions** - Explains delete, publish, draft, change adapter
  - [ ] **Migration** - Migration guide with steps
- [ ] Help sidebar displays with quick links:
  - [ ] "Edit ROMs (Standard View)"
  - [ ] "Plugin Dashboard"
- [ ] All help content readable and accurate

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 17: Screen Options
**Steps**:
1. Click **"Screen Options"** tab (top-right, near "Help")
2. Change **"ROMs per page"** to **10**
3. Click **"Apply"**
4. Verify pagination

**Expected Results**:
- [ ] "Screen Options" tab clickable
- [ ] Panel shows "ROMs per page" input field
- [ ] Can change value (e.g., 5, 10, 50, 100)
- [ ] After applying, table shows selected number of ROMs per page
- [ ] Pagination updates (e.g., "1 of 3" pages if 25 total ROMs, 10 per page)

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 18: Empty State
**Steps**:
1. Delete all ROMs OR test on fresh WordPress install
2. Navigate to **Gamify Bridge → ROM Library**

**Expected Results**:
- [ ] Empty table displays message:
  - [ ] "No ROMs found. Upload your first ROM to get started!"
- [ ] No error messages
- [ ] Upload area still visible for adding first ROM

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

## Phase 6: Migration & Legacy Removal

### Test 19: Migration Script - Dry Run
**Steps**:
1. Ensure test ROMs exist in `/wp-content/uploads/retro-game-emulator/`:
   - BombSweeper.nes
   - Elite-PD.nes
   - (Other ROMs if available)
2. Navigate to migration script:
   ```
   http://campaign-forge.local/wp-content/plugins/wp-retro-emulator-gamification-bridge/migrate-legacy-roms.php
   ```
3. Review preview table

**Expected Results**:
- [ ] Migration page loads with heading: "Legacy ROM Migration (Enhanced)"
- [ ] Explanation text displays:
  - [ ] Creates WordPress Attachments
  - [ ] Creates retro_rom posts with metadata
- [ ] Preview table shows:
  - [ ] All ROM files from directory
  - [ ] Filename, Size, Adapter, Systems, Status columns
  - [ ] Adapter auto-assigned (e.g., .nes → JSNES)
  - [ ] File sizes correct (e.g., 24 KB, 128 KB)
  - [ ] Status shows "Ready" for supported files
- [ ] "Found X files" notice displayed
- [ ] Pre-migration checklist visible:
  - [ ] Back up database
  - [ ] Ensure files exist
  - [ ] Supported extensions listed
  - [ ] Creates attachment + ROM post + taxonomy terms
  - [ ] Duplicate detection via MD5

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 20: Migration Script - Execute
**Steps**:
1. On migration script page, click **"▶️ Start Migration"**
2. Wait for completion (may take a few seconds)
3. Review results

**Expected Results**:
- [ ] Migration runs without errors
- [ ] Success message: "Migration complete. Imported X ROMs."
- [ ] If files skipped, displays:
  - [ ] "Skipped files:" with list and reasons (e.g., "already imported", "unsupported extension")
- [ ] If errors, displays:
  - [ ] "Errors:" with list of failed files and error messages
- [ ] Links provided:
  - [ ] "View ROM Library →"
  - [ ] "Back to Plugin Dashboard →"

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 21: Migration - Attachment Creation
**Steps**:
1. After migration, navigate to **Media → Library**
2. Look for newly created attachments

**Expected Results**:
- [ ] X new attachments created (matching number of migrated ROMs)
- [ ] Filenames match original ROMs (e.g., "BombSweeper.nes", "Elite-PD.nes")
- [ ] MIME types show as:
  - [ ] `application/octet-stream` OR
  - [ ] Other valid MIME types from allowed list
- [ ] Attachments accessible (can click to view details)
- [ ] Download links work (can download ROM file)

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 22: Migration - ROM Post Creation
**Steps**:
1. Navigate to **Retro ROMs** (or **Gamify Bridge → ROM Library**)
2. Verify new ROM posts created

**Expected Results**:
- [ ] X new ROM posts created (matching migrated files)
- [ ] Post titles auto-generated from filenames:
  - [ ] "BombSweeper.nes" → "Bomb Sweeper"
  - [ ] "Elite-PD.nes" → "Elite PD"
- [ ] Adapters assigned correctly:
  - [ ] .nes files → JSNES adapter
  - [ ] .smc files → jSNES (SNES) adapter
  - [ ] .gba files → GBA.js adapter
- [ ] Systems assigned (taxonomy terms):
  - [ ] .nes → "NES" term
  - [ ] .gba → "GBA" term
- [ ] Checksums populated (MD5 hashes, 32 characters)
- [ ] File sizes populated (in bytes, e.g., 24576 for 24KB)
- [ ] All posts in "Published" status

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 23: Migration - Duplicate Detection
**Steps**:
1. Run migration script again (same files, already migrated)
2. Review skipped files

**Expected Results**:
- [ ] All files skipped with message: "already imported - matching checksum"
- [ ] No duplicate ROM posts created
- [ ] No new attachments created
- [ ] Migration completes quickly (detects duplicates early)

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

### Test 24: [nes] Shortcode Compatibility
**Steps**:
1. Create new page or post
2. Add shortcode: `[nes]`
3. Publish page
4. Visit page on frontend

**Expected Results**:
- [ ] Emulator interface loads correctly
- [ ] **Legacy notice displays** at top:
  - [ ] Message about migrating to `[retro_emulator]`
- [ ] ROM dropdown populated with all ROMs
- [ ] Can select ROM and play
- [ ] Touch controls toggle works
- [ ] Emulator functions identically to `[retro_emulator]` shortcode

**Status**: ⬜ PASS / ⬜ FAIL
**Notes**: _____________________________________________

---

## Cross-Browser Testing (Optional)

**Browsers to Test**:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

**Features to Verify**:
- [ ] ROM Library page loads correctly
- [ ] Bulk actions work
- [ ] Drag-and-drop uploader works
- [ ] Modals display properly
- [ ] No JavaScript console errors

---

## Performance Testing (Optional)

**Large Dataset Test**:
1. Create/import 100+ ROMs
2. Test ROM Library page:
   - [ ] Loads in < 3 seconds
   - [ ] Pagination works smoothly
   - [ ] Sorting/filtering responsive
   - [ ] Bulk actions complete in < 5 seconds

---

## Security Testing (Optional)

**File Upload Security**:
- [ ] Attempt .exe upload → Rejected
- [ ] Attempt .php upload → Rejected
- [ ] Attempt 100MB file → Rejected
- [ ] Attempt SQL injection in ROM title → Sanitized
- [ ] Attempt XSS in ROM notes → Sanitized

**Nonce Verification**:
- [ ] Bulk actions without nonce → Rejected
- [ ] Upload without nonce → Rejected

---

## Test Summary

**Total Tests**: 24
**Passed**: ______
**Failed**: ______
**Skipped**: ______

**Critical Issues Found**:
1. _____________________________________________
2. _____________________________________________
3. _____________________________________________

**Minor Issues Found**:
1. _____________________________________________
2. _____________________________________________
3. _____________________________________________

**Overall Status**: ⬜ PASS / ⬜ FAIL / ⬜ PARTIAL

**Tester Signature**: ____________________________
**Date**: ____________________________

---

## Next Steps

- [ ] Fix critical issues found
- [ ] Re-test failed scenarios
- [ ] Create bug reports for issues
- [ ] Update test report with final results
- [ ] Mark phases as tested in ROADMAP.md
