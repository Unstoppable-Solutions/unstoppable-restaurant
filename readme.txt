=== Unstoppable Restaurant for WooCommerce ===
Contributors: paramedic192
Donate link: https://unstoppablesolutions.app/donate
Tags: woocommerce, restaurant, food-ordering, menu, pickup
Requires at least: 5.8
Tested up to: 7.1
Stable tag: 2.18.20
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.5
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A next-generation WooCommerce restaurant and food ordering platform with premium layouts and conversational modifiers.

== Description ==

Unstoppable Restaurant transforms your WooCommerce store into a professional food ordering platform. With beautiful layouts, step-by-step product customization, and seamless checkout integration, you can start taking orders in minutes.

**Key Features:**

* **Premium Layouts** – Choose from Classic Menu or Modern Tabs layout with sticky navigation
* **Conversational Modifiers** – Guide customers through product options step-by-step, just like ordering in person
* **Sticky Cart Drawer** – Slide-in cart that updates in real-time without page refresh
* **Pickup & Delivery** – Built-in service type selection with date and time scheduling
* **Mobile-First Design** – Looks beautiful on all devices with smooth animations
* **WooCommerce Native** – Uses standard WooCommerce products and categories
* **Theme Compatible** – Works with any theme, with template override support

**Conversational Modifiers:**

This unique feature presents product options in the natural order a customer would be asked in person:

1. "Choose your cheese" – None / American / Cheddar / Swiss
2. "How would you like it cooked?" – Rare / Medium / Well Done  
3. "Add any toppings?" – Bacon (+$2) / Jalapeños / Extra Patty (+$4)
4. "Any special instructions?" – Free text field

**Display Your Menu:**

Simply add the shortcode `[unstoppable_menu]` to any page, or use the "Unstoppable Menu" block in the block editor.

To display a specific menu: `[unstoppable_menu menu="main-menu"]`

**Integrations:**

* Compatible with WooCommerce High-Performance Order Storage (HPOS)
* Works with both Classic and Block checkout
* Integrates with Unstoppable Time Slots Pro for advanced scheduling

== Installation ==

1. Upload the `unstoppable-restaurant` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Make sure WooCommerce is installed and activated
4. Go to Restaurant > Dashboard to configure your settings
5. Add the menu to any page using `[unstoppable_menu]` shortcode

== Frequently Asked Questions ==

= Does this work with any theme? =

Yes! Unstoppable Restaurant is designed to work with any properly coded WordPress theme. Templates can be overridden by copying them to your theme's `unstoppable-restaurant` folder.

= Can I customize the colors? =

Yes, you can customize the primary and accent colors from Restaurant > Settings. The plugin uses CSS variables, so you can also add custom CSS for more control.

= Does it support multiple locations? =

The free version supports a single location. Multi-location support is planned for a future Pro version.

= How do I create modifier flows? =

Go to Restaurant > Modifiers to create step-by-step customization flows. Assign them to specific products or entire categories.

= Is it compatible with WooCommerce Blocks? =

Yes! The plugin works with both the classic checkout and the new block-based checkout.

== Screenshots ==

1. Classic Menu layout with sidebar navigation
2. Modern Tabs layout with sticky horizontal navigation
3. Cart drawer with live updates
4. Conversational Modifiers step-by-step flow
5. Admin dashboard
6. Settings page

== Changelog ==

= 2.18.20 =
* FIXED: frontend.min.js was out of sync with frontend.js, so cart fixes never reached live sites
* ADDED: Startup debug logging in frontend JS

= 2.18.19 =
* FIXED: Add-to-cart was missing the shipping-calculation guard, causing 30-45s cart delays on Classic/Modern layouts
* CHANGED: Add-to-cart back on jQuery AJAX with a 15s timeout

= 2.18.15 - 2.18.18 =
* FIXED: Scheduling toggle label overlap and days-of-week picker click targets in menu admin
* FIXED: Shipping calculations blocked during cart drawer update and remove

= 2.18.10 - 2.18.14 =
* FIXED: Admin notices bleeding into the dashboard header
* FIXED: Undefined "name" key warning in layout picker
* ADDED: Cart performance diagnostics and loading-state timeout protection

= 2.18.2 =
* FIXED: Admin JavaScript not loading on modifier flow edit page (post.php)
* FIXED: Modifier groups and options now save correctly
* Root cause: Assets only loaded on edit.php (list), not post.php (edit single)

= 2.18.1 =
* FIXED: Dashboard now correctly counts modifier flows from CPT (was checking wrong option key)
* FIXED: Dashboard "Configure" button for Ordering Experience now links to correct page

= 2.18.0 =
* PHASE 7.8: Stability & Guardrails (final FREE core phase)
* NEW: Health Check system — automatic detection of configuration issues
* NEW: Admin warnings for common problems (dismissible, re-shows after 7 days)
* NEW: Detects missing WooCommerce, no menus, no products, missing shortcode
* NEW: Warns when multiple menus exist without scheduling rules
* NEW: Warns when sections are empty or menus have no categories
* IMPROVED: All warnings are non-blocking with helpful action links
* IMPROVED: Health check only runs on relevant admin pages (performance)
* STABILITY: Last major FREE plugin update before Pro development begins

= 2.17.0 =
* PHASE 7.7: Dashboard Intelligence (read-only insights)
* NEW: Restaurant Insights panel on dashboard
* NEW: Active menu display with time window (e.g., "Lunch Menu (11:00 – 15:00)")
* NEW: Menu summary showing configured vs. active menus
* NEW: Sections status across all menus
* NEW: Scheduling rules warning when multiple menus lack rules
* NEW: Modifier groups and product labels status
* NEW: Visual indicators (success/warning/info) for different insight types
* IMPROVED: Enhanced status data collection for better dashboard intelligence
* UI-ONLY: No logic changes, no configuration options added

= 2.16.3 =
* FIXED: Escape jQuery $ in JavaScript to prevent PHP variable interpolation warnings in MenuAdminPage

= 2.16.2 =
* FIXED: MenuRulesEngine method is get_best_menu(), not get_active_menu()

= 2.16.1 =
* FIXED: Fatal error - MenuRepository uses singleton pattern, must call instance() not new

= 2.16.0 =
* PHASE 7.6: Restaurant Control Center Dashboard
* NEW: Modern, task-oriented dashboard replaces old settings-heavy UI
* NEW: Status header showing ordering status, active menu, and channel
* NEW: Action cards for Menus, Scheduling, Ordering Experience, Appearance
* NEW: Quick Actions section with shortcodes, view menu, docs, diagnostics
* NEW: Live status indicator with pulse animation
* NEW: Copy-to-clipboard for shortcodes
* NEW: Auto-detect menu page for "View Menu" link
* NEW: System diagnostics panel (version info, theme, etc.)
* MOVED: Layout and display settings now under "Appearance" submenu
* IMPROVED: Clean hierarchy - Dashboard → Menus, Scheduling, Experience, Appearance
* UI-ONLY: No logic changes, no data model changes, no architectural changes

= 2.15.1 =
* FIXED: Undefined array key 'name' warning in menu list layout display

= 2.15.0 =
* PHASE 7.3.1: Sections Hardening
* NEW: Strict section schema sanitization (ids, labels, order, product_ids)
* NEW: Server-side validation - invalid entries safely ignored
* NEW: Graceful handling of deleted/missing products in sections
* NEW: `sections_dedupe_products` setting (default true) - prevents product duplication
* NEW: All layout templates (classic/tabs/minimal) have graceful fallback for empty data
* IMPROVED: Section data sanitized at MenuDefinition constructor level
* IMPROVED: Template documentation updated for sections support

* PHASE 7.5: Rules Admin UI
* NEW: "Scheduling Rules" section in Menu Edit screen
* NEW: Enable/disable toggle for scheduling
* NEW: Priority setting (1-100) for menu fallback ordering
* NEW: Day-of-week picker with visual toggles (Sun-Sat)
* NEW: Time window picker with quick presets (Breakfast/Lunch/Dinner/Late Night)
* NEW: Date range picker for seasonal menus
* NEW: "Preview at time" tester - client-side rule evaluation
* NEW: Visual feedback for active/inactive rule state
* NEW: Time presets: Breakfast (6-11), Lunch (11-15), Dinner (17-22), Late Night (22-02)
* IMPROVED: Rules UI disabled visually when scheduling is off

= 2.14.0 =
* PHASE 7.3: Menu Sections - Presentation Layer
* NEW: Menu Sections allow grouping products independently from Woo categories
* NEW: Sections are menu-owned (stored in MenuDefinition.settings.sections)
* NEW: Admin UI: Add/edit/delete sections with drag-to-reorder
* NEW: Admin UI: Multi-select product picker for each section
* NEW: Sections fallback to category rendering when not defined
* NEW: `get_display_groups()` method for unified category/section rendering
* NEW: `to_legacy_sections()` for template-friendly section format
* NEW: `has_sections` flag in template variables
* IMPROVED: MenuHtmlRenderer uses display groups for automatic section support
* FIXED: Duplicate MenuPayloadBuilder::build() call removed
* DATA MODEL: `sections = [{ id, label, description, product_ids, order, is_active }]`

= 2.13.2 =
* PHASE 7.4: Menu Context Enforcement HARDENING (CRITICAL)
* FIXED: Modal add-to-cart now preserves menu context (was losing context)
* FIXED: ModifierFlow add-to-cart now includes menu context from ProductModal
* FIXED: `menu_name` now included in context payload (was missing)
* NEW: ProductModal.captureMenuContext() - captures context when modal opens
* NEW: ProductModal.getMenuContext() - retrieves stored context
* CHANGED: Station data attribute now uses 'data-station' (backward compat maintained)
* CHANGED: MenuPayloadBuilder includes menu_name in menu_context
* CHANGED: Product card template outputs data-menu-name attribute
* IMPROVED: POST field handling includes menu_name and station mappings
* DOCUMENTED: Phase 7.4 contract comments in all affected files

= 2.13.1 =
* PHASE 7.4: Menu Context Enforcement (CRITICAL HARDENING)
* NEW: Canonical context key `urst_context` / `_urst_context`
* NEW: MenuCartIntegration class - THE SPINE of context flow
* NEW: Order-level summary meta `_urst_order_context` for printing/Live Orders
* NEW: Admin metabox showing context on order edit screen (7.4.7 Inspector)
* NEW: Debug mode via `URST_DEBUG_CONTEXT` constant
* NEW: Fallback context creation when no context provided
* NEW: `group_items_by_station()` for ticket routing
* NEW: `get_all_contexts_from_order()` for order analysis
* NEW: Programmatic order support via `ensure_order_item_context()`
* FIXED: Removed malformed `{` directories from zip
* HARDENED: Context is immutable after add-to-cart
* HARDENED: Context persists through cart session, page reload, AJAX

= 2.13.0 =
* ARCHITECTURE: Phase 7 - Menu Intelligence Layer
* PHASE 7.1: MenuRulesEngine - evaluates menu activation based on rules
* NEW: Day of week scheduling (menu active only on certain days)
* NEW: Time of day scheduling (menu active only during certain hours)
* NEW: Date range scheduling (menu active only within date range)
* NEW: Priority-based menu selection (fallback menus)
* PHASE 7.2: Multiple menus per page support
* NEW: [unstoppable_menus] shortcode for multiple menus
* NEW: get_active_menus() - returns all currently active menus
* NEW: get_best_menu() - returns highest priority active menu
* NEW: preview_at_time() - preview menu activation at any time
* PHASE 7.4: Menu Context Enforcement (CRITICAL)
* NEW: section_id field in MenuContext (Phase 7.3 ready)
* NEW: Default context fallback when no context provided
* NEW: _urst_order_menu_summary order meta for printing/Live Orders
* NEW: group_items_by_station() for ticket routing
* NEW: get_all_contexts_from_order() for order analysis
* NEW: ensure_order_item_context() for programmatic orders
* IMPROVED: Context is now immutable after add-to-cart
* IMPROVED: Context persists through cart session, page reload, AJAX
* IMPROVED: Admin order display shows section, station, channel badges
* FOUNDATION: Ready for Live Orders and Printing integration

= 2.12.0 =
* ARCHITECTURE: Phase 6.4 - MenuPayload + MenuPayloadBuilder
* NEW: MenuPayloadBuilder - THE BRAIN of the menu system
* NEW: Flat payload schema: {menu, categories, products, meta}
* NEW: Products reference category_id (not nested in categories)
* REFACTOR: MenuPayload rewritten to authoritative spec
* REFACTOR: MenuHtmlRenderer now uses MenuPayloadBuilder
* IMPROVED: Payload is now cacheable and API-ready
* IMPROVED: Station routing via products_by_station() method
* UNLOCKS: Live Orders (reuse payload)
* UNLOCKS: Printing (deterministic routing)
* UNLOCKS: REST API (serializable payload)
* UNLOCKS: Caching (flat structure)

= 2.11.1 =
* FIX: Sticky tabs navigation now stays visible when clicking categories
* FIX: Scroll observer disabled during programmatic scroll to prevent conflicts
* FIX: Active tab updates immediately on click for better UX
* NEW: --ur-sticky-offset CSS variable for themes with fixed headers
* CSS: Added overflow:visible to prevent sticky breaking in tabs layout

= 2.11.0 =
* ARCHITECTURE: Phase 6.3 - MenuDefinition Domain Model
* NEW: CPT-based menu storage (urst_menu)
* NEW: MenuPostType class for CPT registration
* NEW: MenuRepository now uses CPT with post meta
* NEW: Automatic migration from wp_options to CPT
* REFACTOR: MenuDefinition uses int ID (post ID)
* REFACTOR: Simpler schema: category_ids array + settings blob
* UNLOCKS: WordPress REST API for menus
* UNLOCKS: Standard WordPress data model
* Backward compatible with existing menus (auto-migrated)

= 2.10.0 =
* ARCHITECTURE: Phase 6.2 - Strict separation of Data and HTML
* NEW: MenuHtmlRenderer class - THE ONLY HTML entry point
* REFACTOR: MenuRenderer now ONLY builds payloads, never renders HTML
* REFACTOR: Shortcode calls MenuHtmlRenderer::render()
* NEW: render_from_payload() for cached/API payload rendering
* UNLOCKS: Menu JSON API, Live Orders, Printing, KDS
* No functional changes - HTML output identical

= 2.9.2 =
* COMPLETE: Menu → Cart Contract fully implemented
* Product cards now output menu context as data attributes
* Frontend JS captures menu context on add-to-cart
* Layout templates pass menu context to product cards
* Minimal layout updated with menu context data attributes
* Fixed AJAX handler conflict (CartDrawer handles, filter captures)
* Menu context flows: menu → card → POST → cart item → order item

= 2.9.1 =
* FIX: Global settings now properly control layout selection
* FIX: Show Images/Prices/Descriptions settings now work correctly
* FIX: Layout changes in settings now affect all menus immediately
* CHANGE: Global settings take priority over per-menu layout settings

= 2.9.0 =
* FOUNDATIONAL: Menu → Cart Contract implemented
* NEW: MenuContext - carries menu/category/station through order lifecycle
* NEW: ProductPayload - structured product data with context attached
* NEW: CategoryPayload - category data with station hints
* NEW: MenuPayload - complete menu output as data structure
* NEW: MenuCartIntegration - hooks that transfer context to cart/order
* NEW: Station hints per category (kitchen, bar, counter, expo)
* NEW: Channel tracking (web, qr, kiosk, pos)
* NEW: AJAX add-to-cart endpoint with context preservation
* NEW: Order item meta shows menu context in admin
* ARCHITECTURE: Data-first rendering (data structures before HTML)
* ARCHITECTURE: MenuRenderer.build_payload() separates data from presentation
* TECHNICAL: Every product now carries full context for routing and printing

= 2.8.2 =
* SCHEMA UPDATE: MenuDefinition now matches authoritative specification
* NEW: Categories stored as objects with term_id, order, include_children, hidden
* NEW: Status field (active/draft) for menu definitions
* NEW: Channel-based visibility (web, qr, kiosk, pos) - future ready
* NEW: Role-based visibility (customer, kitchen, counter) - future ready
* NEW: Filters: only_available, hide_out_of_stock, price_range, labels_required
* NEW: Sorting: category_order (manual/wc), product_order (manual/price/popularity)
* IMPROVED: Backward compatible - old category ID arrays auto-convert
* TECHNICAL: Meta uses created_at/updated_at strings (MySQL datetime format)

= 2.8.1 =
* NEW: Menu Admin UI at Restaurant → Menus
* NEW: Create, edit, delete menu definitions
* NEW: Visual layout selector with radio buttons
* NEW: Dual-list category picker with drag-to-reorder
* NEW: Auto-generated slugs for new menus
* NEW: Default menu protection (cannot be deleted)
* IMPROVED: Shortcode display in menu list table

= 2.8.0 =
* NEW: Menu Definitions System - reusable menu configurations
* NEW: MenuDefinition entity with full settings storage
* NEW: MenuRepository for CRUD operations on menu definitions
* NEW: MenuRenderer class - all query/render logic in one place
* NEW: MenuShortcode - thin wrapper that loads menu by slug
* NEW: Default menu auto-created on activation
* NEW: Support for [unstoppable_menu menu="my-menu"] shortcode
* IMPROVED: Category order is now preserved from menu definition
* IMPROVED: Better separation of concerns - no queries in templates
* TECHNICAL: Foundation for Menu Admin UI (coming next)

= 2.7.4 =
* REDESIGNED: Professional admin settings page with modern two-column layout
* NEW: Layout selection cards with visual icons and checkmarks
* NEW: Toggle switches for display options instead of basic checkboxes
* NEW: Category selection pills with product counts
* NEW: Quick Start sidebar card with shortcode
* NEW: Help and Pro upsell sidebar cards
* IMPROVED: Color picker styling
* IMPROVED: Gold brand color (#f0b849) for menu icon

= 2.7.3 =
* FIXED: "Invalid post type" error on Modifiers page - post type now registered immediately during module init, not via hook that fires after init has already started

= 2.7.2 =
* FIXED: "Invalid post type" error when clicking Modifiers menu - post type now registered earlier
* FIXED: Admin menu icon now uses gold color (#f0b849) per Unstoppable Solutions branding
* FIXED: Menu Order page now properly loads jQuery UI Sortable for drag-drop functionality

= 2.7.1 =
* REDESIGNED: Minimal layout now matches Orderable style
* Sidebar categories on left (pill buttons)
* Product list on right (not grid)
* Shows one category at a time
* "Select" button for each product
* Mobile responsive (tabs wrap horizontally on small screens)
* Sticky sidebar on desktop

= 2.7.0 =
* NEW: Minimal (Lightweight) layout option
* Inline CSS - no external stylesheet dependencies
* Shows one category at a time (better performance)
* Simple tab switching (no scroll-based navigation)
* Minimal JavaScript footprint
* Best for sites with many products

= 2.6.4 =
* ADDED: URST_SIMPLE_MODE - renders plain HTML list without templates for debugging
* ADDED: URST_NO_JS - alias for URST_DISABLE_JS

= 2.6.3 =
* FIXED: Category filter settings were not being applied to frontend
* FIXED: LayoutRenderer now reads saved category settings from database
* FIXED: ProductQuery now properly filters by selected categories
* FIXED: Cache key now includes category filter for proper caching

= 2.6.2 =
* FIXED: Tab panels not visible due to theme/plugin CSS conflicts
* ADDED: Inline !important styles to force visibility of tab panels and product grids
* FIXED: Products not showing under category tabs

= 2.6.1 =
* FIXED: Category tab clicking was extremely laggy
* CHANGED: Switched from smooth scroll to instant scroll (smooth + lazy images = lag)
* CHANGED: Throttled IntersectionObserver with requestAnimationFrame
* CHANGED: Reduced observer threshold for better performance
* IMPROVED: Active state updates immediately on click, not after scroll

= 2.6.0 =
* ADDED: URST_BARE_TEST mode - outputs minimal HTML to isolate problem
* To test: add define('URST_BARE_TEST', true); to wp-config.php
* If bare test loads fast but normal doesn't, problem is templates/CSS

= 2.5.9 =
* ADDED: URST_DISABLE_JS constant to completely disable frontend JS for debugging
* ADDED: To test, add to wp-config.php: define('URST_DISABLE_JS', true);
* CHANGED: JS now registered before enqueue for better control
* DEBUG: If page loads fast with JS disabled, problem is frontend JS

= 2.5.8 =
* ADDED: Re-entrancy guard to prevent infinite shortcode recursion
* ADDED: Debug logging when WP_DEBUG is enabled
* ADDED: Timing measurements for performance diagnosis
* FIXED: Shortcode now properly resets state on completion
* DEBUG: Check debug.log for "URST:" entries to diagnose lag

= 2.5.7 =
* MAJOR PERFORMANCE FIX: Single SQL query for all products + categories
* FIXED: Eliminated N+1 query problem completely
* FIXED: Labels no longer trigger per-product database queries
* FIXED: All post meta batch-loaded with update_postmeta_cache()
* FIXED: Attachment URLs pre-cached with _prime_post_caches()
* IMPROVED: Bypasses WooCommerce product objects for speed
* IMPROVED: Direct SQL query is 10-50x faster than wc_get_products()

= 2.5.6 =
* REWRITE: Complete rewrite of product query system
* FIXED: Switched from wc_get_products to WP_Query with 'fields' => 'ids' for speed
* FIXED: Simplified transient key naming
* FIXED: Removed variable product price range queries (expensive)
* IMPROVED: Get product IDs first, then load minimal data
* IMPROVED: Cache extended to 10 minutes
* IMPROVED: Simpler, more reliable caching logic

= 2.5.5 =
* FIXED: Major performance issue - switched to transient caching (persists in database)
* FIXED: Translation timing notice (removed early __() calls)
* FIXED: Menu data now cached for 5 minutes across all page loads
* ADDED: update_termmeta_cache() to batch-load term meta
* IMPROVED: Cache invalidation properly deletes transients from database

= 2.5.4 =
* FIXED: Products not displaying (regression from 2.5.3)
* FIXED: Reverted to per-category queries with result caching
* KEPT: 5-minute result caching for performance
* KEPT: Static cache for term meta during sorting
* KEPT: Automatic cache invalidation on changes

= 2.5.3 =
* FIXED: Major performance issue - menu page loading extremely slow
* FIXED: N+1 query problem - now uses single query for all products
* ADDED: Query result caching (5 minute TTL)
* ADDED: Automatic cache invalidation on product/category changes
* IMPROVED: Category sorting uses static cache to prevent repeated meta queries
* IMPROVED: Product-to-category mapping done in single database query

= 2.5.2 =
* FIXED: WordPress 6.7+ translation timing notice
* FIXED: Plugin now loads textdomain properly at init hook
* CHANGED: Plugin initialization moved from plugins_loaded to init hook
* COMPATIBILITY: Full WordPress 6.7+ compatibility

= 2.5.1 =
* PHASE 8 COMPLETE: Pro Scaffold (Foundation)
* NEW: Extension hook system for Pro modules
* NEW: `unstoppable_restaurant_register_pro_modules` action
* NEW: `unstoppable_restaurant_modules_loaded` action
* NEW: `ModuleManager::register_module()` public method for Pro extensions
* IMPROVED: Module system now supports external module registration
* DEVELOPER: Clean separation between Free and Pro plugin boundaries

= 2.5.0 =
* PHASE 7 COMPLETE: UX Polish + Visual Signals
* NEW: Product Labels system (Spicy, Vegan, Gluten-Free, Popular, New, Chef's Pick, Contains Nuts)
* NEW: Label assignment per product in WP Admin
* NEW: Labels display on product cards (emoji icons) and modal (with text)
* NEW: Menu Order admin page for drag-and-drop sorting
* NEW: Category ordering via custom term meta
* NEW: Product ordering within categories via menu_order
* NEW: "Sold Out" overlay on out-of-stock products
* NEW: Improved stock badge styling
* IMPROVED: Clearer microcopy throughout ("Your order is empty", "Browse the menu to get started")
* IMPROVED: Button labels ("Continue" vs "Next", "View Order" vs "View Cart")
* IMPROVED: Error messages ("Oops! Something went wrong")
* IMPROVED: Scheduling closed message ("We're currently closed for orders")
* IMPROVED: Empty cart icon (shopping bag instead of cart)
* IMPROVED: Category sorting respects custom order, then alphabetical
* IMPROVED: Product sorting respects menu_order
* FIXED: Visual consistency of labels and badges

= 2.4.0 =
* PHASE 6 COMPLETE: Basic Scheduling
* NEW: ScheduleManager API for all scheduling operations
* NEW: Service type selection (Pickup only, Delivery only, or both)
* NEW: Per-day operating hours with multiple time windows
* NEW: Separate hours for Pickup vs Delivery services
* NEW: Closures & blackout dates with optional reason
* NEW: Date range closures (e.g., holiday weeks)
* NEW: Admin settings page (Restaurant > Schedule)
* NEW: Frontend enforcement - blocks add-to-cart when closed
* NEW: Availability messages in product modal
* NEW: Overnight hours support (e.g., 10PM - 2AM)
* NEW: Global API: unstoppable_restaurant()->scheduling()
* NEW: ScheduleManager::is_open(), get_next_open_time(), get_availability_message()
* IMPROVED: Clear messaging for customers ("We're closed. Orders reopen Monday at 11:00 AM")
* IMPROVED: Tab-based admin UI for Pickup vs Delivery hours

= 2.3.1 =
* PHASE 5.5 COMPLETE: Harden & Normalize
* NEW: ModifierCartData class for versioned, normalized cart storage
* NEW: Backward compatibility with legacy modifier data formats
* NEW: Admin validation warnings for problematic modifier configurations
* NEW: Object caching support in ModifierRepository
* IMPROVED: Cart data now includes version, flow_id, flow_name, and timestamps
* IMPROVED: Modifiers survive product/flow updates (data is self-contained)
* IMPROVED: Kitchen output works even if flow is deleted
* IMPROVED: Better error messages with i18n support
* IMPROVED: Display summary works without re-querying flow definition
* FIXED: Cart never breaks even with orphaned modifier data
* FIXED: Price calculation from stored data, not re-queried
* FIXED: Legacy data auto-enriched when possible
* STABILITY: All modifier operations gracefully handle missing/changed flows

= 2.3.0 =
* PHASE 5 COMPLETE: Conversational Modifiers
* NEW: Step-by-step modifier flow in product modal
* NEW: Modifier Group data model (single/multiple selection types)
* NEW: ModifierOption with price adjustments and kitchen labels
* NEW: ModifierEngine for pure flow state management
* NEW: ModifierRepository for database storage/retrieval
* NEW: Admin UI to create modifier flows (WP Admin > Modifiers)
* NEW: Assign modifiers to products or categories
* NEW: Progress indicator in modal showing current step
* NEW: Required/optional validation per group
* NEW: Min/max selection limits for multiple-choice groups
* NEW: Running summary of selections in modal
* NEW: Live price updates as modifiers are selected
* NEW: Kitchen-readable output stored with order items
* NEW: Cart displays modifier selections inline
* NEW: Price adjustment calculation in cart totals
* IMPROVED: Modal hides quantity selector when modifiers present
* IMPROVED: Cart item meta properly stores modifier data
* IMPROVED: Order items receive kitchen text for printing

= 2.2.0 =
* PHASE 4 COMPLETE: Product Modal
* NEW: Click product card to open detail modal
* NEW: Modal displays product image, name, description, price
* NEW: Quantity selector with +/- buttons inside modal
* NEW: Dynamic total price updates based on quantity
* NEW: "Add to Order" button with price display
* NEW: Desktop: Centered modal with backdrop
* NEW: Mobile: Slide-up bottom sheet with drag handle
* NEW: Loading spinner while fetching product data
* NEW: Focus trap and ESC to close for accessibility
* NEW: Placeholder regions for future features (modifiers, labels, availability)
* IMPROVED: Cart integration - modal uses same AJAX logic as product cards
* IMPROVED: Modal closes automatically after adding to cart
* IMPROVED: Safe area support for mobile devices

= 2.1.0 =
* PHASE 3 COMPLETE: Cart Drawer
* NEW: Slide-in cart drawer (right on desktop, bottom sheet on mobile)
* NEW: Floating cart button with item count badge
* NEW: AJAX add-to-cart from product cards
* NEW: AJAX quantity updates without page reload
* NEW: AJAX item removal with loading states
* NEW: Toast notifications for cart actions
* NEW: Focus trap and ESC to close for accessibility
* NEW: Safe area support for mobile notch/home indicator
* IMPROVED: Mobile-first drawer with drag handle indicator
* IMPROVED: Loading states on buttons during AJAX operations
* IMPROVED: Cart reads directly from WooCommerce (no duplicate state)
* IMPROVED: Proper ARIA attributes and screen reader support
* FIXED: Removed inline styles from templates (now in CSS file)

= 2.0.0 =
* PHASE 2 COMPLETE: Layout Engine
* NEW: Centralized Layout Registry for extensibility
* NEW: ProductQuery class - single query layer for all product data
* NEW: LayoutRenderer handles all shortcode and block rendering
* NEW: Simplified admin UI - layout selection, category filter, display toggles
* NEW: Skeleton loader component for loading states
* IMPROVED: Product card is now a dumb render-only component
* IMPROVED: Clean separation of concerns - UI, data, and logic separated
* IMPROVED: CSS design system with comprehensive tokens
* REMOVED: Cart drawer, modifiers, scheduling (coming in Phase 3-5)
* REMOVED: Complex multi-tab admin (simplified to single page)

= 1.0.5 =
* Fixed sidebar category navigation display issues
* Added explicit CSS to prevent theme style conflicts
* Improved sidebar link styling with flexbox
* Added fallback values for category names

= 1.0.4 =
* Fixed template data structure mismatch - categories now display correctly in sidebar and tabs
* Templates now correctly access category data (name, slug, products)

= 1.0.3 =
* Fixed critical error: Updated old namespace references in template files (drawer.php, classic.php, tabs.php)
* Fixed module class names in ModuleManager to use proper namespace format

= 1.0.2 =
* Fixed critical error: Added missing leading backslashes for namespaced class references in global scope

= 1.0.1 =
* Updated to follow Unstoppable Solutions security and coding standards
* Changed prefix from UR_ to URST_ (4+ characters, unique)
* Changed namespace from Starter\UnstoppableRestaurant to Unstoppable\Restaurant
* Implemented single menu with tabs pattern (Dashboard, Settings, Modifiers)
* Added Admin Notices system (welcome, review request, flash notices)
* Enhanced security on all AJAX handlers with proper nonce/capability checks
* Added index.php files to all directories for security
* Updated plugin header for WordPress.org compliance
* Added readme.txt following WordPress.org standards

= 1.0.0 =
* Initial release
* Two premium layouts: Classic Menu and Modern Tabs
* Conversational Modifiers system
* Sticky cart drawer with AJAX updates
* Product modal for quick view
* Basic pickup/delivery scheduling
* Service type selection at checkout
* Date and time slot selection
* WooCommerce HPOS compatibility
* Unstoppable Time Slots Pro integration

== Upgrade Notice ==

= 1.0.3 =
Critical fix for PHP fatal error in templates. Please update immediately.

= 1.0.2 =
Critical fix for PHP fatal error. Please update immediately.

= 1.0.1 =
Security and standards update. Enhanced AJAX security, updated coding standards, and improved admin interface with tabbed navigation.

= 1.0.0 =
Initial release of Unstoppable Restaurant for WooCommerce.

== Credits ==

Developed by [Unstoppable Solutions](https://unstoppablesolutions.app)
