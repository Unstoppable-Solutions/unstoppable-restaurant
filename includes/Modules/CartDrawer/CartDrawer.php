<?php
/**
 * Cart Drawer Module.
 *
 * Slide-in cart drawer with AJAX updates.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Modules\CartDrawer;

use Unstoppable\Restaurant\Core\ModuleInterface;
use Unstoppable\Restaurant\Core\TemplateLoader;
use Unstoppable\Restaurant\Admin\Settings;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CartDrawer class.
 *
 * @since 1.0.0
 */
class CartDrawer implements ModuleInterface {

    /**
     * Get module ID.
     *
     * @return string
     */
    public function get_id() {
        return 'cart-drawer';
    }

    /**
     * Get module name.
     *
     * @return string
     */
    public function get_name() {
        return __( 'Cart Drawer', 'unstoppable-restaurant' );
    }

    /**
     * Check requirements.
     *
     * @return bool
     */
    public function meets_requirements() {
        // Cart drawer is always available if WooCommerce cart exists.
        return function_exists( 'WC' ) && WC()->cart !== null;
    }

    /**
     * Initialize the module.
     */
    public function init() {
        add_action( 'wp_footer', array( $this, 'render_drawer' ) );
        add_action( 'wp_footer', array( $this, 'render_floating_cart' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_urst_get_cart', array( $this, 'ajax_get_cart' ) );
        add_action( 'wp_ajax_nopriv_urst_get_cart', array( $this, 'ajax_get_cart' ) );

        add_action( 'wp_ajax_urst_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_urst_add_to_cart', array( $this, 'ajax_add_to_cart' ) );

        add_action( 'wp_ajax_urst_update_cart_item', array( $this, 'ajax_update_cart_item' ) );
        add_action( 'wp_ajax_nopriv_urst_update_cart_item', array( $this, 'ajax_update_cart_item' ) );

        add_action( 'wp_ajax_urst_remove_cart_item', array( $this, 'ajax_remove_cart_item' ) );
        add_action( 'wp_ajax_nopriv_urst_remove_cart_item', array( $this, 'ajax_remove_cart_item' ) );

        // Diagnostic endpoint
        add_action( 'wp_ajax_urst_cart_diagnostic', array( $this, 'ajax_cart_diagnostic' ) );
        add_action( 'wp_ajax_nopriv_urst_cart_diagnostic', array( $this, 'ajax_cart_diagnostic' ) );

        // Lightweight cart HTML refresh
        add_action( 'wp_ajax_urst_get_cart_html', array( $this, 'ajax_get_cart_html' ) );
        add_action( 'wp_ajax_nopriv_urst_get_cart_html', array( $this, 'ajax_get_cart_html' ) );

        add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_fragments' ) );
    }

    /**
     * Render the cart drawer.
     */
    public function render_drawer() {
        if ( is_cart() || is_checkout() || is_admin() ) {
            return;
        }

        $position = Settings::instance()->get( 'cart_drawer_position', 'right' );

        TemplateLoader::instance()->get( 'cart/drawer.php', array(
            'position' => $position,
        ) );
    }

    /**
     * Render floating cart button.
     */
    public function render_floating_cart() {
        if ( is_cart() || is_checkout() || is_admin() ) {
            return;
        }

        TemplateLoader::instance()->get( 'cart/floating-cart.php', array(
            'count' => WC()->cart ? WC()->cart->get_cart_contents_count() : 0,
        ) );
    }

    /**
     * AJAX: Get cart contents.
     */
    public function ajax_get_cart() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'urst_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'unstoppable-restaurant' ) ) );
        }

        $html  = $this->get_cart_html();
        $count = WC()->cart->get_cart_contents_count();
        $total = WC()->cart->get_cart_total();

        wp_send_json_success( array(
            'html'    => $html,
            'count'   => $count,
            'total'   => $total,
            'isEmpty' => WC()->cart->is_empty(),
        ) );
    }

    /**
     * AJAX: Get cart HTML (lightweight, no shipping).
     * Used for refreshing cart drawer after WooCommerce native operations.
     */
    public function ajax_get_cart_html() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'urst_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'unstoppable-restaurant' ) ) );
        }

        // Disable shipping during cart render for performance
        add_filter( 'woocommerce_shipping_show_shipping_calculator', '__return_false' );
        add_filter( 'woocommerce_cart_ready_to_calc_shipping', '__return_false' );

        $html  = $this->get_cart_html();
        $count = WC()->cart->get_cart_contents_count();
        $total = WC()->cart->get_cart_total();

        // Remove our filters
        remove_filter( 'woocommerce_shipping_show_shipping_calculator', '__return_false' );
        remove_filter( 'woocommerce_cart_ready_to_calc_shipping', '__return_false' );

        wp_send_json_success( array(
            'html'    => $html,
            'count'   => $count,
            'total'   => $total,
            'isEmpty' => WC()->cart->is_empty(),
        ) );
    }

    /**
     * AJAX: Add to cart.
     */
    public function ajax_add_to_cart() {
        $timings = array();
        $start = microtime( true );

        // Verify nonce.
        if ( ! check_ajax_referer( 'urst_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'unstoppable-restaurant' ) ) );
        }
        $timings['nonce'] = round( ( microtime( true ) - $start ) * 1000 );

        // Sanitize input.
        $product_id     = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $quantity       = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
        $variation_id   = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
        $variation      = isset( $_POST['variation'] ) && is_array( $_POST['variation'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['variation'] ) ) : array();
        $cart_item_data = isset( $_POST['cart_item_data'] ) && is_array( $_POST['cart_item_data'] ) ? wp_unslash( $_POST['cart_item_data'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        // Validate.
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product.', 'unstoppable-restaurant' ) ) );
        }

        if ( $quantity < 1 ) {
            $quantity = 1;
        }

        // Sanitize cart item data (modifiers).
        $cart_item_data = $this->sanitize_cart_item_data( $cart_item_data );

        // Add to cart.
        $add_start = microtime( true );
        $cart_item_key = WC()->cart->add_to_cart(
            $product_id,
            $quantity,
            $variation_id,
            $variation,
            $cart_item_data
        );
        $timings['add_to_cart'] = round( ( microtime( true ) - $add_start ) * 1000 );

        if ( ! $cart_item_key ) {
            $error_notices = wc_get_notices( 'error' );
            $message       = ! empty( $error_notices ) ? wp_strip_all_tags( $error_notices[0]['notice'] ) : __( 'Could not add to cart.', 'unstoppable-restaurant' );
            wc_clear_notices();
            wp_send_json_error( array( 'message' => $message ) );
        }

        // Disable shipping during cart render for performance
        add_filter( 'woocommerce_shipping_enabled', '__return_false', 9999 );
        add_filter( 'woocommerce_shipping_show_shipping_calculator', '__return_false', 9999 );
        add_filter( 'woocommerce_cart_ready_to_calc_shipping', '__return_false', 9999 );
        add_filter( 'woocommerce_cart_needs_shipping', '__return_false', 9999 );

        // Get updated cart HTML - this is often the slow part.
        $html_start = microtime( true );
        $html = $this->get_cart_html();
        $timings['get_cart_html'] = round( ( microtime( true ) - $html_start ) * 1000 );

        // Remove our filters
        remove_filter( 'woocommerce_shipping_enabled', '__return_false', 9999 );
        remove_filter( 'woocommerce_shipping_show_shipping_calculator', '__return_false', 9999 );
        remove_filter( 'woocommerce_cart_ready_to_calc_shipping', '__return_false', 9999 );
        remove_filter( 'woocommerce_cart_needs_shipping', '__return_false', 9999 );

        // Get count and total.
        $count_start = microtime( true );
        $count = WC()->cart->get_cart_contents_count();
        $total = WC()->cart->get_cart_total();
        $timings['count_total'] = round( ( microtime( true ) - $count_start ) * 1000 );

        $timings['total_ms'] = round( ( microtime( true ) - $start ) * 1000 );

        // Log if slow (> 2 seconds).
        if ( $timings['total_ms'] > 2000 ) {
            error_log( '[URST SLOW CART] add_to_cart timings: ' . wp_json_encode( $timings ) );
        }

        wp_send_json_success( array(
            'message'       => __( 'Added to cart!', 'unstoppable-restaurant' ),
            'html'          => $html,
            'count'         => $count,
            'total'         => $total,
            'cart_item_key' => $cart_item_key,
            'debug_timings' => $timings, // Include in response for debugging.
        ) );
    }

    /**
     * AJAX: Update cart item quantity.
     */
    public function ajax_update_cart_item() {
        $timings = array();
        $start = microtime( true );

        // Verify nonce.
        if ( ! check_ajax_referer( 'urst_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'unstoppable-restaurant' ) ) );
        }
        $timings['nonce'] = round( ( microtime( true ) - $start ) * 1000 );

        // Sanitize input.
        $cart_item_key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) ) : '';
        $quantity      = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

        // Validate.
        if ( empty( $cart_item_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid cart item.', 'unstoppable-restaurant' ) ) );
        }

        $update_start = microtime( true );
        WC()->cart->set_quantity( $cart_item_key, $quantity );
        $timings['set_quantity'] = round( ( microtime( true ) - $update_start ) * 1000 );

        // Disable shipping during cart render for performance
        add_filter( 'woocommerce_shipping_show_shipping_calculator', '__return_false' );
        add_filter( 'woocommerce_cart_ready_to_calc_shipping', '__return_false' );

        $html_start = microtime( true );
        $html = $this->get_cart_html();
        $timings['get_cart_html'] = round( ( microtime( true ) - $html_start ) * 1000 );

        // Remove filters
        remove_filter( 'woocommerce_shipping_show_shipping_calculator', '__return_false' );
        remove_filter( 'woocommerce_cart_ready_to_calc_shipping', '__return_false' );

        $count_start = microtime( true );
        $count = WC()->cart->get_cart_contents_count();
        $total = WC()->cart->get_cart_total();
        $timings['count_total'] = round( ( microtime( true ) - $count_start ) * 1000 );

        $timings['total_ms'] = round( ( microtime( true ) - $start ) * 1000 );

        if ( $timings['total_ms'] > 2000 ) {
            error_log( '[URST SLOW CART] update_cart_item timings: ' . wp_json_encode( $timings ) );
        }

        wp_send_json_success( array(
            'html'          => $html,
            'count'         => $count,
            'total'         => $total,
            'debug_timings' => $timings,
        ) );
    }

    /**
     * AJAX: Remove cart item.
     */
    public function ajax_remove_cart_item() {
        $timings = array();
        $start = microtime( true );

        // Verify nonce.
        if ( ! check_ajax_referer( 'urst_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'unstoppable-restaurant' ) ) );
        }
        $timings['nonce'] = round( ( microtime( true ) - $start ) * 1000 );

        // Sanitize input.
        $cart_item_key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) ) : '';

        // Validate.
        if ( empty( $cart_item_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid cart item.', 'unstoppable-restaurant' ) ) );
        }

        $remove_start = microtime( true );
        WC()->cart->remove_cart_item( $cart_item_key );
        $timings['remove_item'] = round( ( microtime( true ) - $remove_start ) * 1000 );

        // Disable shipping during cart render for performance
        add_filter( 'woocommerce_shipping_show_shipping_calculator', '__return_false' );
        add_filter( 'woocommerce_cart_ready_to_calc_shipping', '__return_false' );

        $html_start = microtime( true );
        $html = $this->get_cart_html();
        $timings['get_cart_html'] = round( ( microtime( true ) - $html_start ) * 1000 );

        // Remove filters
        remove_filter( 'woocommerce_shipping_show_shipping_calculator', '__return_false' );
        remove_filter( 'woocommerce_cart_ready_to_calc_shipping', '__return_false' );

        $count_start = microtime( true );
        $count = WC()->cart->get_cart_contents_count();
        $total = WC()->cart->get_cart_total();
        $timings['count_total'] = round( ( microtime( true ) - $count_start ) * 1000 );

        $timings['total_ms'] = round( ( microtime( true ) - $start ) * 1000 );

        if ( $timings['total_ms'] > 2000 ) {
            error_log( '[URST SLOW CART] remove_cart_item timings: ' . wp_json_encode( $timings ) );
        }

        wp_send_json_success( array(
            'html'    => $html,
            'count'   => $count,
            'total'   => $total,
            'isEmpty' => WC()->cart->is_empty(),
            'debug_timings' => $timings,
        ) );
    }

    /**
     * Get cart HTML.
     *
     * Temporarily disables shipping calculations which can be very slow
     * (especially with distance-based shipping plugins).
     *
     * @return string
     */
    private function get_cart_html() {
        // Aggressively disable ALL shipping calculations during cart HTML render.
        // This is a common cause of 30-45 second delays with distance-based shipping.
        add_filter( 'woocommerce_shipping_enabled', '__return_false', 9999 );
        add_filter( 'woocommerce_shipping_show_shipping_calculator', '__return_false', 9999 );
        add_filter( 'woocommerce_cart_ready_to_calc_shipping', '__return_false', 9999 );
        add_filter( 'woocommerce_cart_needs_shipping', '__return_false', 9999 );

        $html = TemplateLoader::instance()->get( 'cart/cart-contents.php', array(
            'cart' => WC()->cart->get_cart(),
        ), true );

        // Re-enable shipping.
        remove_filter( 'woocommerce_shipping_enabled', '__return_false', 9999 );
        remove_filter( 'woocommerce_shipping_show_shipping_calculator', '__return_false', 9999 );
        remove_filter( 'woocommerce_cart_ready_to_calc_shipping', '__return_false', 9999 );
        remove_filter( 'woocommerce_cart_needs_shipping', '__return_false', 9999 );

        return $html;
    }

    /**
     * Sanitize cart item data (modifiers).
     *
     * @param array $data Raw cart item data.
     * @return array
     */
    private function sanitize_cart_item_data( $data ) {
        $sanitized = array();

        if ( isset( $data['ur_modifiers'] ) && is_array( $data['ur_modifiers'] ) ) {
            $sanitized['ur_modifiers'] = array();

            foreach ( $data['ur_modifiers'] as $group_id => $selections ) {
                $clean_group_id = sanitize_key( $group_id );
                $sanitized['ur_modifiers'][ $clean_group_id ] = is_array( $selections )
                    ? array_map( 'sanitize_key', $selections )
                    : sanitize_key( $selections );
            }
        }

        if ( isset( $data['ur_special_instructions'] ) ) {
            $sanitized['ur_special_instructions'] = sanitize_textarea_field( $data['ur_special_instructions'] );
        }

        /**
         * Filter sanitized cart item data.
         *
         * @since 1.0.0
         * @param array $sanitized Sanitized data.
         * @param array $data      Raw data.
         */
        return apply_filters( 'urst_sanitize_cart_item_data', $sanitized, $data );
    }

    /**
     * Add cart fragments for AJAX updates.
     *
     * @param array $fragments Existing fragments.
     * @return array
     */
    public function cart_fragments( $fragments ) {
        // Floating cart button.
        ob_start();
        $this->render_floating_cart();
        $fragments['.ur-floating-cart'] = ob_get_clean();

        // Cart contents.
        $fragments['.ur-cart-contents'] = $this->get_cart_html();

        return $fragments;
    }

    /**
     * AJAX: Cart diagnostic - helps identify what's causing slow cart operations.
     */
    public function ajax_cart_diagnostic() {
        $start_time = microtime( true );
        $steps = array();

        // Step 1: Basic response
        $steps['start'] = array(
            'time' => 0,
            'status' => 'ok',
        );

        // Step 2: Nonce check
        $nonce_start = microtime( true );
        $nonce_valid = check_ajax_referer( 'urst_frontend_nonce', 'nonce', false );
        $steps['nonce_check'] = array(
            'time' => round( ( microtime( true ) - $nonce_start ) * 1000, 2 ),
            'status' => $nonce_valid ? 'ok' : 'failed',
        );

        // Step 3: WC Cart access
        $wc_start = microtime( true );
        $cart_exists = WC()->cart !== null;
        $steps['wc_cart_access'] = array(
            'time' => round( ( microtime( true ) - $wc_start ) * 1000, 2 ),
            'status' => $cart_exists ? 'ok' : 'failed',
        );

        // Step 4: Get cart count
        if ( $cart_exists ) {
            $count_start = microtime( true );
            $count = WC()->cart->get_cart_contents_count();
            $steps['cart_count'] = array(
                'time' => round( ( microtime( true ) - $count_start ) * 1000, 2 ),
                'status' => 'ok',
                'count' => $count,
            );
        }

        // Step 5: Get cart items
        if ( $cart_exists ) {
            $items_start = microtime( true );
            $items = WC()->cart->get_cart();
            $steps['cart_items'] = array(
                'time' => round( ( microtime( true ) - $items_start ) * 1000, 2 ),
                'status' => 'ok',
                'items' => count( $items ),
            );
        }

        // Step 6: Render cart HTML
        if ( $cart_exists ) {
            $render_start = microtime( true );
            $html = $this->get_cart_html();
            $steps['render_html'] = array(
                'time' => round( ( microtime( true ) - $render_start ) * 1000, 2 ),
                'status' => 'ok',
                'html_length' => strlen( $html ),
            );
        }

        // Step 7: Get total
        if ( $cart_exists ) {
            $total_start = microtime( true );
            $total = WC()->cart->get_cart_total();
            $steps['cart_total'] = array(
                'time' => round( ( microtime( true ) - $total_start ) * 1000, 2 ),
                'status' => 'ok',
            );
        }

        $total_time = round( ( microtime( true ) - $start_time ) * 1000, 2 );

        // List active plugins that might affect cart
        $active_plugins = get_option( 'active_plugins', array() );
        $cart_related_plugins = array_filter( $active_plugins, function( $plugin ) {
            $keywords = array( 'cart', 'shipping', 'payment', 'checkout', 'woo', 'order', 'discount', 'coupon', 'reward', 'points' );
            foreach ( $keywords as $keyword ) {
                if ( stripos( $plugin, $keyword ) !== false ) {
                    return true;
                }
            }
            return false;
        });

        wp_send_json_success( array(
            'total_time_ms' => $total_time,
            'steps' => $steps,
            'php_max_execution' => ini_get( 'max_execution_time' ),
            'php_memory_limit' => ini_get( 'memory_limit' ),
            'cart_related_plugins' => array_values( $cart_related_plugins ),
            'message' => $total_time > 1000 
                ? 'SLOW: Cart operations taking over 1 second. Check cart_related_plugins list.'
                : 'OK: Cart operations are fast.',
        ) );
    }
}
