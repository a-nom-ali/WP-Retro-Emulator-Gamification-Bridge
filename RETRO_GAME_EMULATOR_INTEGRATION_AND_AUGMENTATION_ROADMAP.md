# RETRO_GAME_EMULATOR_INTEGRATION_AND_AUGMENTATION_ROADMAP.md

A focused plan for folding the legacy **Retro Game Emulator** plugin into WP Retro Emulator Gamification Bridge, modernizing it, and expanding multi-emulator coverage. Treat every phase as additive—finish prerequisites before removing the standalone folder.

---

## Phase 1 — Audit & Stabilize ✅
**Status:** COMPLETE. Findings captured in `REFACTOR-STATUS.md` + OPEN_QUESTIONS.md. `[nes]` usage documented (single test page), ROM storage mapped, and security gaps logged for remediation.

**Remaining Action:** None.

---

## Phase 2 — Data Model & Storage Upgrade (In Progress)
**Objective:** Introduce first-party ROM management that plays nicely with adapters and gamification data.
- ✅ `retro_rom` CPT + taxonomies (`retro_system`, `retro_difficulty`, `retro_multiplayer_mode`) registered with REST support.
- ✅ Post meta schema covers adapter slug, ROM source, checksum, file size, release year, publisher, gamification overrides, touch/control profiles, save-state flag.
- ✅ Initial migration utility (`migrate-legacy-roms.php`) ingests NES/SNES/GBA/GB/GBC files from `/uploads/retro-game-emulator`, assigning adapters + taxonomy terms automatically.
- ✅ REST surface (`GET /gamify/v1/roms`, `/roms/{id}`) feeds frontend pickers/editor via `WP_Gamify_Bridge_Rom_Library_Service`.
- ⏳ TODO: Add attachment-based uploads + MIME/size enforcement, WP-CLI equivalent, and signed URL streaming for ROM downloads.

**Deliverables Remaining:**
- Hardened upload flow + attachment linking.
- CLI/admin bulk import/export tooling.
- Security review log (caps/nonces/file validation).

---

## Phase 3 — Admin Experience & Workflow (In Progress)
**Objective:** Replace the dated `options.php` page with a modern ROM library UI.
- ✅ `admin/class-rom-library.php` adds ROM meta boxes, list-table columns, adapter dropdowns, and sanitation hooks.
- ⏳ TODO: Dedicated ROM Library admin screen (list table + bulk actions), drag-and-drop uploader, contextual help, and migration warnings surfaced inside wp-admin.
- ⏳ TODO: Inline adapter/system metadata tooltips referencing `WP_Gamify_Bridge_Emulator_Manager`.

---

## Phase 4 — Frontend Emulator Surface ✅ (COMPLETE with fixes)
**Objective:** Embed ROM playback within our bridge so events, rooms, and gamification automatically intertwine.
- ✅ `[retro_emulator]` shortcode + `[nes]` shim render canvas, ROM picker, metadata panel, responsive touch controls, and status messaging (`templates/shortcodes/retro-emulator.php` + CSS enhancements).
- ✅ New JS runtime (`retro-emulator-player.js` + vendored JSNES) handles ROM loading, audio/video loops, keyboard + touch controls, and dispatches lifecycle events.
- ✅ Gutenberg block (`wp-gamify/retro-emulator`) exposes the same UI in the editor with ROM dropdown + touch toggle settings.
- ✅ Script enqueuer auto-localizes ROM metadata and loads assets when a page uses the shortcode/block.
- ✅ **TESTED:** ROM loading & playback verified (BombSweeper running with full NES graphics).
- ✅ **TESTED:** Touch controls toggle working correctly (D-pad, A/B, Start/Select buttons).
- ✅ **TESTED:** Event submission to REST API working (100% success after fixes).
- ✅ **TESTED:** Gamification integration verified (XP awards displaying correctly).
- ✅ **FIXED:** Replaced jQuery AJAX with fetch API (jQuery incompatibility causing 404s).
- ✅ **FIXED:** Removed invalid room_id from event payload (was causing "Room not found" 404s).
- ✅ Manual QA checklist completed via Playwright - full test report in `TEST-REPORT-2025-01-15.md`.
- ⏳ TODO: Save/load state hooks, fullscreen toggle, pause/resume UI, and richer ROM filtering/search (REST-backed).

---

## Phase 5 — Adapter + Gamification Enhancements
**Objective:** Guarantee every ROM/emulator combination automatically publishes rich event data.
- Extend each adapter to declare supported file extensions, save-state support, control mappings, default score multipliers, and optional setup instructions. Expose through `get_adapters_metadata()`.
- Enhance `js/emulator-hooks.js` to register per-ROM hooks (example: JSNES hooking when a specific ROM loads, GBA-specific events, etc.).
- Map emulator lifecycle events to gamification payloads (level, stage, boss, high score, badge) with consistent schema so REST endpoint + integrations can respond uniformly.
- Add analytics/telemetry: store per-ROM play counts, average session time, and failure reasons. Display inside dashboard.

**Deliverables:**
- Adapter metadata updates + docs.
- Expanded JS hook coverage with tests/logging.
- Dashboard widgets for ROM engagement metrics.

---

## Phase 6 — Release & Legacy Removal
**Objective:** Ship the integrated experience and delete the redundant plugin folder.
- ✅ README/AGENTS/ROADMAP partially updated; continue syncing docs as phases complete.
- ⏳ TODO: Complete ROM migration (including attachments), verify `[nes]` shims across content, remove `/retro-game-emulator`, and publish migration/release notes.

**Deliverables:**
- Final PR removing the folder.
- Communication plan/migration guide for downstream sites.

---

## Cross-Cutting Requirements
- **Security:** Capability checks (`manage_options` or granular caps) + nonces everywhere. Validate uploads (MIME, extension, size) and generate signed URLs when exposing ROM downloads.
- **Performance:** Cache ROM listings, lazy load metadata, and avoid `scandir` in templates. Stream ROM bytes efficiently (range requests, gzip for JS, caching headers).
- **Accessibility:** Provide keyboard navigable controls for ROM selection, descriptive labels for control mappings, and focus management for canvas interactions.
- **Testing:** PHPUnit for PHP infrastructure, Playwright/Jest for frontend if possible. Provide manual QA checklist when automation impractical (ROM upload, playback, gamification events, room notifications).
- **Documentation:** Keep `README.md`, `ROADMAP.md`, `AGENTS.md`, and admin help screens aligned. Record migration steps and compatibility notes.

Completion of this roadmap unlocks future work like save-state cloud sync, spectator mode, and marketplace-driven ROM metadata imports.
