# AGENTS.md

Guidance for Codex engineers working inside `WP Retro Emulator Gamification Bridge`. Start here before making changes.

## Mission Snapshot
- **Goal:** Fuse JavaScript retro emulators with WordPress gamification flows (GamiPress, MyCred, rooms, broadcasting).
- **Immediate Focus:** Absorb the legacy `retro-game-emulator` plugin (5-year stale NES shortcode + ROM uploader) into this codebase, modernize it, then remove the standalone folder. Extend the system so any emulator/ROM surface automatically plugs into adapters, REST APIs, rooms, and reward pipelines.

## Tooling & Commands
```bash
composer install              # install PHP deps
composer run phpcs            # lint (WordPress-Core & -Docs)
composer run phpcbf           # autofix coding standards
composer run test             # phpunit suite
npm install && npm run build  # if you touch JS build steps (when added)
```
- Text domain: `wp-gamify-bridge`
- Minimums: PHP 7.4, WP 6.0
- Follow gitmoji commits (`✨`, `🐛`, `📝`, etc.). Commit after each discrete deliverable.
- Update `ROADMAP.md` whenever phases/tasks complete or shift.

## Architecture Cheat Sheet
1. `wp-gamify-bridge.php` boots everything (singleton) and loads files under `inc/` + `admin/`.
2. REST entry: `WP_Gamify_Bridge_Endpoint` and `WP_Gamify_Bridge_Room_Endpoint` handle events + room CRUD, using `WP_Gamify_Bridge_Event_Validator` and `WP_Gamify_Bridge_Rate_Limiter`.
3. Database: `WP_Gamify_Bridge_Database` manages tables `wp_gamify_events`, `wp_gamify_rooms`, etc.
4. Emulator adapters live in `inc/adapters/`. `WP_Gamify_Bridge_Emulator_Manager` registers adapters (JSNES, jSNES, GBA.js, MAME, RetroArch, EmulatorJS), transforms payloads, and localizes detection data for `js/emulator-hooks.js`.
5. Frontend bridge scripts: `js/emulator-hooks.js` (event dispatch, offline queue, detection) and `js/room.js` (presence + notifications). Enqueued via `WP_Gamify_Bridge_Script_Enqueuer` with localized player/room context.
6. Admin experience: `admin/class-admin-page.php` for rooms/logs, `admin/class-dashboard.php` for stats/settings/leaderboards/event tester.

## Legacy Plugin Analysis (retro-game-emulator)
- Provides `[nes]` shortcode with `<canvas>` + ROM `<select>`, powered by bundled `lib/jsnes.min.js` and `lib/app.js`.
- Relying on uploads dir `/wp-content/uploads/retro-game-emulator/` for raw `.nes` files, scanned synchronously via `scandir` on every page load. Inline `<script>` exposes ROM listing in `wp_head`.
- Settings page in `options.php` handles manual `.nes` uploads + delete links without capabilities beyond `administrator`, lacks modern sanitization, MIME checks, limits, or localization.
- JavaScript (`app.js`) wires a vanilla JSNES instance without our adapter hooks, no gamification triggers, no room awareness, no multi-emulator support.
- Tested-up-to WP 5.6 (per readme). No block, REST, or Gutenberg integration.

## Directive: Integrate & Augment Retro Emulator Functionality
1. **Create a First-Party ROM Library Module**
   - Introduce a `retro_rom` custom post type (or dedicated DB table) with metadata: emulator adapter, system, ROM source (upload/URL), checksum, default difficulty.
   - Provide admin UI that mirrors and supersedes the legacy options page (bulk upload, metadata editing, status flags, permission checks).
   - Migrate existing files from `/retro-game-emulator/` into the new storage strategy (attachment + taxonomy). Leave a CLI or WP-CLI migration helper.

2. **Frontend Experience Refresh**
   - Replace `[nes]` with a namespaced shortcode/block (`[retro_emulator id="..."]`) that renders:
     - Canvas/player host
     - Dynamic ROM picker (AJAX/rest-driven) with search, cover art, system filters
     - Emulator-specific control mapping hints.
   - Hook into `js/emulator-hooks.js` + adapters so ROM changes automatically propagate `emulator`, `system`, and metadata in each `triggerWPEvent()` payload.
   - Implement responsive on-screen touch controls that automatically appear on mobile/touch devices and mirror adapter control mappings.
   - Ensure canvas + input surface adopt our CSS naming + room data attributes for real-time sync.

3. **Adapter + Event Automation**
   - For each ROM entry, store the adapter slug so the emulator manager can auto-attach correct hooks, score multipliers, and event transformations.
   - Extend adapters to expose ROM capability metadata (supported file extensions, BIOS needs, default control layout). Use this when rendering UI and validating uploads.
   - Build a generic `EmulatorSession` JS helper that wraps JSNES/App.js style emulators and sends standardized lifecycle events (start, pause, resume, save-state) to the REST endpoint.

4. **Security & Performance Upgrades**
   - Enforce capability checks (`manage_options` or custom caps) and nonces on all ROM CRUD.
   - Stream ROMs via signed URLs or the REST API to prevent directory traversal. Consider chunked loading and caching headers.
   - Add validation and file-size/type limits. Optionally support zipped ROMs with server-side extraction + scanning.

5. **Deletion of Legacy Folder**
   - Once parity achieved, remove `/retro-game-emulator` directory. Document removal in release notes and provide migration instructions.

## Agent Execution Principles
- **Traceability:** Whenever you alter emulator behavior, update adapter docs + describe changes in `ROADMAP.md` and, if needed, create a changelog entry.
- **Isolation:** Build new modules under `inc/emulator/` or `admin/rom-library/` instead of patching legacy folder contents.
- **Testing:** Add PHPUnit coverage for new server-side logic (ROM CPT registration, upload handlers, migration helpers). For JS, add Playwright or Jest stubs where practical; otherwise document manual test steps in PR/test notes.
- **Progress Tracking:** Move items across the new `RETRO_GAME_EMULATOR_INTEGRATION_AND_AUGMENTATION_ROADMAP.md` when milestones complete. Keep CLAUDE + AGENTS aligned.
- **Docs First:** Update README/admin help tabs after adding user-facing features. Provide inline help text in admin pages describing new ROM workflow and gamification hooks.
- **Compatibility:** Maintain backwards compatibility for any existing `[nes]` shortcodes by mapping them onto the new shortcode/block via shim, emitting deprecation warnings in logs/admin notices.

## Checklist Before Submitting Work
- [ ] composer + phpcs run without errors
- [ ] Relevant tests added or updated
- [ ] ROADMAP + new integration roadmap reflect progress
- [ ] Legacy folder untouched unless migrating/removing files intentionally
- [ ] Gitmoji commit prepared

Document owners: Codex engineers. Mirror updates back to CLAUDE instructions whenever behavior or tooling changes.
