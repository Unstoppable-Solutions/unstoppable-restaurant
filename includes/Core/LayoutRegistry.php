<?php
/**
 * Layout Registry.
 *
 * Centralized registration and management of menu layouts.
 * All layouts must be registered through this class.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LayoutRegistry class.
 *
 * @since 1.0.0
 */
class LayoutRegistry {

    /**
     * Singleton instance.
     *
     * @var LayoutRegistry|null
     */
    private static $instance = null;

    /**
     * Registered layouts.
     *
     * @var array
     */
    private $layouts = array();

    /**
     * Get singleton instance.
     *
     * @return LayoutRegistry
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->register_default_layouts();
        }
        return self::$instance;
    }

    /**
     * Register default layouts.
     */
    private function register_default_layouts() {
        // Classic Menu Layout.
        $this->register( array(
            'id'          => 'classic',
            'label'       => __( 'Classic Menu', 'unstoppable-restaurant' ),
            'description' => __( 'Categories listed vertically with sidebar navigation on desktop, dropdown on mobile.', 'unstoppable-restaurant' ),
            'template'    => 'layouts/classic.php',
            'icon'        => 'dashicons-list-view',
            'supports'    => array( 'sidebar', 'mobile-dropdown', 'smooth-scroll' ),
        ) );

        // Modern Tabs Layout.
        $this->register( array(
            'id'          => 'tabs',
            'label'       => __( 'Modern Tabs', 'unstoppable-restaurant' ),
            'description' => __( 'Sticky horizontal tabs with smooth scroll navigation and active state highlighting.', 'unstoppable-restaurant' ),
            'template'    => 'layouts/tabs.php',
            'icon'        => 'dashicons-category',
            'supports'    => array( 'sticky-tabs', 'smooth-scroll', 'keyboard-nav' ),
        ) );

        // Minimal Layout - ultra lightweight.
        $this->register( array(
            'id'          => 'minimal',
            'label'       => __( 'Minimal (Lightweight)', 'unstoppable-restaurant' ),
            'description' => __( 'Ultra-lightweight layout with inline CSS. Shows one category at a time. Best for sites with many products.', 'unstoppable-restaurant' ),
            'template'    => 'layouts/minimal.php',
            'icon'        => 'dashicons-minus',
            'supports'    => array( 'tab-switching', 'lightweight' ),
        ) );

        /**
         * Filter to register additional layouts.
         *
         * @since 1.0.0
         * @param LayoutRegistry $registry The layout registry instance.
         */
        do_action( 'urst_register_layouts', $this );
    }

    /**
     * Register a layout.
     *
     * @param array $args {
     *     Layout arguments.
     *
     *     @type string $id          Unique layout identifier. Required.
     *     @type string $label       Human-readable name. Required.
     *     @type string $description Layout description.
     *     @type string $template    Template file path relative to templates/. Required.
     *     @type string $icon        Dashicon class for admin UI.
     *     @type array  $supports    Features this layout supports.
     * }
     * @return bool True on success, false on failure.
     */
    public function register( array $args ) {
        // Validate required fields.
        if ( empty( $args['id'] ) || empty( $args['label'] ) || empty( $args['template'] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // translators: %s is the layout ID or 'unknown'.
                error_log( sprintf( 'Unstoppable Restaurant: Failed to register layout "%s". Missing required fields.', $args['id'] ?? 'unknown' ) );
            }
            return false;
        }

        $id = sanitize_key( $args['id'] );

        // Check for duplicates.
        if ( isset( $this->layouts[ $id ] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // translators: %s is the layout ID.
                error_log( sprintf( 'Unstoppable Restaurant: Layout "%s" is already registered.', $id ) );
            }
            return false;
        }

        $this->layouts[ $id ] = array(
            'id'          => $id,
            'label'       => sanitize_text_field( $args['label'] ),
            'description' => isset( $args['description'] ) ? sanitize_text_field( $args['description'] ) : '',
            'template'    => sanitize_text_field( $args['template'] ),
            'icon'        => isset( $args['icon'] ) ? sanitize_text_field( $args['icon'] ) : 'dashicons-layout',
            'supports'    => isset( $args['supports'] ) && is_array( $args['supports'] ) ? array_map( 'sanitize_key', $args['supports'] ) : array(),
        );

        return true;
    }

    /**
     * Unregister a layout.
     *
     * @param string $id Layout ID.
     * @return bool True on success, false if layout not found.
     */
    public function unregister( $id ) {
        $id = sanitize_key( $id );

        if ( ! isset( $this->layouts[ $id ] ) ) {
            return false;
        }

        unset( $this->layouts[ $id ] );
        return true;
    }

    /**
     * Get a specific layout.
     *
     * @param string $id Layout ID.
     * @return array|null Layout data or null if not found.
     */
    public function get( $id ) {
        $id = sanitize_key( $id );
        return $this->layouts[ $id ] ?? null;
    }

    /**
     * Get all registered layouts.
     *
     * @return array All layouts.
     */
    public function get_all() {
        return $this->layouts;
    }

    /**
     * Check if a layout exists.
     *
     * @param string $id Layout ID.
     * @return bool True if exists, false otherwise.
     */
    public function exists( $id ) {
        return isset( $this->layouts[ sanitize_key( $id ) ] );
    }

    /**
     * Get layout IDs.
     *
     * @return array Layout IDs.
     */
    public function get_ids() {
        return array_keys( $this->layouts );
    }

    /**
     * Get the template path for a layout.
     *
     * @param string $id Layout ID.
     * @return string|null Template path or null if not found.
     */
    public function get_template( $id ) {
        $layout = $this->get( $id );
        return $layout ? $layout['template'] : null;
    }

    /**
     * Get layouts as options for select fields.
     *
     * @return array Associative array of id => label.
     */
    public function get_options() {
        $options = array();

        foreach ( $this->layouts as $id => $layout ) {
            $options[ $id ] = $layout['label'];
        }

        return $options;
    }

    /**
     * Get the default layout ID.
     *
     * @return string First registered layout ID.
     */
    public function get_default() {
        $ids = $this->get_ids();
        return ! empty( $ids ) ? $ids[0] : 'classic';
    }
}
