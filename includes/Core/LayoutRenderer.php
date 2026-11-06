<?php
/**
 * Layout Renderer.
 *
 * DEPRECATED in 2.8.0: Use MenuShortcode + MenuRenderer instead.
 * Kept for backward compatibility with blocks.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Core;

use Unstoppable\Restaurant\Admin\Settings;
use Unstoppable\Restaurant\Menu\MenuRepository;
use Unstoppable\Restaurant\Menu\MenuRenderer;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LayoutRenderer class.
 *
 * @since 1.0.0
 * @deprecated 2.8.0 Use MenuShortcode + MenuRenderer instead.
 */
class LayoutRenderer {

    /**
     * Singleton instance.
     *
     * @var LayoutRenderer|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return LayoutRenderer
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the renderer.
     */
    public function init() {
        // Shortcodes are now handled by MenuShortcode (2.8.0+).
        // Only register block for backward compatibility.
        add_action( 'init', array( $this, 'register_block' ) );
    }

    /**
     * Register Gutenberg block.
     */
    public function register_block() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type( 'unstoppable-restaurant/menu', array(
            'render_callback' => array( $this, 'render_block' ),
            'attributes'      => array(
                'menu'              => array( 'type' => 'string', 'default' => '' ),
                'categories'        => array( 'type' => 'string', 'default' => '' ),
                'layout'            => array( 'type' => 'string', 'default' => '' ),
                'columns'           => array( 'type' => 'number', 'default' => 3 ),
                'show_images'       => array( 'type' => 'boolean', 'default' => true ),
                'show_prices'       => array( 'type' => 'boolean', 'default' => true ),
                'show_descriptions' => array( 'type' => 'boolean', 'default' => true ),
            ),
        ) );
    }

    /**
     * Render block output.
     *
     * @param array $atts Block attributes.
     * @return string Rendered HTML.
     */
    public function render_block( $atts = array() ) {
        // If menu attribute is provided, use new system.
        if ( ! empty( $atts['menu'] ) ) {
            $menu = MenuRepository::instance()->get_by_slug( sanitize_title( $atts['menu'] ) );
            if ( $menu ) {
                return MenuRenderer::instance()->render( $menu );
            }
        }

        // Fall back to default menu.
        $menu = MenuRepository::instance()->get_default();
        return MenuRenderer::instance()->render( $menu );
    }

    /**
     * Render shortcode output.
     *
     * @deprecated 2.8.0 Use MenuShortcode instead.
     * @param array $atts Shortcode attributes.
     * @return string Rendered HTML.
     */
    public function render_shortcode( $atts = array() ) {
        // Delegate to new system.
        $menu_slug = ! empty( $atts['menu'] ) ? sanitize_title( $atts['menu'] ) : '';
        
        $repository = MenuRepository::instance();
        $menu = ! empty( $menu_slug ) ? $repository->get_by_slug( $menu_slug ) : $repository->get_default();
        
        if ( ! $menu ) {
            return '';
        }

        return MenuRenderer::instance()->render( $menu );
    }

    /**
     * Render empty state when no products found.
     *
     * @return string Empty state HTML.
     */
    private function render_empty_state() {
        return TemplateLoader::instance()->get( 'layouts/empty.php', array(), true );
    }

    /**
     * Render a layout programmatically.
     *
     * @deprecated 2.8.0 Use MenuRenderer instead.
     * @param string $layout_id Layout ID.
     * @param array  $args      Render arguments.
     * @return string Rendered HTML.
     */
    public function render( $layout_id, array $args = array() ) {
        return $this->render_shortcode( array_merge( array( 'layout' => $layout_id ), $args ) );
    }
}
