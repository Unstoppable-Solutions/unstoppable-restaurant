<?php
/**
 * Menu Shortcode Handler.
 *
 * Thin wrapper that ONLY:
 * - Loads MenuDefinition by slug
 * - Passes to MenuHtmlRenderer
 *
 * Phase 7.2: Supports multiple menus per page.
 *
 * @package Unstoppable_Restaurant
 * @since 2.8.0
 */

namespace Unstoppable\Restaurant\Menu;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MenuShortcode class.
 *
 * @since 2.8.0
 */
class MenuShortcode {

    /**
     * Singleton instance.
     *
     * @var MenuShortcode|null
     */
    private static $instance = null;

    /**
     * Re-entrancy guard.
     *
     * @var bool
     */
    private $running = false;

    /**
     * Rendered menu slugs (for multiple menus on same page).
     *
     * @var array
     */
    private $rendered_menus = array();

    /**
     * Get singleton instance.
     *
     * @return MenuShortcode
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize shortcode.
     */
    public function init() {
        add_shortcode( 'unstoppable_menu', array( $this, 'render' ) );
        add_shortcode( 'unstoppable_restaurant', array( $this, 'render' ) );
        add_shortcode( 'unstoppable_menus', array( $this, 'render_multiple' ) );
    }

    /**
     * Render single menu shortcode.
     *
     * Usage: [unstoppable_menu menu="lunch"]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render( $atts = array() ) {
        // Re-entrancy guard.
        if ( $this->running ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'URST: unstoppable_menu recursion blocked' );
            }
            return '<!-- URST: recursion blocked -->';
        }
        $this->running = true;

        // Don't render in admin.
        if ( is_admin() && ! wp_doing_ajax() ) {
            $this->running = false;
            return '';
        }

        // Parse attributes.
        $atts = shortcode_atts( array(
            'menu'    => '', // Menu slug.
            'channel' => 'web',
            'class'   => '', // Additional CSS class.
        ), $atts, 'unstoppable_menu' );

        // Get menu slug.
        $menu_slug = sanitize_title( $atts['menu'] );
        $channel = sanitize_key( $atts['channel'] );

        // Load menu definition.
        $repository = MenuRepository::instance();

        if ( ! empty( $menu_slug ) ) {
            $menu = $repository->get_by_slug( $menu_slug );
        } else {
            // No slug provided - use best active menu via rules engine.
            $rules_engine = MenuRulesEngine::instance();
            $menu = $rules_engine->get_best_menu( $channel );

            // Fallback to default if no active menu.
            if ( ! $menu ) {
                $menu = $repository->get_default();
            }
        }

        // Handle menu not found.
        if ( ! $menu ) {
            $this->running = false;

            if ( current_user_can( 'manage_woocommerce' ) ) {
                return '<div class="urst-error" style="padding: 20px; background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b;">'
                    . sprintf(
                        /* translators: %s: menu slug */
                        esc_html__( 'Menu "%s" not found.', 'unstoppable-restaurant' ),
                        esc_html( $menu_slug )
                    )
                    . '</div>';
            }

            $this->running = false;
            return '';
        }

        // Track rendered menus for this page.
        $this->rendered_menus[] = $menu->get_slug();

        // Render via MenuHtmlRenderer (THE ONLY HTML ENTRY POINT).
        $output = MenuHtmlRenderer::render( $menu, $channel );

        // Add wrapper with optional custom class.
        if ( ! empty( $atts['class'] ) && ! empty( $output ) ) {
            $output = '<div class="' . esc_attr( $atts['class'] ) . '">' . $output . '</div>';
        }

        $this->running = false;

        return $output;
    }

    /**
     * Render multiple menus shortcode (Phase 7.2).
     *
     * Usage: [unstoppable_menus menus="lunch,drinks,dessert"]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_multiple( $atts = array() ) {
        // Don't render in admin.
        if ( is_admin() && ! wp_doing_ajax() ) {
            return '';
        }

        // Parse attributes.
        $atts = shortcode_atts( array(
            'menus'     => '', // Comma-separated menu slugs.
            'channel'   => 'web',
            'class'     => '',
            'separator' => '', // HTML between menus.
        ), $atts, 'unstoppable_menus' );

        // Parse menu slugs.
        $slugs = array_filter( array_map( 'sanitize_title', explode( ',', $atts['menus'] ) ) );

        if ( empty( $slugs ) ) {
            // No slugs provided - render all active menus.
            $rules_engine = MenuRulesEngine::instance();
            $active_menus = $rules_engine->get_active_menus( sanitize_key( $atts['channel'] ) );

            $slugs = array_map( function( $menu ) {
                return $menu->get_slug();
            }, $active_menus );
        }

        if ( empty( $slugs ) ) {
            return '';
        }

        // Render each menu.
        $outputs = array();
        foreach ( $slugs as $slug ) {
            $output = $this->render( array(
                'menu'    => $slug,
                'channel' => $atts['channel'],
            ) );

            if ( ! empty( $output ) ) {
                $outputs[] = $output;
            }
        }

        if ( empty( $outputs ) ) {
            return '';
        }

        // Join with separator.
        $separator = wp_kses_post( $atts['separator'] );
        $html = implode( $separator, $outputs );

        // Add wrapper.
        if ( ! empty( $atts['class'] ) ) {
            $html = '<div class="' . esc_attr( $atts['class'] ) . '">' . $html . '</div>';
        }

        return $html;
    }

    /**
     * Get slugs of menus rendered on current page.
     *
     * @return array
     */
    public function get_rendered_menus() {
        return $this->rendered_menus;
    }

    /**
     * Reset rendered menus tracking.
     */
    public function reset_rendered_menus() {
        $this->rendered_menus = array();
    }
}
