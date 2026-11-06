<?php
/**
 * Health Check Class.
 *
 * Phase 7.8: Stability & Guardrails.
 * 
 * Lightweight diagnostics to detect configuration issues
 * and warn administrators before they become support tickets.
 *
 * @package Unstoppable_Restaurant
 * @since 2.18.0
 */

namespace Unstoppable\Restaurant\Admin;

use Unstoppable\Restaurant\Menu\MenuRepository;
use Unstoppable\Restaurant\Menu\MenuRulesEngine;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * HealthCheck class.
 *
 * @since 2.18.0
 */
class HealthCheck {

    /**
     * Instance.
     *
     * @var HealthCheck|null
     */
    private static $instance = null;

    /**
     * Cached issues.
     *
     * @var array|null
     */
    private $issues = null;

    /**
     * Get instance.
     *
     * @return HealthCheck
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize health check.
     */
    public function init() {
        // Only run on admin.
        if ( ! is_admin() ) {
            return;
        }

        // Add warnings to admin notices.
        add_action( 'admin_notices', array( $this, 'display_warnings' ) );
    }

    /**
     * Run all health checks and return issues.
     *
     * Results are cached per request.
     *
     * @param bool $force Force re-check.
     * @return array Array of issues.
     */
    public function check( $force = false ) {
        if ( null !== $this->issues && ! $force ) {
            return $this->issues;
        }

        $this->issues = array();

        // Only check on relevant admin pages.
        if ( ! $this->is_relevant_page() ) {
            return $this->issues;
        }

        // Run individual checks.
        $this->check_woocommerce();
        $this->check_menus();
        $this->check_products();
        $this->check_shortcode();
        $this->check_scheduling();

        return $this->issues;
    }

    /**
     * Check if current page is relevant for health checks.
     *
     * @return bool
     */
    private function is_relevant_page() {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return false;
        }

        // Only on dashboard and plugin pages.
        return 'dashboard' === $screen->id
            || strpos( $screen->id, 'unstoppable-restaurant' ) !== false
            || strpos( $screen->id, 'urst' ) !== false;
    }

    /**
     * Check WooCommerce is active.
     */
    private function check_woocommerce() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            $this->issues[] = array(
                'type'    => 'error',
                'code'    => 'woocommerce_missing',
                'message' => __( 'WooCommerce is not active. Unstoppable Restaurant requires WooCommerce to function.', 'unstoppable-restaurant' ),
                'action'  => admin_url( 'plugins.php' ),
                'action_label' => __( 'Manage Plugins', 'unstoppable-restaurant' ),
            );
        }
    }

    /**
     * Check menus configuration.
     */
    private function check_menus() {
        $repository = MenuRepository::instance();
        $menus = $repository->get_all();

        // No menus configured.
        if ( empty( $menus ) ) {
            $this->issues[] = array(
                'type'    => 'warning',
                'code'    => 'no_menus',
                'message' => __( 'No menus configured. Create your first menu to start displaying products.', 'unstoppable-restaurant' ),
                'action'  => admin_url( 'admin.php?page=urst-menus' ),
                'action_label' => __( 'Create Menu', 'unstoppable-restaurant' ),
            );
            return; // No point checking further.
        }

        // Check each menu.
        foreach ( $menus as $menu ) {
            // Menu with no categories.
            $categories = $menu->get_category_ids();
            if ( empty( $categories ) ) {
                $this->issues[] = array(
                    'type'    => 'info',
                    'code'    => 'menu_no_categories',
                    'message' => sprintf(
                        /* translators: %s: menu name */
                        __( 'Menu "%s" has no categories selected. It will show all product categories.', 'unstoppable-restaurant' ),
                        $menu->get_name()
                    ),
                );
            }

            // Menu with sections but no products in sections.
            if ( $menu->has_sections() ) {
                $sections = $menu->get_active_sections();
                $empty_sections = 0;
                foreach ( $sections as $section ) {
                    if ( empty( $section['product_ids'] ) ) {
                        $empty_sections++;
                    }
                }
                if ( $empty_sections > 0 ) {
                    $this->issues[] = array(
                        'type'    => 'info',
                        'code'    => 'empty_sections',
                        'message' => sprintf(
                            /* translators: 1: number of empty sections, 2: menu name */
                            _n(
                                'Menu "%2$s" has %1$d empty section.',
                                'Menu "%2$s" has %1$d empty sections.',
                                $empty_sections,
                                'unstoppable-restaurant'
                            ),
                            $empty_sections,
                            $menu->get_name()
                        ),
                    );
                }
            }
        }

        // Check for active menu.
        $engine = MenuRulesEngine::instance();
        $active = $engine->get_best_menu( 'web' );
        if ( ! $active && count( $menus ) > 0 ) {
            $this->issues[] = array(
                'type'    => 'warning',
                'code'    => 'no_active_menu',
                'message' => __( 'No menu is currently active. Check your scheduling rules or ensure at least one menu is set as default.', 'unstoppable-restaurant' ),
                'action'  => admin_url( 'admin.php?page=urst-menus' ),
                'action_label' => __( 'Check Menus', 'unstoppable-restaurant' ),
            );
        }
    }

    /**
     * Check products exist.
     */
    private function check_products() {
        // Skip if WooCommerce not active.
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Count published products.
        $count = wp_count_posts( 'product' );
        $published = isset( $count->publish ) ? (int) $count->publish : 0;

        if ( 0 === $published ) {
            $this->issues[] = array(
                'type'    => 'warning',
                'code'    => 'no_products',
                'message' => __( 'No products found. Add products to WooCommerce to display them in your menu.', 'unstoppable-restaurant' ),
                'action'  => admin_url( 'edit.php?post_type=product' ),
                'action_label' => __( 'Add Products', 'unstoppable-restaurant' ),
            );
        }
    }

    /**
     * Check shortcode is placed on a page.
     */
    private function check_shortcode() {
        global $wpdb;

        // Check if any page contains our shortcode.
        $found = $wpdb->get_var( "
            SELECT ID FROM {$wpdb->posts}
            WHERE post_type IN ('page', 'post')
            AND post_status = 'publish'
            AND (post_content LIKE '%[unstoppable_menu%' OR post_content LIKE '%[unstoppable_menus%')
            LIMIT 1
        " );

        if ( ! $found ) {
            $this->issues[] = array(
                'type'    => 'info',
                'code'    => 'no_shortcode',
                'message' => __( 'Menu shortcode not found on any page. Add [unstoppable_menu] to a page to display your menu.', 'unstoppable-restaurant' ),
                'action'  => admin_url( 'post-new.php?post_type=page' ),
                'action_label' => __( 'Create Page', 'unstoppable-restaurant' ),
            );
        }
    }

    /**
     * Check scheduling configuration.
     */
    private function check_scheduling() {
        $repository = MenuRepository::instance();
        $menus = $repository->get_all();

        if ( count( $menus ) < 2 ) {
            return; // Scheduling only matters with multiple menus.
        }

        // Count menus with scheduling rules.
        $with_rules = 0;
        foreach ( $menus as $menu ) {
            $rules = $menu->get_setting( 'rules', array() );
            if ( ! empty( $rules['enabled'] ) ) {
                $with_rules++;
            }
        }

        // Multiple menus but no scheduling.
        if ( 0 === $with_rules ) {
            $this->issues[] = array(
                'type'    => 'info',
                'code'    => 'no_scheduling',
                'message' => sprintf(
                    /* translators: %d: number of menus */
                    __( 'You have %d menus but no scheduling rules. Only the default menu will display. Consider adding time-based rules for automatic menu switching.', 'unstoppable-restaurant' ),
                    count( $menus )
                ),
                'action'  => admin_url( 'admin.php?page=urst-menus' ),
                'action_label' => __( 'Configure Scheduling', 'unstoppable-restaurant' ),
            );
        }
    }

    /**
     * Display warnings as admin notices.
     */
    public function display_warnings() {
        // Only show to admins.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $issues = $this->check();

        // Filter to only warnings and errors (not info).
        $important = array_filter( $issues, function( $issue ) {
            return in_array( $issue['type'], array( 'warning', 'error' ), true );
        } );

        // Check if warnings are dismissed.
        $dismissed = get_option( 'urst_health_warnings_dismissed', array() );
        $dismissed_time = get_option( 'urst_health_warnings_dismissed_time', 0 );

        // Re-show after 7 days.
        if ( $dismissed_time && ( time() - $dismissed_time ) > ( 7 * DAY_IN_SECONDS ) ) {
            $dismissed = array();
            delete_option( 'urst_health_warnings_dismissed' );
            delete_option( 'urst_health_warnings_dismissed_time' );
        }

        foreach ( $important as $issue ) {
            // Skip if dismissed.
            if ( in_array( $issue['code'], $dismissed, true ) ) {
                continue;
            }

            $this->render_notice( $issue );
        }
    }

    /**
     * Render a single notice.
     *
     * @param array $issue Issue data.
     */
    private function render_notice( $issue ) {
        $type = 'error' === $issue['type'] ? 'error' : 'warning';
        ?>
        <div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible urst-notice urst-health-notice" data-notice="health" data-code="<?php echo esc_attr( $issue['code'] ); ?>">
            <p>
                <strong><?php esc_html_e( 'Unstoppable Restaurant', 'unstoppable-restaurant' ); ?></strong> —
                <?php echo esc_html( $issue['message'] ); ?>
                <?php if ( ! empty( $issue['action'] ) && ! empty( $issue['action_label'] ) ) : ?>
                    <a href="<?php echo esc_url( $issue['action'] ); ?>" class="button button-small" style="margin-left: 10px;">
                        <?php echo esc_html( $issue['action_label'] ); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }

    /**
     * Get issues by type.
     *
     * @param string $type Issue type: error, warning, info.
     * @return array
     */
    public function get_issues_by_type( $type ) {
        $issues = $this->check();
        return array_filter( $issues, function( $issue ) use ( $type ) {
            return $issue['type'] === $type;
        } );
    }

    /**
     * Check if there are any critical issues.
     *
     * @return bool
     */
    public function has_critical_issues() {
        $errors = $this->get_issues_by_type( 'error' );
        return ! empty( $errors );
    }

    /**
     * Get issue count by type.
     *
     * @return array
     */
    public function get_issue_counts() {
        $issues = $this->check();
        return array(
            'error'   => count( $this->get_issues_by_type( 'error' ) ),
            'warning' => count( $this->get_issues_by_type( 'warning' ) ),
            'info'    => count( $this->get_issues_by_type( 'info' ) ),
            'total'   => count( $issues ),
        );
    }
}
