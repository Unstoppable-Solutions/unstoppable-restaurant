<?php
/**
 * Menu Rules Engine.
 *
 * PHASE 7.1 - Menu Intelligence Layer.
 *
 * Evaluates whether a menu should be active based on:
 * - Day of week rules
 * - Time of day rules
 * - Date range rules
 * - Product availability
 * - Priority/fallback logic
 *
 * This is PURE LOGIC. No UI. No templates.
 *
 * @package Unstoppable_Restaurant
 * @since 2.13.0
 */

namespace Unstoppable\Restaurant\Menu;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MenuRulesEngine class.
 *
 * @since 2.13.0
 */
final class MenuRulesEngine {

    /**
     * Singleton instance.
     *
     * @var MenuRulesEngine|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return MenuRulesEngine
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
     * Check if a menu is currently active based on all rules.
     *
     * This is THE method to call before rendering a menu.
     *
     * @param MenuDefinition $menu    Menu to evaluate.
     * @param array          $context Optional context: ['time' => timestamp, 'channel' => 'web']
     * @return bool
     */
    public function is_active( MenuDefinition $menu, array $context = array() ) {
        // Must be published.
        if ( ! $menu->is_published() ) {
            return false;
        }

        // Get rules from settings.
        $rules = $menu->get_setting( 'rules', array() );

        // If no rules defined, menu is always active (when published).
        if ( empty( $rules ) || ! is_array( $rules ) ) {
            return true;
        }

        // Check if rules are enabled.
        if ( empty( $rules['enabled'] ) ) {
            return true;
        }

        // Get evaluation time.
        $time = isset( $context['time'] ) ? $context['time'] : current_time( 'timestamp' );

        // Evaluate each rule type.
        $checks = array(
            'day_of_week' => $this->check_day_of_week( $rules, $time ),
            'time_of_day' => $this->check_time_of_day( $rules, $time ),
            'date_range'  => $this->check_date_range( $rules, $time ),
        );

        /**
         * Filter rule check results.
         *
         * @since 2.13.0
         * @param array          $checks  Rule check results.
         * @param MenuDefinition $menu    Menu being evaluated.
         * @param array          $context Evaluation context.
         */
        $checks = apply_filters( 'urst_menu_rule_checks', $checks, $menu, $context );

        // All enabled checks must pass.
        foreach ( $checks as $check ) {
            if ( false === $check ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the reason why a menu is inactive.
     *
     * Useful for admin UI and debugging.
     *
     * @param MenuDefinition $menu    Menu to evaluate.
     * @param array          $context Optional context.
     * @return string|null Reason string or null if active.
     */
    public function get_inactive_reason( MenuDefinition $menu, array $context = array() ) {
        if ( ! $menu->is_published() ) {
            return __( 'Menu is not published', 'unstoppable-restaurant' );
        }

        $rules = $menu->get_setting( 'rules', array() );

        if ( empty( $rules ) || empty( $rules['enabled'] ) ) {
            return null; // No rules = active.
        }

        $time = isset( $context['time'] ) ? $context['time'] : current_time( 'timestamp' );

        if ( ! $this->check_day_of_week( $rules, $time ) ) {
            $day_name = date_i18n( 'l', $time );
            return sprintf( __( 'Not available on %s', 'unstoppable-restaurant' ), $day_name );
        }

        if ( ! $this->check_time_of_day( $rules, $time ) ) {
            return __( 'Outside scheduled hours', 'unstoppable-restaurant' );
        }

        if ( ! $this->check_date_range( $rules, $time ) ) {
            return __( 'Outside date range', 'unstoppable-restaurant' );
        }

        return null;
    }

    /**
     * Check day of week rule.
     *
     * @param array $rules Rules array.
     * @param int   $time  Timestamp to check.
     * @return bool|null True if passes, false if fails, null if not configured.
     */
    private function check_day_of_week( array $rules, $time ) {
        if ( empty( $rules['days'] ) ) {
            return null; // Not configured.
        }

        $days = (array) $rules['days'];

        if ( empty( $days ) ) {
            return null;
        }

        // Get current day (0 = Sunday, 6 = Saturday).
        $current_day = (int) date( 'w', $time );

        return in_array( $current_day, array_map( 'intval', $days ), true );
    }

    /**
     * Check time of day rule.
     *
     * @param array $rules Rules array.
     * @param int   $time  Timestamp to check.
     * @return bool|null True if passes, false if fails, null if not configured.
     */
    private function check_time_of_day( array $rules, $time ) {
        if ( empty( $rules['time_start'] ) && empty( $rules['time_end'] ) ) {
            return null; // Not configured.
        }

        // Parse time strings (HH:MM format).
        $time_start = isset( $rules['time_start'] ) ? $rules['time_start'] : '00:00';
        $time_end = isset( $rules['time_end'] ) ? $rules['time_end'] : '23:59';

        // Get current time in HH:MM format.
        $current_time = date( 'H:i', $time );

        // Handle overnight ranges (e.g., 22:00 to 02:00).
        if ( $time_start > $time_end ) {
            // Overnight: either after start OR before end.
            return ( $current_time >= $time_start || $current_time <= $time_end );
        }

        // Normal range: must be between start and end.
        return ( $current_time >= $time_start && $current_time <= $time_end );
    }

    /**
     * Check date range rule.
     *
     * @param array $rules Rules array.
     * @param int   $time  Timestamp to check.
     * @return bool|null True if passes, false if fails, null if not configured.
     */
    private function check_date_range( array $rules, $time ) {
        if ( empty( $rules['date_start'] ) && empty( $rules['date_end'] ) ) {
            return null; // Not configured.
        }

        $current_date = date( 'Y-m-d', $time );

        // Check start date.
        if ( ! empty( $rules['date_start'] ) ) {
            if ( $current_date < $rules['date_start'] ) {
                return false;
            }
        }

        // Check end date.
        if ( ! empty( $rules['date_end'] ) ) {
            if ( $current_date > $rules['date_end'] ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all active menus for a channel.
     *
     * Returns menus that pass ALL rules.
     *
     * @param string $channel Channel: web | qr | kiosk | pos
     * @param array  $context Optional context.
     * @return MenuDefinition[]
     */
    public function get_active_menus( $channel = 'web', array $context = array() ) {
        $repository = MenuRepository::instance();
        $all_menus = $repository->get_all( true ); // Published only.

        $active = array();

        foreach ( $all_menus as $menu ) {
            // Check channel visibility.
            if ( ! $menu->is_visible_for_channel( $channel ) ) {
                continue;
            }

            // Check rules.
            if ( ! $this->is_active( $menu, $context ) ) {
                continue;
            }

            $active[] = $menu;
        }

        // Sort by priority.
        usort( $active, function( $a, $b ) {
            $priority_a = $a->get_setting( 'priority', 10 );
            $priority_b = $b->get_setting( 'priority', 10 );
            return $priority_a - $priority_b;
        } );

        return $active;
    }

    /**
     * Get the best menu for a channel (highest priority active menu).
     *
     * @param string $channel Channel: web | qr | kiosk | pos
     * @param array  $context Optional context.
     * @return MenuDefinition|null
     */
    public function get_best_menu( $channel = 'web', array $context = array() ) {
        $active = $this->get_active_menus( $channel, $context );
        return ! empty( $active ) ? $active[0] : null;
    }

    /**
     * Get fallback menu (lowest priority, no time rules).
     *
     * @param string $channel Channel.
     * @return MenuDefinition|null
     */
    public function get_fallback_menu( $channel = 'web' ) {
        $repository = MenuRepository::instance();

        // Get default menu first.
        $default = $repository->get_default();
        if ( $default && $default->is_visible_for_channel( $channel ) ) {
            return $default;
        }

        // Otherwise, get any active menu.
        $all = $repository->get_all( true );
        foreach ( $all as $menu ) {
            if ( $menu->is_visible_for_channel( $channel ) ) {
                return $menu;
            }
        }

        return null;
    }

    /**
     * Preview what menus would be active at a given time.
     *
     * Useful for admin UI to test scheduling.
     *
     * @param int    $time    Unix timestamp.
     * @param string $channel Channel.
     * @return array {
     *     @type MenuDefinition[] $active   Active menus.
     *     @type array            $inactive Inactive menus with reasons.
     * }
     */
    public function preview_at_time( $time, $channel = 'web' ) {
        $repository = MenuRepository::instance();
        $all_menus = $repository->get_all( true );

        $context = array( 'time' => $time, 'channel' => $channel );
        $active = array();
        $inactive = array();

        foreach ( $all_menus as $menu ) {
            if ( ! $menu->is_visible_for_channel( $channel ) ) {
                $inactive[] = array(
                    'menu'   => $menu,
                    'reason' => __( 'Not available for this channel', 'unstoppable-restaurant' ),
                );
                continue;
            }

            if ( $this->is_active( $menu, $context ) ) {
                $active[] = $menu;
            } else {
                $inactive[] = array(
                    'menu'   => $menu,
                    'reason' => $this->get_inactive_reason( $menu, $context ),
                );
            }
        }

        return array(
            'active'   => $active,
            'inactive' => $inactive,
        );
    }
}
