<?php
/**
 * Menu Definition Entity.
 *
 * Domain object representing a reusable restaurant menu configuration.
 * Stored as CPT (urst_menu) for WordPress-native integration.
 *
 * Schema matches Phase 6.3 AUTHORITATIVE specification.
 *
 * @package Unstoppable_Restaurant
 * @since 2.11.0
 */

namespace Unstoppable\Restaurant\Menu;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MenuDefinition class.
 *
 * @since 2.11.0
 */
final class MenuDefinition implements \JsonSerializable {

    /**
     * Post ID (null for new menus).
     *
     * @var int|null
     */
    public $id;

    /**
     * URL-safe slug.
     *
     * @var string
     */
    public $slug;

    /**
     * Human-readable name.
     *
     * @var string
     */
    public $name;

    /**
     * Optional description.
     *
     * @var string|null
     */
    public $description;

    /**
     * Ordered WooCommerce category term IDs.
     *
     * @var array<int>
     */
    public $category_ids = array();

    /**
     * Menu settings (layout, filters, sorting, visibility, etc).
     *
     * @var array<string,mixed>
     */
    public $settings = array();

    /**
     * Status: publish | draft
     *
     * @var string
     */
    public $status = 'publish';

    /**
     * Creation timestamp.
     *
     * @var string
     */
    public $created_at;

    /**
     * Last modified timestamp.
     *
     * @var string
     */
    public $updated_at;

    /**
     * Default settings.
     *
     * @var array
     */
    private static $default_settings = array(
        'layout'            => 'classic',
        'show_images'       => true,
        'show_prices'       => true,
        'show_descriptions' => true,
        'columns'           => 3,
        'priority'          => 10, // Lower = higher priority for fallback.
        'filters'           => array(
            'hide_out_of_stock' => true,
        ),
        'visibility'        => array(
            'channels' => array( 'web' ),
        ),
        // Phase 7.1: Rules engine settings.
        'rules'             => array(
            'enabled'    => false,
            'days'       => array(), // 0-6 (Sun-Sat).
            'time_start' => '',      // HH:MM format.
            'time_end'   => '',      // HH:MM format.
            'date_start' => '',      // YYYY-MM-DD format.
            'date_end'   => '',      // YYYY-MM-DD format.
        ),
        // Phase 7.3: Sections - presentation-only groupings.
        // Sections are menu-owned, optional, and override category rendering.
        'sections'                 => array(),
        // Phase 7.3.1: Dedupe products across sections (prevents same product twice).
        'sections_dedupe_products' => true,
    );

    /**
     * Constructor.
     *
     * @param array $data Menu definition data.
     */
    public function __construct( array $data ) {
        $this->id          = isset( $data['id'] ) ? absint( $data['id'] ) : null;
        $this->slug        = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';
        $this->name        = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        $this->description = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null;
        $this->status      = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'publish';
        $this->created_at  = isset( $data['created_at'] ) ? $data['created_at'] : current_time( 'mysql' );
        $this->updated_at  = isset( $data['updated_at'] ) ? $data['updated_at'] : current_time( 'mysql' );

        // Sanitize category IDs.
        $this->category_ids = isset( $data['category_ids'] ) 
            ? array_map( 'absint', array_filter( (array) $data['category_ids'] ) )
            : array();

        // Merge settings with defaults.
        $raw_settings = isset( $data['settings'] ) ? (array) $data['settings'] : array();
        
        // Phase 7.3.1: Sanitize sections before merging.
        if ( isset( $raw_settings['sections'] ) ) {
            $raw_settings['sections'] = self::sanitize_sections( $raw_settings['sections'] );
        }
        
        $this->settings = array_merge(
            self::$default_settings,
            $raw_settings
        );
    }

    /**
     * Phase 7.3.1: Sanitize sections array with strict schema enforcement.
     *
     * Section schema:
     * {
     *   id: string (sanitize_key),
     *   label: string (sanitize_text_field),
     *   description: string (sanitize_textarea_field),
     *   product_ids: int[] (absint, filtered),
     *   order: int (absint),
     *   is_active: bool
     * }
     *
     * @param mixed $sections Raw sections data.
     * @return array Sanitized sections.
     */
    private static function sanitize_sections( $sections ) {
        // Must be array.
        if ( ! is_array( $sections ) ) {
            return array();
        }

        $sanitized = array();
        $order = 1;

        foreach ( $sections as $section ) {
            // Skip non-array entries.
            if ( ! is_array( $section ) ) {
                continue;
            }

            // Label is required.
            $label = isset( $section['label'] ) ? sanitize_text_field( $section['label'] ) : '';
            if ( empty( $label ) ) {
                continue;
            }

            // Generate ID from label if not provided.
            $id = isset( $section['id'] ) && ! empty( $section['id'] ) 
                ? sanitize_key( $section['id'] ) 
                : sanitize_key( $label );

            // Ensure ID is not empty after sanitization.
            if ( empty( $id ) ) {
                $id = 'section-' . $order;
            }

            // Sanitize product IDs - must be array of positive integers.
            $product_ids = array();
            if ( isset( $section['product_ids'] ) && is_array( $section['product_ids'] ) ) {
                $product_ids = array_values( array_filter( array_map( 'absint', $section['product_ids'] ) ) );
            }

            // Build sanitized section.
            $sanitized[] = array(
                'id'          => $id,
                'label'       => $label,
                'description' => isset( $section['description'] ) ? sanitize_textarea_field( $section['description'] ) : '',
                'product_ids' => $product_ids,
                'order'       => isset( $section['order'] ) ? absint( $section['order'] ) : $order,
                'is_active'   => isset( $section['is_active'] ) ? (bool) $section['is_active'] : true,
            );

            $order++;
        }

        return $sanitized;
    }

    /**
     * Check if menu is published.
     *
     * @return bool
     */
    public function is_published() {
        return 'publish' === $this->status;
    }

    /**
     * Alias for is_published (backward compat).
     *
     * @return bool
     */
    public function is_active() {
        return $this->is_published();
    }

    /**
     * Check if menu includes a category.
     *
     * @param int $term_id Category term ID.
     * @return bool
     */
    public function has_category( $term_id ) {
        return in_array( (int) $term_id, $this->category_ids, true );
    }

    /**
     * Get menu ID.
     *
     * @return int|null
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get menu slug.
     *
     * @return string
     */
    public function get_slug() {
        return $this->slug;
    }

    /**
     * Get menu name.
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get menu description.
     *
     * @return string|null
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Get category IDs.
     *
     * @return array<int>
     */
    public function get_category_ids() {
        return $this->category_ids;
    }

    /**
     * Check if menu uses all categories (no specific selection).
     *
     * @return bool
     */
    public function uses_all_categories() {
        return empty( $this->category_ids );
    }

    /**
     * Get all settings.
     *
     * @return array
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get a specific setting.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get_setting( $key, $default = null ) {
        return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
    }

    /**
     * Get layout ID.
     *
     * @return string
     */
    public function get_layout() {
        return $this->get_setting( 'layout', 'classic' );
    }

    /**
     * Get filter settings.
     *
     * @return array
     */
    public function get_filters() {
        return $this->get_setting( 'filters', array() );
    }

    /**
     * Get visibility settings.
     *
     * @return array
     */
    public function get_visibility() {
        return $this->get_setting( 'visibility', array( 'channels' => array( 'web' ) ) );
    }

    /**
     * Phase 7.3: Get sections.
     *
     * @return array Array of section definitions.
     */
    public function get_sections() {
        $sections = $this->get_setting( 'sections', array() );
        return is_array( $sections ) ? $sections : array();
    }

    /**
     * Phase 7.3: Check if menu has sections defined.
     *
     * @return bool
     */
    public function has_sections() {
        $sections = $this->get_sections();
        return ! empty( $sections );
    }

    /**
     * Phase 7.3.1: Check if sections should dedupe products.
     *
     * When true, a product appearing in multiple sections will only
     * show in the first section where it appears (by order).
     *
     * @return bool
     */
    public function should_dedupe_section_products() {
        return (bool) $this->get_setting( 'sections_dedupe_products', true );
    }

    /**
     * Phase 7.3: Get active sections only.
     *
     * @return array
     */
    public function get_active_sections() {
        return array_filter( $this->get_sections(), function( $section ) {
            return ! isset( $section['is_active'] ) || $section['is_active'];
        } );
    }

    /**
     * Phase 7.3: Get a section by ID.
     *
     * @param string $section_id Section ID.
     * @return array|null
     */
    public function get_section( $section_id ) {
        foreach ( $this->get_sections() as $section ) {
            if ( isset( $section['id'] ) && $section['id'] === $section_id ) {
                return $section;
            }
        }
        return null;
    }

    /**
     * Get status.
     *
     * @return string
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * Get creation timestamp.
     *
     * @return string
     */
    public function get_created_at() {
        return $this->created_at;
    }

    /**
     * Get last modified timestamp.
     *
     * @return string
     */
    public function get_updated_at() {
        return $this->updated_at;
    }

    /**
     * Check if menu is visible for a given channel.
     *
     * @param string $channel Channel: web | qr | kiosk | pos
     * @return bool
     */
    public function is_visible_for_channel( $channel ) {
        if ( ! $this->is_published() ) {
            return false;
        }

        $visibility = $this->get_visibility();
        $channels = isset( $visibility['channels'] ) ? $visibility['channels'] : array( 'web' );

        // Empty = all channels.
        if ( empty( $channels ) ) {
            return true;
        }

        return in_array( $channel, $channels, true );
    }

    /**
     * Check if menu is visible to current user (web channel).
     *
     * @return bool
     */
    public function is_visible_to_current_user() {
        return $this->is_visible_for_channel( 'web' );
    }

    /**
     * Serialize to array.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id'           => $this->id,
            'slug'         => $this->slug,
            'name'         => $this->name,
            'description'  => $this->description,
            'category_ids' => $this->category_ids,
            'settings'     => $this->settings,
            'status'       => $this->status,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
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

    /**
     * Create from array.
     *
     * @param array $data Menu data.
     * @return MenuDefinition
     */
    public static function from_array( array $data ) {
        return new self( $data );
    }
}
