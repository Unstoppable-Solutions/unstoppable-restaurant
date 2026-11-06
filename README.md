# Unstoppable Restaurant for WooCommerce

Restaurant ordering foundation for WooCommerce: menus, premium layouts, a fast cart drawer, conversational modifiers, product labels, sorting and menu scheduling.

This is the **free** plugin. Printing, automation and messaging live in the separate Pro plugin (`unstoppable-restaurant-pro`, private repo), which extends this one only through hooks and filters. The free plugin works fully on its own.

## Features

- **Menus** as a custom post type, rendered with `[unstoppable_menu]`
- **Layouts:** Classic (sidebar categories), Modern Tabs (sticky tabs), Minimal (one category at a time, inline CSS)
- **Cart drawer** with quantity controls, built to skip shipping calculations so cart actions stay fast
- **Conversational modifiers:** step-by-step product customization (cheese → cook temp → toppings → side → notes)
- **Product labels** (Spicy, Vegan, etc.)
- **Category and product sorting**
- **Menu scheduling:** days of week, time window, date range and priority, with a preview tester

## Requirements

- WordPress 5.8+
- WooCommerce 7.0+
- PHP 7.4+

## Development

- Project rules: [`docs/UNSTOPPABLE_RESTAURANT_CANONICAL_SPEC.md`](docs/UNSTOPPABLE_RESTAURANT_CANONICAL_SPEC.md). Read it before changing architecture.
- Notes for Claude Code sessions: [`CLAUDE.md`](CLAUDE.md)
- Every push and PR runs WordPress Plugin Check. Don't merge red.
- When you edit a `.js` or `.css` file in `assets/`, update its `.min` twin too. The site loads the minified files unless `SCRIPT_DEBUG` is on.

## Releasing

1. Bump `Version:` and `URST_VERSION` in `unstoppable-restaurant.php`, and `Stable tag:` in `readme.txt`.
2. Add a changelog entry in `readme.txt`.
3. Push `main`, then publish a GitHub Release tagged `vX.Y.Z`. The deploy workflow attaches `unstoppable-restaurant.zip` to the release and, once the plugin is on WordPress.org, pushes to SVN.

## License

GPL-2.0-or-later.
