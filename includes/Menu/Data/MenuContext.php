<?php
/**
 * Menu Context.
 *
 * PHASE 7.4 AUTHORITATIVE - CANONICAL STRUCTURE (DO NOT MODIFY).
 *
 * This is the SINGLE SOURCE OF TRUTH for menu context.
 * Determined ONCE at add-to-cart. NEVER re-derived.
 *
 * Schema (LOCKED):
 * - menu_id       (int)    Menu post ID
 * - menu_slug     (string) Menu slug for shortcode reference
 * - menu_name     (string) Menu display name
 * - channel       (string) web | qr | kiosk | app
 * - category_id   (int)    WooCommerce category term ID (nullable)
 * - category_name (string) Category name for display (nullable)
 * - section_id    (string) Custom section identifier (future)
 * - section_name  (string) Section display name (future)
 * - captured_at   (int)    Unix timestamp when captured
 * - version       (string) Schema version for migrations
 *
 * RULES:
 * - No other keys
 * - No alternates
 * - No fallbacks elsewhere
 *
 * @package Unstoppable_Restaurant
 * @since 2.9.0
 */

namespace Unstoppable\Restaurant\Menu\Data;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MenuContext class.
 *
 * @since 2.9.0
 */
class MenuContext implements \JsonSerializable {

    /**
     * Schema version.
     */
    const VERSION = '1.0';

    /**
     * Meta key for cart items.
     */
    const CART_META_KEY = 'urst_context';

    /**
     * Meta key for order items.
     */
    const ORDER_ITEM_META_KEY = '_urst_context';

    /**
     * Meta key for order-level summary.
     */
    const ORDER_META_KEY = '_urst_order_context';

    /**
     * Menu definition ID (post ID).
     *
     * @var int
     */
    private $menu_id;

    /**
     * Menu slug for shortcode reference.
     *
     * @var string
     */
    private $menu_slug;

    /**
     * Menu display name.
     *
     * @var string
     */
    private $menu_name;

    /**
     * Channel: web | qr | kiosk | app
     *
     * @var string
     */
    private $channel;

    /**
     * Category term ID (nullable).
     *
     * @var int|null
     */
    private $category_id;

    /**
     * Category name (nullable).
     *
     * @var string|null
     */
    private $category_name;

    /**
     * Section ID (future).
     *
     * @var string|null
     */
    private $section_id;

    /**
     * Section name (future).
     *
     * @var string|null
     */
    private $section_name;

    /**
     * Station hint for routing: kitchen | bar | counter | expo | prep
     *
     * @var string|null
     */
    private $station;

    /**
     * Unix timestamp when context was captured.
     *
     * @var int
     */
    private $captured_at;

    /**
     * Schema version.
     *
     * @var string
     */
    private $version;

    /**
     * Constructor.
     *
     * @param array $data Context data.
     */
    public function __construct( array $data = array() ) {
        $this->menu_id       = isset( $data['menu_id'] ) ? absint( $data['menu_id'] ) : 0;
        $this->menu_slug     = isset( $data['menu_slug'] ) ? sanitize_title( $data['menu_slug'] ) : '';
        $this->menu_name     = isset( $data['menu_name'] ) ? sanitize_text_field( $data['menu_name'] ) : '';
        $this->channel       = $this->sanitize_channel( isset( $data['channel'] ) ? $data['channel'] : 'web' );
        $this->category_id   = isset( $data['category_id'] ) && $data['category_id'] ? absint( $data['category_id'] ) : null;
        $this->category_name = isset( $data['category_name'] ) && $data['category_name'] ? sanitize_text_field( $data['category_name'] ) : null;
        $this->section_id    = isset( $data['section_id'] ) && $data['section_id'] ? sanitize_key( $data['section_id'] ) : null;
        $this->section_name  = isset( $data['section_name'] ) && $data['section_name'] ? sanitize_text_field( $data['section_name'] ) : null;
        $this->station       = isset( $data['station'] ) ? $this->sanitize_station( $data['station'] ) : null;
        $this->captured_at   = isset( $data['captured_at'] ) ? absint( $data['captured_at'] ) : time();
        $this->version       = isset( $data['version'] ) ? sanitize_text_field( $data['version'] ) : self::VERSION;

        // Backward compat: station_hint -> station
        if ( ! $this->station && isset( $data['station_hint'] ) ) {
            $this->station = $this->sanitize_station( $data['station_hint'] );
        }
    }

    /**
     * Sanitize channel.
     *
     * @param string $channel Channel value.
     * @return string
     */
    private function sanitize_channel( $channel ) {
        $allowed = array( 'web', 'qr', 'kiosk', 'app' );
        $channel = sanitize_key( $channel );
        return in_array( $channel, $allowed, true ) ? $channel : 'web';
    }

    /**
     * Sanitize station.
     *
     * @param string|null $station Station value.
     * @return string|null
     */
    private function sanitize_station( $station ) {
        if ( null === $station || '' === $station ) {
            return null;
        }
        $allowed = array( 'kitchen', 'bar', 'counter', 'expo', 'prep' );
        $station = sanitize_key( $station );
        return in_array( $station, $allowed, true ) ? $station : null;
    }

    // === GETTERS ===

    public function get_menu_id() {
        return $this->menu_id;
    }

    public function get_menu_slug() {
        return $this->menu_slug;
    }

    public function get_menu_name() {
        return $this->menu_name;
    }

    public function get_channel() {
        return $this->channel;
    }

    public function get_category_id() {
        return $this->category_id;
    }

    public function get_category_name() {
        return $this->category_name;
    }

    public function get_section_id() {
        return $this->section_id;
    }

    public function get_section_name() {
        return $this->section_name;
    }

    public function get_station() {
        return $this->station;
    }

    /**
     * Backward compat alias.
     */
    public function get_station_hint() {
        return $this->station;
    }

    public function get_captured_at() {
        return $this->captured_at;
    }

    public function get_version() {
        return $this->version;
    }

    // === PREDICATES ===

    public function has_station() {
        return null !== $this->station;
    }

    /**
     * Backward compat alias.
     */
    public function has_station_hint() {
        return $this->has_station();
    }

    public function has_section() {
        return null !== $this->section_id;
    }

    public function is_bar_item() {
        return 'bar' === $this->station;
    }

    public function is_kitchen_item() {
        return 'kitchen' === $this->station || null === $this->station;
    }

    public function is_valid() {
        return ! empty( $this->menu_slug ) || $this->menu_id > 0;
    }

    // === SERIALIZATION ===

    /**
     * Convert to canonical array (EXACT SCHEMA).
     *
     * @return array
     */
    public function to_array() {
        return array(
            'menu_id'       => $this->menu_id,
            'menu_slug'     => $this->menu_slug,
            'menu_name'     => $this->menu_name,
            'channel'       => $this->channel,
            'category_id'   => $this->category_id,
            'category_name' => $this->category_name,
            'section_id'    => $this->section_id,
            'section_name'  => $this->section_name,
            'station'       => $this->station,
            'captured_at'   => $this->captured_at,
            'version'       => $this->version,
        );
    }

    /**
     * JSON serialization.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize() {
        return $this->to_array();
    }

    // === FACTORY METHODS ===

    /**
     * Create from array.
     *
     * @param array $data Context data.
     * @return MenuContext
     */
    public static function from_array( array $data ) {
        return new self( $data );
    }

    /**
     * Create from cart item.
     *
     * @param array $cart_item Cart item data.
     * @return MenuContext|null
     */
    public static function from_cart_item( array $cart_item ) {
        // Try new key first.
        if ( isset( $cart_item[ self::CART_META_KEY ] ) ) {
            return self::from_array( $cart_item[ self::CART_META_KEY ] );
        }
        // Backward compat: old key.
        if ( isset( $cart_item['urst_menu_context'] ) ) {
            return self::from_array( $cart_item['urst_menu_context'] );
        }
        return null;
    }

    /**
     * Create from order item.
     *
     * @param \WC_Order_Item_Product $item Order item.
     * @return MenuContext|null
     */
    public static function from_order_item( $item ) {
        // Try new key first.
        $data = $item->get_meta( self::ORDER_ITEM_META_KEY, true );
        if ( ! empty( $data ) && is_array( $data ) ) {
            return self::from_array( $data );
        }
        // Backward compat: old key.
        $data = $item->get_meta( '_urst_menu_context', true );
        if ( ! empty( $data ) && is_array( $data ) ) {
            return self::from_array( $data );
        }
        return null;
    }

    /**
     * Create a fallback context (when no context provided).
     *
     * Uses rules engine to get best active menu.
     *
     * @param string $channel Channel.
     * @return MenuContext
     */
    public static function create_fallback( $channel = 'web' ) {
        $data = array(
            'menu_id'       => 0,
            'menu_slug'     => 'default-menu',
            'menu_name'     => '',
            'channel'       => $channel,
            'category_id'   => null,
            'category_name' => null,
            'section_id'    => null,
            'section_name'  => null,
            'station'       => null,
            'captured_at'   => time(),
            'version'       => self::VERSION,
        );

        // Try to get best menu from rules engine.
        if ( class_exists( '\Unstoppable\Restaurant\Menu\MenuRulesEngine' ) ) {
            $rules = \Unstoppable\Restaurant\Menu\MenuRulesEngine::instance();
            $menu = $rules->get_best_menu( $channel );
            if ( $menu ) {
                $data['menu_id'] = $menu->get_id();
                $data['menu_slug'] = $menu->get_slug();
                $data['menu_name'] = $menu->get_name();
            }
        }

        // Log fallback creation in debug mode.
        if ( defined( 'URST_DEBUG_CONTEXT' ) && URST_DEBUG_CONTEXT ) {
            error_log( sprintf(
                'URST Context: Created fallback context [menu=%s, channel=%s]',
                $data['menu_slug'],
                $channel
            ) );
        }

        return new self( $data );
    }

    /**
     * Backward compat alias.
     */
    public static function create_default( $channel = 'web' ) {
        return self::create_fallback( $channel );
    }
}
