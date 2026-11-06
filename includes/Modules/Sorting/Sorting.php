<?php
/**
 * Sorting Module.
 *
 * Allows admin to order categories and products via drag-and-drop.
 * Uses WordPress term_order and menu_order for storage.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Modules\Sorting;

use Unstoppable\Restaurant\Core\ModuleInterface;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sorting class.
 *
 * @since 2.5.0
 */
class Sorting implements ModuleInterface {

    /**
     * Get module ID.
     *
     * @return string
     */
    public function get_id() {
        return 'sorting';
    }

    /**
     * Get module name.
     *
     * @return string
     */
    public function get_name() {
        return __( 'Menu Sorting', 'unstoppable-restaurant' );
    }

    /**
     * Check requirements.
     *
     * @return bool
     */
    public function meets_requirements() {
        return function_exists( 'WC' );
    }

    /**
     * Initialize the module.
     */
    public function init() {
        // Admin menu.
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Enqueue admin scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_urst_save_category_order', array( $this, 'ajax_save_category_order' ) );
        add_action( 'wp_ajax_urst_save_product_order', array( $this, 'ajax_save_product_order' ) );
        add_action( 'wp_ajax_urst_get_category_products', array( $this, 'ajax_get_category_products' ) );

        // Apply ordering to frontend queries.
        add_filter( 'urst_product_query_args', array( $this, 'apply_product_ordering' ), 10, 2 );
        add_filter( 'urst_category_query_args', array( $this, 'apply_category_ordering' ) );
    }

    /**
     * Enqueue admin scripts for sorting page.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'restaurant_page_urst-sorting' !== $hook ) {
            return;
        }

        // jQuery UI Sortable is required for drag-drop functionality.
        wp_enqueue_script( 'jquery-ui-sortable' );
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'unstoppable-restaurant',
            __( 'Menu Order', 'unstoppable-restaurant' ),
            __( 'Menu Order', 'unstoppable-restaurant' ),
            'manage_woocommerce',
            'urst-sorting',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Render admin page.
     */
    public function render_admin_page() {
        // Get categories.
        $categories = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'meta_value_num',
            'meta_key'   => '_urst_order',
            'order'      => 'ASC',
        ) );

        // Fall back to default ordering if no custom order.
        if ( is_wp_error( $categories ) ) {
            $categories = array();
        }

        // Re-sort by custom order.
        usort( $categories, function( $a, $b ) {
            $order_a = get_term_meta( $a->term_id, '_urst_order', true );
            $order_b = get_term_meta( $b->term_id, '_urst_order', true );
            $order_a = $order_a !== '' ? (int) $order_a : 999;
            $order_b = $order_b !== '' ? (int) $order_b : 999;
            return $order_a - $order_b;
        } );

        include __DIR__ . '/views/admin-sorting.php';
    }

    /**
     * Get products for a category.
     *
     * @param int $category_id Category ID.
     * @return array
     */
    public function get_category_products( $category_id ) {
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $category_id,
                ),
            ),
        );

        $query = new \WP_Query( $args );
        $products = array();

        foreach ( $query->posts as $post ) {
            $products[] = array(
                'id'    => $post->ID,
                'title' => $post->post_title,
                'order' => $post->menu_order,
            );
        }

        // Sort by menu_order.
        usort( $products, function( $a, $b ) {
            return $a['order'] - $b['order'];
        } );

        return $products;
    }

    /**
     * AJAX: Save category order.
     */
    public function ajax_save_category_order() {
        if ( ! check_ajax_referer( 'urst_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'unstoppable-restaurant' ) ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'unstoppable-restaurant' ) ) );
        }

        $order = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : array();

        foreach ( $order as $position => $term_id ) {
            update_term_meta( $term_id, '_urst_order', $position );
        }

        wp_send_json_success( array( 'message' => __( 'Category order saved.', 'unstoppable-restaurant' ) ) );
    }

    /**
     * AJAX: Save product order.
     */
    public function ajax_save_product_order() {
        if ( ! check_ajax_referer( 'urst_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'unstoppable-restaurant' ) ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'unstoppable-restaurant' ) ) );
        }

        $order = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : array();

        foreach ( $order as $position => $post_id ) {
            wp_update_post( array(
                'ID'         => $post_id,
                'menu_order' => $position,
            ) );
        }

        wp_send_json_success( array( 'message' => __( 'Product order saved.', 'unstoppable-restaurant' ) ) );
    }

    /**
     * AJAX: Get products for a category.
     */
    public function ajax_get_category_products() {
        if ( ! check_ajax_referer( 'urst_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'unstoppable-restaurant' ) ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'unstoppable-restaurant' ) ) );
        }

        $category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;

        if ( ! $category_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid category.', 'unstoppable-restaurant' ) ) );
        }

        $products = $this->get_category_products( $category_id );

        wp_send_json_success( array( 'products' => $products ) );
    }

    /**
     * Apply product ordering to frontend queries.
     *
     * @param array $args  Query args.
     * @param int   $category_id Category ID.
     * @return array
     */
    public function apply_product_ordering( $args, $category_id = 0 ) {
        // Default to menu_order for our layouts.
        if ( ! isset( $args['orderby'] ) || 'menu_order' === $args['orderby'] ) {
            $args['orderby'] = 'menu_order';
            $args['order']   = 'ASC';
        }

        return $args;
    }

    /**
     * Apply category ordering to frontend queries.
     *
     * @param array $args Query args.
     * @return array
     */
    public function apply_category_ordering( $args ) {
        // We handle ordering via meta, so we need custom sorting.
        // The actual sorting happens in get_layout_categories.
        return $args;
    }
}
