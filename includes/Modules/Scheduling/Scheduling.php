<?php
/**
 * Scheduling Module.
 *
 * Handles operating hours, closures, and order availability.
 * Blocks orders outside valid times.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Modules\Scheduling;

use Unstoppable\Restaurant\Core\ModuleInterface;
use Unstoppable\Restaurant\Core\TemplateLoader;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load schedule manager.
require_once __DIR__ . '/ScheduleManager.php';

/**
 * Scheduling class.
 *
 * @since 1.0.0
 */
class Scheduling implements ModuleInterface {

    /**
     * Get module ID.
     *
     * @return string
     */
    public function get_id() {
        return 'scheduling';
    }

    /**
     * Get module name.
     *
     * @return string
     */
    public function get_name() {
        return __( 'Scheduling', 'unstoppable-restaurant' );
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
        // Admin settings.
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Frontend enforcement.
        add_filter( 'urst_can_add_to_cart', array( $this, 'check_can_add_to_cart' ), 10, 2 );
        add_action( 'urst_modal_product_availability', array( $this, 'render_availability_message' ) );

        // Cart drawer availability check.
        add_filter( 'urst_cart_drawer_data', array( $this, 'add_scheduling_to_cart_data' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_urst_check_availability', array( $this, 'ajax_check_availability' ) );
        add_action( 'wp_ajax_nopriv_urst_check_availability', array( $this, 'ajax_check_availability' ) );

        // Expose API globally.
        add_action( 'init', array( $this, 'register_global_api' ) );
    }

    /**
     * Register global API access.
     */
    public function register_global_api() {
        // Make ScheduleManager accessible via unstoppable_restaurant()->scheduling()
        add_filter( 'urst_scheduling_manager', function() {
            return ScheduleManager::instance();
        } );
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'unstoppable-restaurant',
            __( 'Schedule', 'unstoppable-restaurant' ),
            __( 'Schedule', 'unstoppable-restaurant' ),
            'manage_woocommerce',
            'urst-schedule',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting( 'urst_schedule', ScheduleManager::OPTION_SERVICE_TYPES, array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_service_types' ),
            'default'           => array( 'pickup' ),
        ) );

        register_setting( 'urst_schedule', ScheduleManager::OPTION_SCHEDULE, array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_schedule' ),
            'default'           => array(),
        ) );

        register_setting( 'urst_schedule', ScheduleManager::OPTION_CLOSURES, array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_closures' ),
            'default'           => array(),
        ) );
    }

    /**
     * Sanitize service types.
     *
     * @param mixed $value Input value.
     * @return array
     */
    public function sanitize_service_types( $value ) {
        if ( ! is_array( $value ) ) {
            return array( 'pickup' );
        }

        $valid = array( 'pickup', 'delivery' );
        $sanitized = array_filter( $value, function( $v ) use ( $valid ) {
            return in_array( $v, $valid, true );
        } );

        return ! empty( $sanitized ) ? array_values( $sanitized ) : array( 'pickup' );
    }

    /**
     * Sanitize schedule data.
     *
     * @param mixed $value Input value.
     * @return array
     */
    public function sanitize_schedule( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $sanitized = array();
        $services  = array( 'pickup', 'delivery' );

        foreach ( $services as $service ) {
            if ( ! isset( $value[ $service ] ) || ! is_array( $value[ $service ] ) ) {
                continue;
            }

            $sanitized[ $service ] = array();

            for ( $day = 0; $day <= 6; $day++ ) {
                if ( ! isset( $value[ $service ][ $day ] ) || ! is_array( $value[ $service ][ $day ] ) ) {
                    continue;
                }

                $sanitized[ $service ][ $day ] = array();

                foreach ( $value[ $service ][ $day ] as $window ) {
                    if ( empty( $window['start'] ) || empty( $window['end'] ) ) {
                        continue;
                    }

                    // Validate time format.
                    if ( ! preg_match( '/^\d{2}:\d{2}$/', $window['start'] ) ||
                         ! preg_match( '/^\d{2}:\d{2}$/', $window['end'] ) ) {
                        continue;
                    }

                    $sanitized[ $service ][ $day ][] = array(
                        'start' => sanitize_text_field( $window['start'] ),
                        'end'   => sanitize_text_field( $window['end'] ),
                    );
                }
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize closures data.
     *
     * @param mixed $value Input value.
     * @return array
     */
    public function sanitize_closures( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $value as $closure ) {
            if ( isset( $closure['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $closure['date'] ) ) {
                // Single date.
                $sanitized[] = array(
                    'date'   => $closure['date'],
                    'reason' => isset( $closure['reason'] ) ? sanitize_text_field( $closure['reason'] ) : '',
                );
            } elseif ( isset( $closure['start'] ) && isset( $closure['end'] ) ) {
                // Date range.
                if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $closure['start'] ) &&
                     preg_match( '/^\d{4}-\d{2}-\d{2}$/', $closure['end'] ) ) {
                    $sanitized[] = array(
                        'start'  => $closure['start'],
                        'end'    => $closure['end'],
                        'reason' => isset( $closure['reason'] ) ? sanitize_text_field( $closure['reason'] ) : '',
                    );
                }
            }
        }

        return $sanitized;
    }

    /**
     * Render admin settings page.
     */
    public function render_admin_page() {
        $manager  = ScheduleManager::instance();
        $services = $manager->get_enabled_services();
        $schedule = $manager->get_schedule();
        $closures = $manager->get_closures();

        $days = array(
            0 => __( 'Sunday', 'unstoppable-restaurant' ),
            1 => __( 'Monday', 'unstoppable-restaurant' ),
            2 => __( 'Tuesday', 'unstoppable-restaurant' ),
            3 => __( 'Wednesday', 'unstoppable-restaurant' ),
            4 => __( 'Thursday', 'unstoppable-restaurant' ),
            5 => __( 'Friday', 'unstoppable-restaurant' ),
            6 => __( 'Saturday', 'unstoppable-restaurant' ),
        );

        include __DIR__ . '/views/admin-schedule.php';
    }

    /**
     * Check if item can be added to cart.
     *
     * @param bool $can_add    Current value.
     * @param int  $product_id Product ID.
     * @return bool
     */
    public function check_can_add_to_cart( $can_add, $product_id ) {
        if ( ! $can_add ) {
            return false;
        }

        return ScheduleManager::instance()->is_open();
    }

    /**
     * Render availability message in product modal.
     *
     * @param array $product Product data.
     */
    public function render_availability_message( $product ) {
        $manager = ScheduleManager::instance();

        if ( ! $manager->is_open() ) {
            $message = $manager->get_availability_message();
            ?>
            <div class="ur-availability-message ur-availability-message--closed">
                <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
                </svg>
                <span><?php echo esc_html( $message ); ?></span>
            </div>
            <?php
        } else {
            // Show positive message with hours.
            $message = $manager->get_availability_message();
            ?>
            <div class="ur-availability-message ur-availability-message--open">
                <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                </svg>
                <span><?php echo esc_html( $message ); ?></span>
            </div>
            <?php
        }
    }

    /**
     * Add scheduling data to cart drawer.
     *
     * @param array $data Cart drawer data.
     * @return array
     */
    public function add_scheduling_to_cart_data( $data ) {
        $manager = ScheduleManager::instance();

        $data['scheduling'] = array(
            'is_open'  => $manager->is_open(),
            'message'  => $manager->get_availability_message(),
            'services' => $manager->get_enabled_services(),
        );

        return $data;
    }

    /**
     * AJAX: Check current availability.
     */
    public function ajax_check_availability() {
        if ( ! check_ajax_referer( 'urst_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'unstoppable-restaurant' ) ) );
        }

        $manager = ScheduleManager::instance();
        $service = isset( $_POST['service_type'] ) ? sanitize_key( $_POST['service_type'] ) : null;

        wp_send_json_success( array(
            'is_open'  => $service ? $manager->is_service_available( $service ) : $manager->is_open(),
            'message'  => $manager->get_availability_message( $service ),
            'services' => $manager->get_enabled_services(),
        ) );
    }
}
