# unstoppable-restaurant

> This file is for Claude Code sessions working on this repo. For a human-oriented overview see `README.md`.

## What this plugin does

Restaurant ordering foundation for WooCommerce: menus (CPT + shortcode), Classic / Modern Tabs / Minimal layouts, cart drawer, conversational modifiers, product labels, sorting and basic menu scheduling. Printing, automation and messaging live in the separate Pro plugin, which extends this one only through hooks and filters.

- **Main PHP file:** `unstoppable-restaurant.php`
- **Code prefix:** `URST_` (constants / class prefix); `urst_` (lowercase for options / tables)
- **Package:** `Unstoppable_Restaurant`
- **WordPress.org slug:** `unstoppable-restaurant`
- **WP.org URL:** https://wordpress.org/plugins/unstoppable-restaurant/

## Base44 Pro mapping

- **Base44 app:** _(none yet — fill in when a Pro version is scaffolded there)_
- When updating code that affects both the free (this repo) and the Base44 Pro app, apply the matching change in both places in the same session.

## Release workflow

Releases are deployed to WordPress.org SVN automatically by GitHub Actions. The workflow is identical across every `unstoppable-solutions/*` plugin:

1. Finish feature work on `main`.
2. Bump the version in **all** of these:
   - `unstoppable-restaurant.php` — `Version:` header
   - `unstoppable-restaurant.php` — `URST_VERSION` constant (if present)
   - `readme.txt` — `Stable tag:`
   - `readme.txt` — add a `== Changelog ==` entry
3. Commit and push `main`.
4. On GitHub, create a new **Release** tagged `vX.Y.Z` (semver) with the changelog in the body.
5. The `deploy-to-wporg.yml` workflow fires and pushes the release to WP.org SVN.

## CI

Every push and PR runs the official WordPress Plugin Check against the codebase (`.github/workflows/plugin-check.yml`). CI must pass before merging. The deploy workflow (`.github/workflows/deploy-to-wporg.yml`) only fires on published Releases.

**Secrets used by deploy:** `SVN_USERNAME`, `SVN_PASSWORD` — org-level secrets on `unstoppable-solutions`. Nothing per-repo.

Both workflows carry an `if: ${{ ! github.event.repository.is_template }}` guard, so they are inert on the template itself (where the template's placeholder tokens would otherwise fail Plugin Check parsing).

## Project ground rules

Read `docs/UNSTOPPABLE_RESTAURANT_CANONICAL_SPEC.md` before writing code. It wins any conflict. Short version:

- Free must work fully without Pro. No printing code in Free, ever.
- Never block checkout; side effects are async.
- No heavy logic on every page load. Performance regressions block a release.
- No site-specific hacks.

## Hard-won lessons (read before touching cart, assets or admin CSS)

- **Minified assets load by default.** `Assets.php` enqueues `*.min.js` / `*.min.css` unless `SCRIPT_DEBUG` is on. Any edit to `frontend.js`, `admin.js` or a CSS file must also update its `.min` twin, or the change never reaches a live site. (This caused the v2.18.17-2.18.19 "fixes didn't work" loop.)
- **Shipping calculation is the cart-drawer bottleneck.** Every handler that calls `CartDrawer::get_cart_html()` must wrap it in the four shipping filters at priority 9999 (`woocommerce_shipping_enabled`, `woocommerce_shipping_show_shipping_calculator`, `woocommerce_cart_ready_to_calc_shipping`, `woocommerce_cart_needs_shipping`) and remove them after. Distance-rate shipping plugins otherwise add 30-45s per cart action.
- **WP admin CSS overrides custom toggles.** The scheduling toggle uses a wrapper div with the switch and its text in separate `<label>`s. Don't put the text back inside the switch label.
- `chrome-extension://...` console errors and "message channel closed" promise errors on the user's test site come from browser extensions, not this plugin.

## Known phpcs:ignores specific to this plugin

_(none yet — add here as you ignore specific checks, one bullet per site, with the rationale.)_

## Shared conventions across the Unstoppable Solutions org

- **GPL-2.0-or-later** on everything.
- **One author** for Contributors: in `readme.txt`: `paramedic192` (Dave Stevens).
- **Tested up to** is bumped as WordPress ships each new .x.
- **Plugin Check CI config** (`.github/workflows/plugin-check.yml`) is standard across the org: `exclude-checks: late_escaping` + `exclude-files` lists the GitHub-only files (`.github`, `.distignore`, `.gitignore`, `.gitattributes`, `README.md`, `CLAUDE.md`, `SECURITY.md`, `CONTRIBUTING.md`, `CHANGELOG.md`).
- **`.distignore`** keeps GitHub-only files out of the WP.org zip (CI, docs, this file).
- **Prefer `phpcs:ignore` + inline rationale** over removing the ignored check from CI entirely — a specific ignore documents the decision at the call site; a check removal silently covers future code too.
- **Don't `-c user.email=` with a random identity** when committing — use `fieldmedics4@gmail.com` / `Dave Stevens` for authorship, with Claude as Co-Authored-By.

## Working with the plugin from Claude Code

- When Claude attaches this repo in a future session, this file loads automatically.
- Workflow for a bugfix / feature:
  1. Make the change on `main` (or a branch).
  2. Watch Plugin Check CI — do not merge red.
  3. For a release: follow the Release workflow section above.
- The free plugin and its Pro add-on live in **separate repos**. This repo is the free side; Pro lives in `Unstoppable-Solutions/unstoppable-restaurant-pro` (private) once created.

## Repository layout

`unstoppable-restaurant.php` is the bootstrap (PSR-4-style autoload under `includes/`: `Core/`, `Admin/`, `Menu/`, `Modules/<Module>/`), `templates/` holds theme-overridable views (`yourtheme/unstoppable-restaurant/...`), `docs/` holds the canonical spec (not shipped), `assets/` holds CSS/JS, `languages/` holds the `.pot`, `readme.txt` is the WP.org directory listing, `.github/` holds the CI and deploy workflows.
