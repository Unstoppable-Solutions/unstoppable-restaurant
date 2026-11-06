<?php
/**
 * Admin Notices Class.
 *
 * Handles all admin dashboard notices for the plugin.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin_Notices class.
 *
 * @since 1.0.0
 */
class Admin_Notices {

    /**
     * Instance.
     *
     * @var Admin_Notices|null
     */
    private static $instance = null;

    /**
     * Plugin prefix.
     *
     * @var string
     */
    private $prefix = 'urst';

    /**
     * Plugin name.
     *
     * @var string
     */
    private $plugin_name = 'Unstoppable Restaurant';

    /**
     * Get instance.
     *
     * @return Admin_Notices
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize notices.
     */
    public function init() {
        add_action( 'admin_notices', array( $this, 'display_notices' ) );
        add_action( 'admin_notices', array( $this, 'display_flash_notices' ) );
        add_action( 'wp_ajax_' . $this->prefix . '_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Enqueue admin scripts for notice dismissal.
     */
    public function enqueue_scripts() {
        wp_add_inline_script( 'jquery', $this->get_dismiss_script() );
    }

    /**
     * Get dismiss script.
     *
     * @return string
     */
    private function get_dismiss_script() {
        return "
        jQuery(document).ready(function($) {
            $(document).on('click', '.urst-notice .notice-dismiss, .urst-dismiss-notice', function(e) {
                var \$notice = $(this).closest('.urst-notice');
                var noticeType = \$notice.data('notice');
                var noticeCode = \$notice.data('code') || '';
                
                if (noticeType) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'urst_dismiss_notice',
                            notice: noticeType,
                            code: noticeCode,
                            nonce: '" . wp_create_nonce( 'urst_dismiss_notice' ) . "'
                        }
                    });
                }
                
                if ($(this).hasClass('urst-dismiss-notice')) {
                    e.preventDefault();
                    \$notice.fadeOut();
                }
            });
        });
        ";
    }

    /**
     * Display all active notices.
     */
    public function display_notices() {
        // Welcome/Setup notice (shows once after activation).
        $this->maybe_show_welcome_notice();

        // Review request (shows after 7 days of use).
        $this->maybe_show_review_notice();
    }

    /**
     * Welcome/Setup notice after plugin activation.
     */
    private function maybe_show_welcome_notice() {
        // Check if already dismissed.
        if ( get_option( $this->prefix . '_welcome_notice_dismissed' ) ) {
            return;
        }

        // Only show to admins.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Only show on relevant pages.
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        $is_allowed = 'dashboard' === $screen->id
            || strpos( $screen->id, $this->prefix ) !== false
            || strpos( $screen->id, 'unstoppable-restaurant' ) !== false
            || strpos( $screen->id, 'woocommerce' ) !== false;

        if ( ! $is_allowed ) {
            return;
        }

        ?>
        <div class="notice notice-success is-dismissible urst-notice" data-notice="welcome">
            <p>
                <strong><?php echo esc_html( $this->plugin_name ); ?></strong> —
                <?php esc_html_e( 'Thanks for installing! Get started by configuring your settings and adding the menu to a page.', 'unstoppable-restaurant' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=unstoppable-restaurant&tab=settings' ) ); ?>" class="button button-primary" style="margin-left: 10px;">
                    <?php esc_html_e( 'Go to Settings', 'unstoppable-restaurant' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Review request notice after 7 days.
     */
    private function maybe_show_review_notice() {
        // Check if already dismissed.
        if ( get_option( $this->prefix . '_review_notice_dismissed' ) ) {
            return;
        }

        // Check install date.
        $install_date = get_option( $this->prefix . '_install_date' );
        if ( ! $install_date ) {
            update_option( $this->prefix . '_install_date', time() );
            return;
        }

        // Show after 7 days.
        if ( time() < ( $install_date + ( 7 * DAY_IN_SECONDS ) ) ) {
            return;
        }

        // Only show to admins.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Only show on plugin pages.
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'unstoppable-restaurant' ) === false ) {
            return;
        }

        ?>
        <div class="notice notice-info is-dismissible urst-notice" data-notice="review">
            <p>
                <?php
                printf(
                    /* translators: %s: Plugin name */
                    esc_html__( 'Enjoying %s? Please leave a review to help others discover the plugin!', 'unstoppable-restaurant' ),
                    '<strong>' . esc_html( $this->plugin_name ) . '</strong>'
                );
                ?>
                <a href="https://wordpress.org/support/plugin/unstoppable-restaurant/reviews/#new-post" target="_blank" class="button button-primary" style="margin-left: 10px;">
                    <?php esc_html_e( 'Leave a Review', 'unstoppable-restaurant' ); ?>
                </a>
                <button type="button" class="button urst-dismiss-notice" data-notice="review" style="margin-left: 5px;">
                    <?php esc_html_e( 'Maybe Later', 'unstoppable-restaurant' ); ?>
                </button>
            </p>
        </div>
        <?php
    }

    /**
     * AJAX handler to dismiss notices.
     */
    public function ajax_dismiss_notice() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'urst_dismiss_notice', 'nonce', false ) ) {
            wp_send_json_error( 'Invalid security token.' );
        }

        // Verify permissions.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $notice = isset( $_POST['notice'] ) ? sanitize_key( $_POST['notice'] ) : '';

        if ( empty( $notice ) ) {
            wp_send_json_error( 'Invalid notice.' );
        }

        // Whitelist allowed notice types.
        $allowed_notices = array( 'welcome', 'review', 'upgrade', 'license', 'announcement', 'health' );
        if ( ! in_array( $notice, $allowed_notices, true ) ) {
            wp_send_json_error( 'Invalid notice type.' );
        }

        // Special handling for health warnings (track individual codes).
        if ( 'health' === $notice ) {
            $code = isset( $_POST['code'] ) ? sanitize_key( $_POST['code'] ) : '';
            if ( $code ) {
                $dismissed = get_option( 'urst_health_warnings_dismissed', array() );
                if ( ! in_array( $code, $dismissed, true ) ) {
                    $dismissed[] = $code;
                    update_option( 'urst_health_warnings_dismissed', $dismissed );
                    update_option( 'urst_health_warnings_dismissed_time', time() );
                }
            }
            wp_send_json_success();
        }

        // Save dismissal.
        update_option( $this->prefix . '_' . $notice . '_notice_dismissed', true );

        // For notices that re-show after a period, save the date.
        if ( in_array( $notice, array( 'upgrade', 'license' ), true ) ) {
            update_option( $this->prefix . '_' . $notice . '_notice_dismissed_date', time() );
        }

        wp_send_json_success();
    }

    /**
     * Display flash notices (success/error messages).
     * These are one-time notices triggered by user actions.
     */
    public function display_flash_notices() {
        $notices = get_transient( $this->prefix . '_flash_notices' );

        if ( empty( $notices ) || ! is_array( $notices ) ) {
            return;
        }

        foreach ( $notices as $notice ) {
            $type    = isset( $notice['type'] ) ? $notice['type'] : 'info';
            $message = isset( $notice['message'] ) ? $notice['message'] : '';

            if ( empty( $message ) ) {
                continue;
            }

            $allowed_types = array( 'success', 'error', 'warning', 'info' );
            if ( ! in_array( $type, $allowed_types, true ) ) {
                $type = 'info';
            }

            ?>
            <div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
                <p><?php echo wp_kses_post( $message ); ?></p>
            </div>
            <?php
        }

        // Clear flash notices after displaying.
        delete_transient( $this->prefix . '_flash_notices' );
    }

    /**
     * Add a flash notice to be displayed on next page load.
     *
     * @param string $message The notice message.
     * @param string $type    Notice type: success, error, warning, info.
     */
    public static function add_flash_notice( $message, $type = 'success' ) {
        $prefix  = 'urst';
        $notices = get_transient( $prefix . '_flash_notices' );

        if ( ! is_array( $notices ) ) {
            $notices = array();
        }

        $notices[] = array(
            'message' => $message,
            'type'    => $type,
        );

        set_transient( $prefix . '_flash_notices', $notices, 60 );
    }
}
