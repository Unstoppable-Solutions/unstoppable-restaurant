<?php
/**
 * Schedule Manager.
 *
 * Central API for all scheduling operations.
 * Provides a clean interface for checking availability.
 *
 * Usage:
 *   ScheduleManager::instance()->is_open();
 *   ScheduleManager::instance()->get_next_open_time();
 *   ScheduleManager::instance()->is_service_available( 'pickup' );
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Modules\Scheduling;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ScheduleManager class.
 *
 * @since 2.4.0
 */
class ScheduleManager {

    /**
     * Singleton instance.
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Cached schedule data.
     *
     * @var array|null
     */
    private $schedule_cache = null;

    /**
     * Cached closures data.
     *
     * @var array|null
     */
    private $closures_cache = null;

    /**
     * Option keys.
     */
    const OPTION_SCHEDULE      = 'urst_schedule';
    const OPTION_CLOSURES      = 'urst_closures';
    const OPTION_SERVICE_TYPES = 'urst_service_types';

    /**
     * Service type constants.
     */
    const SERVICE_PICKUP   = 'pickup';
    const SERVICE_DELIVERY = 'delivery';

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Check if the restaurant is currently open for orders.
     *
     * @param string|null $service_type Optional service type to check.
     * @return bool
     */
    public function is_open( $service_type = null ) {
        // Check for closures first.
        if ( $this->is_closed_today() ) {
            return false;
        }

        $now = new \DateTime( 'now', wp_timezone() );
        $day = (int) $now->format( 'w' ); // 0 = Sunday, 6 = Saturday.

        $schedule = $this->get_schedule();
        $windows  = $this->get_windows_for_day( $day, $service_type );

        if ( empty( $windows ) ) {
            return false;
        }

        $current_time = $now->format( 'H:i' );

        foreach ( $windows as $window ) {
            if ( $this->time_in_window( $current_time, $window ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a specific service type is available now.
     *
     * @param string $service_type 'pickup' or 'delivery'.
     * @return bool
     */
    public function is_service_available( $service_type ) {
        // Check if service type is enabled.
        $enabled_services = $this->get_enabled_services();
        if ( ! in_array( $service_type, $enabled_services, true ) ) {
            return false;
        }

        return $this->is_open( $service_type );
    }

    /**
     * Get enabled service types.
     *
     * @return array
     */
    public function get_enabled_services() {
        $services = get_option( self::OPTION_SERVICE_TYPES, array( self::SERVICE_PICKUP ) );

        if ( ! is_array( $services ) || empty( $services ) ) {
            return array( self::SERVICE_PICKUP );
        }

        return $services;
    }

    /**
     * Check if today is a closure/blackout date.
     *
     * @return bool
     */
    public function is_closed_today() {
        $closures = $this->get_closures();
        $today    = ( new \DateTime( 'now', wp_timezone() ) )->format( 'Y-m-d' );

        foreach ( $closures as $closure ) {
            if ( $this->date_in_closure( $today, $closure ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get closure reason if closed today.
     *
     * @return string|null
     */
    public function get_closure_reason() {
        $closures = $this->get_closures();
        $today    = ( new \DateTime( 'now', wp_timezone() ) )->format( 'Y-m-d' );

        foreach ( $closures as $closure ) {
            if ( $this->date_in_closure( $today, $closure ) ) {
                return $closure['reason'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get the next time the restaurant opens.
     *
     * @param string|null $service_type Optional service type.
     * @return \DateTime|null
     */
    public function get_next_open_time( $service_type = null ) {
        $now      = new \DateTime( 'now', wp_timezone() );
        $schedule = $this->get_schedule();
        $max_days = 14; // Look ahead max 14 days.

        for ( $i = 0; $i < $max_days; $i++ ) {
            $check_date = ( clone $now )->modify( "+{$i} days" );
            $date_str   = $check_date->format( 'Y-m-d' );
            $day        = (int) $check_date->format( 'w' );

            // Skip if closure.
            if ( $this->is_date_closed( $date_str ) ) {
                continue;
            }

            $windows = $this->get_windows_for_day( $day, $service_type );

            foreach ( $windows as $window ) {
                $open_time = new \DateTime( $date_str . ' ' . $window['start'], wp_timezone() );

                // If today, check if window is still upcoming or current.
                if ( $i === 0 ) {
                    $close_time = new \DateTime( $date_str . ' ' . $window['end'], wp_timezone() );

                    // Handle overnight windows.
                    if ( $close_time <= $open_time ) {
                        $close_time->modify( '+1 day' );
                    }

                    // If we're before this window opens, return open time.
                    if ( $now < $open_time ) {
                        return $open_time;
                    }

                    // If we're currently in this window, we're open now.
                    if ( $now >= $open_time && $now < $close_time ) {
                        return $now; // Open now.
                    }
                } else {
                    // Future day - return first window open time.
                    return $open_time;
                }
            }
        }

        return null; // No open time found in next 14 days.
    }

    /**
     * Get today's hours for display.
     *
     * @param string|null $service_type Optional service type.
     * @return array { open: string, close: string } or empty if closed.
     */
    public function get_todays_hours( $service_type = null ) {
        if ( $this->is_closed_today() ) {
            return array();
        }

        $now     = new \DateTime( 'now', wp_timezone() );
        $day     = (int) $now->format( 'w' );
        $windows = $this->get_windows_for_day( $day, $service_type );

        if ( empty( $windows ) ) {
            return array();
        }

        // Return first and last times (simplification for basic scheduling).
        $first_open = $windows[0]['start'];
        $last_close = $windows[ count( $windows ) - 1 ]['end'];

        return array(
            'open'  => $first_open,
            'close' => $last_close,
        );
    }

    /**
     * Get human-readable availability message.
     *
     * @param string|null $service_type Optional service type.
     * @return string
     */
    public function get_availability_message( $service_type = null ) {
        // Check closure first.
        if ( $this->is_closed_today() ) {
            $reason = $this->get_closure_reason();
            $next   = $this->get_next_open_time( $service_type );

            if ( $reason ) {
                $message = sprintf(
                    /* translators: %s: closure reason */
                    __( 'We\'re closed today (%s).', 'unstoppable-restaurant' ),
                    $reason
                );
            } else {
                $message = __( 'We\'re closed today.', 'unstoppable-restaurant' );
            }

            if ( $next ) {
                $message .= ' ' . sprintf(
                    /* translators: 1: day name, 2: time */
                    __( 'Orders reopen %1$s at %2$s.', 'unstoppable-restaurant' ),
                    wp_date( 'l', $next->getTimestamp() ),
                    wp_date( get_option( 'time_format' ), $next->getTimestamp() )
                );
            }

            return $message;
        }

        // Check if open now.
        if ( $this->is_open( $service_type ) ) {
            $hours = $this->get_todays_hours( $service_type );
            $service_label = $this->get_service_label( $service_type );

            if ( ! empty( $hours ) ) {
                return sprintf(
                    /* translators: 1: service type, 2: open time, 3: close time */
                    __( '%1$s available today %2$s – %3$s', 'unstoppable-restaurant' ),
                    $service_label,
                    wp_date( get_option( 'time_format' ), strtotime( 'today ' . $hours['open'] ) ),
                    wp_date( get_option( 'time_format' ), strtotime( 'today ' . $hours['close'] ) )
                );
            }

            return sprintf(
                /* translators: %s: service type */
                __( '%s available now', 'unstoppable-restaurant' ),
                $service_label
            );
        }

        // Closed but will reopen later today or tomorrow.
        $next = $this->get_next_open_time( $service_type );

        if ( $next ) {
            $now   = new \DateTime( 'now', wp_timezone() );
            $today = $now->format( 'Y-m-d' );

            if ( $next->format( 'Y-m-d' ) === $today ) {
                return sprintf(
                    /* translators: %s: time */
                    __( 'We\'re currently closed. Orders open at %s today.', 'unstoppable-restaurant' ),
                    wp_date( get_option( 'time_format' ), $next->getTimestamp() )
                );
            } else {
                return sprintf(
                    /* translators: 1: day name, 2: time */
                    __( 'We\'re currently closed. Orders reopen %1$s at %2$s.', 'unstoppable-restaurant' ),
                    wp_date( 'l', $next->getTimestamp() ),
                    wp_date( get_option( 'time_format' ), $next->getTimestamp() )
                );
            }
        }

        return __( 'We\'re currently closed.', 'unstoppable-restaurant' );
    }

    /**
     * Get service label for display.
     *
     * @param string|null $service_type Service type.
     * @return string
     */
    private function get_service_label( $service_type ) {
        if ( self::SERVICE_PICKUP === $service_type ) {
            return __( 'Pickup', 'unstoppable-restaurant' );
        }

        if ( self::SERVICE_DELIVERY === $service_type ) {
            return __( 'Delivery', 'unstoppable-restaurant' );
        }

        // If no specific service, show generic or combined.
        $services = $this->get_enabled_services();

        if ( count( $services ) === 1 ) {
            return $this->get_service_label( $services[0] );
        }

        return __( 'Ordering', 'unstoppable-restaurant' );
    }

    /**
     * Check if a date is closed.
     *
     * @param string $date Date in Y-m-d format.
     * @return bool
     */
    public function is_date_closed( $date ) {
        $closures = $this->get_closures();

        foreach ( $closures as $closure ) {
            if ( $this->date_in_closure( $date, $closure ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a date falls within a closure.
     *
     * @param string $date    Date to check (Y-m-d).
     * @param array  $closure Closure data.
     * @return bool
     */
    private function date_in_closure( $date, $closure ) {
        $check = strtotime( $date );

        if ( isset( $closure['date'] ) ) {
            // Single date closure.
            return $date === $closure['date'];
        }

        if ( isset( $closure['start'] ) && isset( $closure['end'] ) ) {
            // Date range.
            $start = strtotime( $closure['start'] );
            $end   = strtotime( $closure['end'] );
            return $check >= $start && $check <= $end;
        }

        return false;
    }

    /**
     * Check if time is within a window.
     *
     * @param string $time   Time to check (H:i).
     * @param array  $window Window with 'start' and 'end'.
     * @return bool
     */
    private function time_in_window( $time, $window ) {
        $check = strtotime( $time );
        $start = strtotime( $window['start'] );
        $end   = strtotime( $window['end'] );

        // Handle overnight windows (e.g., 22:00 - 02:00).
        if ( $end <= $start ) {
            // Overnight: either after start OR before end.
            return $check >= $start || $check < $end;
        }

        return $check >= $start && $check < $end;
    }

    /**
     * Get schedule data.
     *
     * @return array
     */
    public function get_schedule() {
        if ( null !== $this->schedule_cache ) {
            return $this->schedule_cache;
        }

        $schedule = get_option( self::OPTION_SCHEDULE, array() );

        if ( empty( $schedule ) ) {
            $schedule = $this->get_default_schedule();
        }

        $this->schedule_cache = $schedule;
        return $schedule;
    }

    /**
     * Get closures data.
     *
     * @return array
     */
    public function get_closures() {
        if ( null !== $this->closures_cache ) {
            return $this->closures_cache;
        }

        $closures = get_option( self::OPTION_CLOSURES, array() );

        // Filter out past closures.
        $today = ( new \DateTime( 'now', wp_timezone() ) )->format( 'Y-m-d' );
        $closures = array_filter( $closures, function( $closure ) use ( $today ) {
            if ( isset( $closure['date'] ) ) {
                return $closure['date'] >= $today;
            }
            if ( isset( $closure['end'] ) ) {
                return $closure['end'] >= $today;
            }
            return true;
        } );

        $this->closures_cache = array_values( $closures );
        return $this->closures_cache;
    }

    /**
     * Get time windows for a specific day.
     *
     * @param int         $day          Day of week (0=Sunday, 6=Saturday).
     * @param string|null $service_type Optional service type filter.
     * @return array
     */
    private function get_windows_for_day( $day, $service_type = null ) {
        $schedule = $this->get_schedule();
        $windows  = array();

        // If no service type specified, get all enabled services.
        if ( null === $service_type ) {
            $service_types = $this->get_enabled_services();
        } else {
            $service_types = array( $service_type );
        }

        foreach ( $service_types as $service ) {
            if ( isset( $schedule[ $service ][ $day ] ) ) {
                foreach ( $schedule[ $service ][ $day ] as $window ) {
                    if ( ! empty( $window['start'] ) && ! empty( $window['end'] ) ) {
                        $windows[] = $window;
                    }
                }
            }
        }

        // Sort by start time.
        usort( $windows, function( $a, $b ) {
            return strcmp( $a['start'], $b['start'] );
        } );

        return $windows;
    }

    /**
     * Get default schedule (reasonable restaurant hours).
     *
     * @return array
     */
    private function get_default_schedule() {
        $default_window = array(
            array( 'start' => '11:00', 'end' => '21:00' ),
        );

        return array(
            self::SERVICE_PICKUP => array(
                0 => $default_window, // Sunday
                1 => $default_window, // Monday
                2 => $default_window, // Tuesday
                3 => $default_window, // Wednesday
                4 => $default_window, // Thursday
                5 => $default_window, // Friday
                6 => $default_window, // Saturday
            ),
        );
    }

    /**
     * Save schedule.
     *
     * @param array $schedule Schedule data.
     * @return bool
     */
    public function save_schedule( $schedule ) {
        $this->schedule_cache = null;
        return update_option( self::OPTION_SCHEDULE, $schedule );
    }

    /**
     * Save closures.
     *
     * @param array $closures Closures data.
     * @return bool
     */
    public function save_closures( $closures ) {
        $this->closures_cache = null;
        return update_option( self::OPTION_CLOSURES, $closures );
    }

    /**
     * Save service types.
     *
     * @param array $services Service types.
     * @return bool
     */
    public function save_service_types( $services ) {
        return update_option( self::OPTION_SERVICE_TYPES, $services );
    }

    /**
     * Clear all caches.
     */
    public function clear_cache() {
        $this->schedule_cache = null;
        $this->closures_cache = null;
    }
}
