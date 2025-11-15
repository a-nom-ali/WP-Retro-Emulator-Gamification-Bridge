# OPEN_QUESTIONS.md

Please fill out this form so we can continue the Retro Game Emulator integration with the right context.

## Site Inventory
1. **Where is this plugin deployed?** (dev/staging/prod URLs)
A: currently we are developing on a dev server - local wordpress. Accessible through http://campaign-forge.local with 'admin' as both username and password.
 
2. **Approximate number of posts/pages using `[nes]` (or other emulator shortcodes)?**
A: currently only in http://campaign-forge.local/contact-us/ for testing

3. **Current ROM storage location(s) besides `/retro-game-emulator/` (if any)?**
A: Currently we have the old plugin installed, but will be removing it. roms are located in the uploads folder - the full current path is "/Users/nielowait/Local Sites/campaign-forge/app/public/wp-content/uploads/retro-game-emulator"

4. **Do you have a list of ROM titles/systems that must be migrated first?**
A: We have a few test ROMS, and also created one called piggypoo.new, which is currently raising an error in the browser - but we'll get back to that. There are working nes roms in there though.

## Legal & Licensing
5. **Are all ROM uploads cleared/licensed for internal use?** (Yes/No + details) 
A: Yes - sourced from public domain and open source soures.
6. **Should we enforce upload restrictions (e.g., MD5 whitelist, size cap)?** 
A: Let's not limit our options yet, we can always add restrictions later

## Emulator Experience
7. **Which additional emulator platforms are top priority after NES?**
A: Priorities according to common ROM availability - those emulators with the highest known coverage of available ROMS should enjoy priority.
8. **Any custom control layouts or accessibility requirements we should replicate?**
A: Use known layouts that work well on mobile.
9. **Preferred behavior for on-screen touch controls (auto-show, toggle button, sensitivity settings)?**
A: A toggle button should be provided on desktop, auto-show on mobile. We could add a gear button for customizable settings such as sensitivity settings, or changing keys on desktop.
10. **Do you require save-state support or cloud sync in this release?**
A: Hmmm. Feels like it should be included if it makes sense at this point, but if we believe it would make mors sense post-implementation, go with that.

## Gamification & Rooms
11. **Must new ROM sessions always attach to an existing room, or can we auto-provision temporary rooms?**
A: We can auto-provision temporary rooms if not attached to existing rooms.
12. **Any special gamification rules (XP multipliers, badge names) tied to specific ROMs?**
A: We can have the option of adding per rom gamification rules when adding roms, with default gamification rules when no customization is defined.
13. **Should emulator events broadcast to all rooms or only the originating room by default?**
A: Follow-up Question: What do you think makes most sense?
   - **Response:** Recommend defaulting to the originating room to avoid cross-room noise; provide a per-ROM or per-event override that can broadcast globally for marquee events.
A: Agreed.

## Admin Workflow
14. **Who can manage ROM uploads (role/capability)?**
A: Admin and editors.
15. **Do you need bulk import/export tooling (CSV, JSON, WP-CLI)?**
A: Follow-up Question: What kind of imports do you invision with this question?
   - **Response:** Thinking about workflows such as uploading a CSV/JSON that lists ROM metadata + file paths, or a WP-CLI command to register dozens of ROMs at once (useful when migrating libraries or syncing from external archives). Exports would let you pull that same metadata for backups or migrations.
A: Agreed, good idea.
16. **Preferred metadata fields on ROM records (cover art, difficulty, release year, publisher, notes, etc.)?**
A: All of those, and any other relevat metadata fields you can think of.

## Migration & Rollout
17. **Target timeline for removing `/retro-game-emulator/` from the repo/site?**
A: As soon as we've integrated all features from the plugin.
18. **Should we ship a compatibility shim for `[nes]` users, and for how long?**
A: No, this is all happening locally and isn't in production yet. We can go nuts.
19. **Any environments where we can test large ROM libraries or high concurrency?**
A: Follow-up Question: Provide more detial on this question?
   - **Response:** Looking for any staging/demo setup where we can seed hundreds of ROM records or simulate many concurrent players to validate performance; if none exist we may spin up synthetic load locally.
A: We may spin up synthetic load locally
20. **Other blockers, dependencies, or approvals required before proceeding?**
A: Nope, we're all good, as I said, to go nuts. This project is still being developed.

> Add any clarifications below:
- 
