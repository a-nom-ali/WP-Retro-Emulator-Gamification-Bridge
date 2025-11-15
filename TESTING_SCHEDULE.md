# TESTING_SCHEDULE.md

Testing calendar for CLAUDE CLI (using MCP test helpers) to validate the WP Retro Emulator Gamification Bridge.
Keep this schedule updated as new features land.

## Weekly Cadence
| Day | Focus | Tools | Notes |
| --- | --- | --- | --- |
| Monday | ROM Library Data Model (CPT, taxonomies, REST) | `composer run phpcs`, `wp cli eval` scripts, ROM MCP import helper | Verify new ROM entries persist metadata, taxonomies, and REST payloads |
| Tuesday | Emulator Frontend (shortcode, JSNES runtime, touch controls) | Playwright smoke via MCP, manual mobile emulator check | Load `[retro_emulator]` page, switch ROMs, confirm lifecycle events fire |
| Wednesday | Gamification REST pipeline (event endpoint, adapters, room hooks) | MCP REST harness hitting `/wp-json/gamify/v1/event` | Use synthetic payloads from MCP to ensure adapter transforms work |
| Thursday | Admin UI (ROM Library screen, migration utilities) | MCP-headless WP admin automation | Run `migrate-legacy-roms.php`, check admin meta boxes, list columns |
| Friday | Regression sweep + Docs | `composer run test`, `composer run phpcs`, manual doc diff | Ensure ROADMAP/AGENTS/README reflect current state |

## Release Checklist Sessions
1. **Pre-beta** (before shipping new emulator UI)
   - Run full MCP browser suite (desktop + mobile).
   - Validate `[nes]` shim logs deprecation notice.
   - Confirm ROM migration imports all legacy files.
2. **Beta sign-off** (before deleting `retro-game-emulator/`)
   - End-to-end test: migrate ROM, load emulator, trigger event, receive gamification reward.
   - QA Gutenberg block insertion + render on staging.
3. **General Availability**
   - Security scan (nonces, caps) via MCP audit scripts.
   - Backup/restore test to ensure ROM CPT survives deployments.

## MCP Helpers
- `mcp run emulator:load-rom --rom="<slug>"` — loads ROM via headless browser and confirms canvas updates.
- `mcp run rom:migrate --path=<dir>` — dry-runs migration to check metadata auto-fill.
- `mcp run rest:event --type=game_start --rom=<id>` — fires REST payload and validates gamification response.

> CLAUDE: Update this schedule whenever cadence shifts or new MCP scripts appear.
