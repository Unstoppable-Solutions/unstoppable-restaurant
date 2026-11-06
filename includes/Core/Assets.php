<?php
/**
 * Assets Manager.
 *
 * Handles enqueuing of CSS and JavaScript files.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Assets class.
 *
 * @since 1.0.0
 */
class Assets {

    /**
     * Instance.
     *
     * @var Assets|null
     */
    private static $instance = null;

    /**
     * Get instance.
     *
     * @return Assets
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize assets.
     */
    public function init() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_frontend_assets() {
        // Debug mode: skip all JS if URST_DISABLE_JS is defined.
        $disable_js = defined( 'URST_DISABLE_JS' ) && URST_DISABLE_JS;
        
        // Also check for URST_NO_JS (simpler constant name).
        if ( defined( 'URST_NO_JS' ) && URST_NO_JS ) {
            $disable_js = true;
        }
        
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        // Design system CSS - always load (lightweight).
        wp_enqueue_style(
            'urst-design-system',
            URST_PLUGIN_URL . 'assets/css/design-system' . $suffix . '.css',
            array(),
            URST_VERSION
        );

        // Frontend CSS - always load.
        wp_enqueue_style(
            'urst-frontend',
            URST_PLUGIN_URL . 'assets/css/frontend' . $suffix . '.css',
            array( 'urst-design-system' ),
            URST_VERSION
        );

        // Skip JS entirely if disabled.
        if ( $disable_js ) {
            return;
        }

        // Frontend JS - only register, will be enqueued when needed.
        wp_register_script(
            'urst-frontend',
            URST_PLUGIN_URL . 'assets/js/frontend' . $suffix . '.js',
            array( 'jquery' ),
            URST_VERSION,
            true
        );

        // Localize script (will be printed when script is enqueued).
        wp_localize_script(
            'urst-frontend',
            'urstConfig',
            $this->get_frontend_config()
        );
        
        // Enqueue immediately - we need it for cart functionality.
        wp_enqueue_script( 'urst-frontend' );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        // DEBUG: Log which page we're on
        error_log( '[URST DEBUG] Admin page hook: ' . $hook );
        
        // TEMPORARILY load on ALL admin pages for debugging
        // TODO: Remove this after fixing the issue
        // if ( ! $this->is_plugin_admin_page( $hook ) ) {
        //     return;
        // }

        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        // Admin CSS.
        wp_enqueue_style(
            'urst-admin',
            URST_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            URST_VERSION
        );

        // Admin JS - load non-minified for debugging
        wp_enqueue_script(
            'urst-admin',
            URST_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'jquery-ui-sortable' ),
            URST_VERSION,
            true
        );

        // Localize script.
        wp_localize_script(
            'urst-admin',
            'urstAdminConfig',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'urst_admin_nonce' ),
                'i18n'     => array(
                    'addGroup'      => __( 'Enter group label...', 'unstoppable-restaurant' ),
                    'addModifier'   => __( 'Add Option', 'unstoppable-restaurant' ),
                    'confirmDelete' => __( 'Are you sure you want to delete this?', 'unstoppable-restaurant' ),
                    'saving'        => __( 'Saving...', 'unstoppable-restaurant' ),
                    'saved'         => __( 'Saved!', 'unstoppable-restaurant' ),
                    'error'         => __( 'An error occurred.', 'unstoppable-restaurant' ),
                ),
            )
        );
    }

    /**
     * Check if current page is a plugin admin page.
     *
     * @param string $hook Current admin page hook.
     * @return bool
     */
    private function is_plugin_admin_page( $hook ) {
        $plugin_pages = array(
            'toplevel_page_unstoppable-restaurant',
            'restaurant_page_ur-settings',
            'edit.php',
            'post.php',
            'post-new.php',
        );

        // Check direct hook match.
        if ( in_array( $hook, $plugin_pages, true ) ) {
            // For edit.php, post.php, post-new.php - check if it's our CPT.
            if ( in_array( $hook, array( 'edit.php', 'post.php', 'post-new.php' ), true ) ) {
                return $this->is_our_post_type();
            }
            return true;
        }

        return false;
    }

    /**
     * Check if current screen is one of our post types.
     *
     * @return bool
     */
    private function is_our_post_type() {
        // Our custom post types.
        $our_post_types = array( 'ur_modifier_flow', 'urst_menu' );

        // Check via $_GET for edit.php and post-new.php.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['post_type'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post_type = sanitize_key( $_GET['post_type'] );
            return in_array( $post_type, $our_post_types, true );
        }

        // Check via $_GET['post'] for post.php (editing existing).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['post'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post_id = absint( $_GET['post'] );
            $post_type = get_post_type( $post_id );
            return in_array( $post_type, $our_post_types, true );
        }

        // Check via global $post.
        global $post;
        if ( $post && in_array( $post->post_type, $our_post_types, true ) ) {
            return true;
        }

        return false;
    }

    /**
     * Get frontend configuration.
     *
     * @return array
     */
    private function get_frontend_config() {
        $config = array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'urst_frontend_nonce' ),
            'currency' => array(
                'symbol'    => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
                'decimal'   => wc_get_price_decimal_separator(),
                'thousand'  => wc_get_price_thousand_separator(),
                'decimals'  => wc_get_price_decimals(),
                'position'  => get_option( 'woocommerce_currency_pos' ),
            ),
            'i18n'     => array(
                // Cart messages.
                'addToCart'         => __( 'Added!', 'unstoppable-restaurant' ),
                'addToOrder'        => __( 'Add to Order', 'unstoppable-restaurant' ),
                'viewCart'          => __( 'View Order', 'unstoppable-restaurant' ),
                'checkout'          => __( 'Checkout', 'unstoppable-restaurant' ),
                'emptyCart'         => __( 'Your order is empty', 'unstoppable-restaurant' ),
                'emptyCartHint'     => __( 'Browse the menu to get started', 'unstoppable-restaurant' ),

                // Stock.
                'outOfStock'        => __( 'Sold out', 'unstoppable-restaurant' ),
                'soldOut'           => __( 'Sold Out', 'unstoppable-restaurant' ),

                // Loading/errors.
                'loading'           => __( 'Loading...', 'unstoppable-restaurant' ),
                'error'             => __( 'Oops! Something went wrong. Please try again.', 'unstoppable-restaurant' ),

                // Modifiers.
                'next'              => __( 'Continue', 'unstoppable-restaurant' ),
                'back'              => __( 'Back', 'unstoppable-restaurant' ),
                'modifierRequired'  => __( 'Please make a selection to continue.', 'unstoppable-restaurant' ),
                'selectAtLeast'     => __( 'Please select at least %d option(s).', 'unstoppable-restaurant' ),
                'selectAtMost'      => __( 'You can select up to %d option(s).', 'unstoppable-restaurant' ),

                // Scheduling.
                'closed'            => __( 'We\'re currently closed for orders.', 'unstoppable-restaurant' ),
                'opensAt'           => __( 'Opens at %s', 'unstoppable-restaurant' ),
            ),
        );

        // Add scheduling data.
        $config['scheduling'] = $this->get_scheduling_data();

        return $config;
    }

    /**
     * Get scheduling data for frontend.
     *
     * @return array
     */
    private function get_scheduling_data() {
        // Check if ScheduleManager class exists.
        if ( ! class_exists( '\\Unstoppable\\Restaurant\\Modules\\Scheduling\\ScheduleManager' ) ) {
            return array(
                'is_open'  => true,
                'message'  => '',
                'services' => array( 'pickup' ),
            );
        }

        $manager = \Unstoppable\Restaurant\Modules\Scheduling\ScheduleManager::instance();

        return array(
            'is_open'  => $manager->is_open(),
            'message'  => $manager->get_availability_message(),
            'services' => $manager->get_enabled_services(),
        );
    }
}
