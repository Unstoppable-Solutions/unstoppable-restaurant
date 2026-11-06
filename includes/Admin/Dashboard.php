<?php
/**
 * Restaurant Control Center Dashboard.
 *
 * Phase 7.6: Modern, task-oriented dashboard.
 * UI-only - links to existing screens.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Admin;

use Unstoppable\Restaurant\Menu\MenuRepository;
use Unstoppable\Restaurant\Menu\MenuRulesEngine;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dashboard class.
 *
 * @since 2.16.0
 */
class Dashboard {

    /**
     * Instance.
     *
     * @var Dashboard|null
     */
    private static $instance = null;

    /**
     * Get instance.
     *
     * @return Dashboard
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize dashboard.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ), 9 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register admin menu.
     */
    public function register_menu() {
        // Main menu - Dashboard.
        add_menu_page(
            __( 'Restaurant', 'unstoppable-restaurant' ),
            __( 'Restaurant', 'unstoppable-restaurant' ),
            'manage_woocommerce',
            'unstoppable-restaurant',
            array( $this, 'render_dashboard' ),
            'dashicons-food',
            56
        );

        // Dashboard submenu (same as parent).
        add_submenu_page(
            'unstoppable-restaurant',
            __( 'Dashboard', 'unstoppable-restaurant' ),
            __( 'Dashboard', 'unstoppable-restaurant' ),
            'manage_woocommerce',
            'unstoppable-restaurant',
            array( $this, 'render_dashboard' )
        );

        // Add gold color styling to menu icon.
        add_action( 'admin_head', array( $this, 'admin_menu_icon_style' ) );
    }

    /**
     * Add gold color styling to admin menu icon.
     */
    public function admin_menu_icon_style() {
        ?>
        <style>
            #adminmenu .toplevel_page_unstoppable-restaurant .wp-menu-image:before {
                color: #f0b849;
            }
            #adminmenu .toplevel_page_unstoppable-restaurant:hover .wp-menu-image:before,
            #adminmenu .toplevel_page_unstoppable-restaurant.current .wp-menu-image:before {
                color: #fff;
            }
        </style>
        <?php
    }

    /**
     * Enqueue dashboard assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_unstoppable-restaurant' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'urst-dashboard',
            URST_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            URST_VERSION
        );

        // Inline dashboard styles.
        wp_add_inline_style( 'urst-dashboard', $this->get_dashboard_styles() );
    }

    /**
     * Render the dashboard.
     */
    public function render_dashboard() {
        $status = $this->get_status_data();
        ?>
        <div class="wrap urst-dashboard">
            <h1 class="wp-heading-inline" style="display:none;"><?php esc_html_e( 'Restaurant', 'unstoppable-restaurant' ); ?></h1>
            <hr class="wp-header-end">
            <?php $this->render_header( $status ); ?>
            
            <?php $this->render_insights( $status ); ?>
            
            <div class="urst-dashboard-grid">
                <?php $this->render_action_cards( $status ); ?>
            </div>

            <div class="urst-utilities">
                <?php $this->render_utilities(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get status data for the dashboard.
     *
     * Phase 7.7: Enhanced with intelligent insights.
     *
     * @return array
     */
    private function get_status_data() {
        $repository = MenuRepository::instance();
        $menus = $repository->get_all();
        $menu_count = count( $menus );

        // Get active menu via rules engine.
        $active_menu = null;
        $active_menu_name = __( 'None', 'unstoppable-restaurant' );
        $active_menu_time_window = '';
        $active_menu_has_sections = false;
        $active_menu_section_count = 0;
        $active_menu_category_count = 0;
        
        if ( $menu_count > 0 ) {
            $engine = MenuRulesEngine::instance();
            $active_menu = $engine->get_best_menu( 'web' );
            if ( $active_menu ) {
                $active_menu_name = $active_menu->get_name();
                
                // Get time window if rules are set.
                $rules = $active_menu->get_setting( 'rules', array() );
                if ( ! empty( $rules['enabled'] ) && ! empty( $rules['time_start'] ) && ! empty( $rules['time_end'] ) ) {
                    $active_menu_time_window = $rules['time_start'] . ' – ' . $rules['time_end'];
                }
                
                // Check for sections.
                $sections = $active_menu->get_setting( 'sections', array() );
                $active_sections = array_filter( $sections, function( $s ) {
                    return ! empty( $s['is_active'] );
                } );
                $active_menu_has_sections = ! empty( $active_sections );
                $active_menu_section_count = count( $active_sections );
                
                // Get category count.
                $categories = $active_menu->get_setting( 'categories', array() );
                $active_menu_category_count = count( $categories );
            }
        }

        // Count menus with scheduling rules enabled.
        $menus_with_rules = 0;
        $menus_with_sections = 0;
        $total_sections = 0;
        
        foreach ( $menus as $menu ) {
            $rules = $menu->get_setting( 'rules', array() );
            if ( ! empty( $rules['enabled'] ) ) {
                $menus_with_rules++;
            }
            
            $sections = $menu->get_setting( 'sections', array() );
            $active_sections = array_filter( $sections, function( $s ) {
                return ! empty( $s['is_active'] );
            } );
            if ( ! empty( $active_sections ) ) {
                $menus_with_sections++;
                $total_sections += count( $active_sections );
            }
        }

        // Get modifier groups count (from CPT, not legacy option).
        $modifier_count = wp_count_posts( 'ur_modifier_flow' );
        $modifier_count = isset( $modifier_count->publish ) ? (int) $modifier_count->publish : 0;

        // Get labels count.
        $labels = get_option( 'urst_product_labels', array() );
        $label_count = count( $labels );
        
        // Build insights array.
        $insights = $this->build_insights(
            $menu_count,
            $menus_with_rules,
            $menus_with_sections,
            $total_sections,
            $active_menu,
            $active_menu_time_window,
            $modifier_count,
            $label_count
        );

        return array(
            'ordering_live'              => true, // Always live in Free version.
            'active_menu'                => $active_menu_name,
            'active_menu_slug'           => $active_menu ? $active_menu->get_slug() : '',
            'active_menu_time_window'    => $active_menu_time_window,
            'active_menu_has_sections'   => $active_menu_has_sections,
            'active_menu_section_count'  => $active_menu_section_count,
            'active_menu_category_count' => $active_menu_category_count,
            'channel'                    => 'web',
            'menu_count'                 => $menu_count,
            'menus_with_rules'           => $menus_with_rules,
            'menus_with_sections'        => $menus_with_sections,
            'total_sections'             => $total_sections,
            'scheduling_active'          => $menus_with_rules > 0,
            'modifier_count'             => $modifier_count,
            'label_count'                => $label_count,
            'insights'                   => $insights,
        );
    }
    
    /**
     * Build intelligent insights based on current configuration.
     *
     * Phase 7.7: Read-only insights to help restaurant owners understand their setup.
     *
     * @param int              $menu_count          Total menu count.
     * @param int              $menus_with_rules    Menus with scheduling rules.
     * @param int              $menus_with_sections Menus using sections.
     * @param int              $total_sections      Total sections across all menus.
     * @param MenuDefinition|null $active_menu      Currently active menu.
     * @param string           $time_window         Active menu time window.
     * @param int              $modifier_count      Number of modifier groups.
     * @param int              $label_count         Number of product labels.
     * @return array Array of insight items with type, icon, and message.
     */
    private function build_insights( $menu_count, $menus_with_rules, $menus_with_sections, $total_sections, $active_menu, $time_window, $modifier_count, $label_count ) {
        $insights = array();
        
        // No menus configured - critical.
        if ( 0 === $menu_count ) {
            $insights[] = array(
                'type'    => 'warning',
                'icon'    => 'dashicons-warning',
                'message' => __( 'No menus configured. Create your first menu to start accepting orders.', 'unstoppable-restaurant' ),
            );
            return $insights; // No point showing other insights.
        }
        
        // Active menu info.
        if ( $active_menu ) {
            $menu_info = $active_menu->get_name();
            if ( ! empty( $time_window ) ) {
                /* translators: 1: menu name, 2: time window */
                $insights[] = array(
                    'type'    => 'success',
                    'icon'    => 'dashicons-clock',
                    'message' => sprintf( __( 'Active Menu: %1$s (%2$s)', 'unstoppable-restaurant' ), '<strong>' . esc_html( $menu_info ) . '</strong>', esc_html( $time_window ) ),
                );
            } else {
                /* translators: %s: menu name */
                $insights[] = array(
                    'type'    => 'info',
                    'icon'    => 'dashicons-restaurant',
                    'message' => sprintf( __( 'Active Menu: %s', 'unstoppable-restaurant' ), '<strong>' . esc_html( $menu_info ) . '</strong>' ),
                );
            }
        } else {
            $insights[] = array(
                'type'    => 'warning',
                'icon'    => 'dashicons-hidden',
                'message' => __( 'No menu is currently active. Check your scheduling rules.', 'unstoppable-restaurant' ),
            );
        }
        
        // Menu summary.
        if ( $menu_count > 1 ) {
            if ( $menus_with_rules > 0 ) {
                /* translators: 1: total menus, 2: menus with active rules */
                $insights[] = array(
                    'type'    => 'info',
                    'icon'    => 'dashicons-list-view',
                    'message' => sprintf( __( '%1$d menus configured, %2$d with scheduling rules', 'unstoppable-restaurant' ), $menu_count, $menus_with_rules ),
                );
            } else {
                /* translators: %d: total menus */
                $insights[] = array(
                    'type'    => 'info',
                    'icon'    => 'dashicons-list-view',
                    'message' => sprintf( __( '%d menus configured', 'unstoppable-restaurant' ), $menu_count ),
                );
            }
        }
        
        // Scheduling warning.
        if ( $menu_count > 1 && 0 === $menus_with_rules ) {
            $insights[] = array(
                'type'    => 'warning',
                'icon'    => 'dashicons-clock',
                'message' => __( 'Multiple menus but no scheduling rules. Only one menu will display.', 'unstoppable-restaurant' ),
            );
        }
        
        // Sections info.
        if ( $menus_with_sections > 0 ) {
            /* translators: 1: number of sections, 2: number of menus */
            $insights[] = array(
                'type'    => 'success',
                'icon'    => 'dashicons-category',
                'message' => sprintf( 
                    _n( 
                        '%1$d section configured on %2$d menu', 
                        '%1$d sections configured across %2$d menus', 
                        $total_sections, 
                        'unstoppable-restaurant' 
                    ), 
                    $total_sections, 
                    $menus_with_sections 
                ),
            );
        }
        
        // Modifiers info.
        if ( $modifier_count > 0 ) {
            /* translators: %d: number of modifier groups */
            $insights[] = array(
                'type'    => 'success',
                'icon'    => 'dashicons-forms',
                'message' => sprintf( 
                    _n( 
                        '%d modifier group ready for products', 
                        '%d modifier groups ready for products', 
                        $modifier_count, 
                        'unstoppable-restaurant' 
                    ), 
                    $modifier_count 
                ),
            );
        }
        
        // Labels info.
        if ( $label_count > 0 ) {
            /* translators: %d: number of labels */
            $insights[] = array(
                'type'    => 'success',
                'icon'    => 'dashicons-tag',
                'message' => sprintf( 
                    _n( 
                        '%d product label available', 
                        '%d product labels available', 
                        $label_count, 
                        'unstoppable-restaurant' 
                    ), 
                    $label_count 
                ),
            );
        }
        
        return $insights;
    }

    /**
     * Render status header.
     *
     * @param array $status Status data.
     */
    private function render_header( $status ) {
        ?>
        <div class="urst-dashboard-header">
            <div class="urst-header-main">
                <h1>
                    <span class="dashicons dashicons-food"></span>
                    <?php esc_html_e( 'Restaurant Control Center', 'unstoppable-restaurant' ); ?>
                </h1>
                <p class="urst-tagline"><?php esc_html_e( 'Manage your online ordering experience', 'unstoppable-restaurant' ); ?></p>
            </div>

            <div class="urst-status-bar">
                <div class="urst-status-item urst-status-live">
                    <span class="urst-status-indicator <?php echo $status['ordering_live'] ? 'is-live' : 'is-paused'; ?>"></span>
                    <span class="urst-status-label">
                        <?php echo $status['ordering_live'] ? esc_html__( 'LIVE', 'unstoppable-restaurant' ) : esc_html__( 'PAUSED', 'unstoppable-restaurant' ); ?>
                    </span>
                </div>

                <div class="urst-status-item">
                    <span class="dashicons dashicons-restaurant"></span>
                    <span class="urst-status-text">
                        <?php 
                        /* translators: %s: active menu name */
                        printf( esc_html__( 'Active: %s', 'unstoppable-restaurant' ), '<strong>' . esc_html( $status['active_menu'] ) . '</strong>' ); 
                        ?>
                    </span>
                </div>

                <div class="urst-status-item">
                    <span class="dashicons dashicons-laptop"></span>
                    <span class="urst-status-text">
                        <?php 
                        /* translators: %s: channel name */
                        printf( esc_html__( 'Channel: %s', 'unstoppable-restaurant' ), '<strong>' . esc_html( ucfirst( $status['channel'] ) ) . '</strong>' ); 
                        ?>
                    </span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render insights panel.
     *
     * Phase 7.7: Read-only intelligent insights.
     *
     * @param array $status Status data including insights array.
     */
    private function render_insights( $status ) {
        if ( empty( $status['insights'] ) ) {
            return;
        }
        ?>
        <div class="urst-insights-panel">
            <h2 class="urst-insights-title">
                <span class="dashicons dashicons-lightbulb"></span>
                <?php esc_html_e( 'Restaurant Insights', 'unstoppable-restaurant' ); ?>
            </h2>
            <div class="urst-insights-list">
                <?php foreach ( $status['insights'] as $insight ) : ?>
                    <div class="urst-insight urst-insight-<?php echo esc_attr( $insight['type'] ); ?>">
                        <span class="dashicons <?php echo esc_attr( $insight['icon'] ); ?>"></span>
                        <span class="urst-insight-message"><?php echo wp_kses_post( $insight['message'] ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render action cards.
     *
     * @param array $status Status data.
     */
    private function render_action_cards( $status ) {
        ?>
        <!-- Menus Card -->
        <div class="urst-card urst-card-action">
            <div class="urst-card-icon">
                <span class="dashicons dashicons-list-view"></span>
            </div>
            <div class="urst-card-content">
                <h3><?php esc_html_e( 'Menus', 'unstoppable-restaurant' ); ?></h3>
                <p class="urst-card-stat">
                    <?php 
                    /* translators: %d: number of menus */
                    printf( esc_html( _n( '%d menu', '%d menus', $status['menu_count'], 'unstoppable-restaurant' ) ), $status['menu_count'] ); 
                    ?>
                </p>
                <p class="urst-card-desc"><?php esc_html_e( 'Create and manage your restaurant menus, categories, and sections.', 'unstoppable-restaurant' ); ?></p>
            </div>
            <div class="urst-card-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=urst-menus' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Manage Menus', 'unstoppable-restaurant' ); ?>
                </a>
            </div>
        </div>

        <!-- Scheduling Card -->
        <div class="urst-card urst-card-action">
            <div class="urst-card-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="urst-card-content">
                <h3><?php esc_html_e( 'Scheduling', 'unstoppable-restaurant' ); ?></h3>
                <p class="urst-card-stat">
                    <?php 
                    if ( $status['scheduling_active'] ) {
                        echo '<span class="urst-badge urst-badge-success">' . esc_html__( 'Rules Active', 'unstoppable-restaurant' ) . '</span>';
                    } else {
                        echo '<span class="urst-badge">' . esc_html__( 'No Rules', 'unstoppable-restaurant' ) . '</span>';
                    }
                    ?>
                </p>
                <p class="urst-card-desc"><?php esc_html_e( 'Set up time-based menu switching for breakfast, lunch, dinner, etc.', 'unstoppable-restaurant' ); ?></p>
            </div>
            <div class="urst-card-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=urst-menus' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Edit Schedule', 'unstoppable-restaurant' ); ?>
                </a>
            </div>
        </div>

        <!-- Ordering Experience Card -->
        <div class="urst-card urst-card-action">
            <div class="urst-card-icon">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="urst-card-content">
                <h3><?php esc_html_e( 'Ordering Experience', 'unstoppable-restaurant' ); ?></h3>
                <p class="urst-card-stat">
                    <?php 
                    /* translators: %d: number of modifiers */
                    printf( esc_html( _n( '%d modifier group', '%d modifier groups', $status['modifier_count'], 'unstoppable-restaurant' ) ), $status['modifier_count'] ); 
                    ?>
                </p>
                <p class="urst-card-desc"><?php esc_html_e( 'Configure product modifiers, cart drawer, and product modals.', 'unstoppable-restaurant' ); ?></p>
            </div>
            <div class="urst-card-actions">
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ur_modifier_flow' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Configure', 'unstoppable-restaurant' ); ?>
                </a>
            </div>
        </div>

        <!-- Appearance Card -->
        <div class="urst-card urst-card-action">
            <div class="urst-card-icon">
                <span class="dashicons dashicons-admin-appearance"></span>
            </div>
            <div class="urst-card-content">
                <h3><?php esc_html_e( 'Appearance', 'unstoppable-restaurant' ); ?></h3>
                <p class="urst-card-stat">
                    <?php 
                    /* translators: %d: number of labels */
                    printf( esc_html( _n( '%d product label', '%d product labels', $status['label_count'], 'unstoppable-restaurant' ) ), $status['label_count'] ); 
                    ?>
                </p>
                <p class="urst-card-desc"><?php esc_html_e( 'Customize layouts, colors, product labels, and display settings.', 'unstoppable-restaurant' ); ?></p>
            </div>
            <div class="urst-card-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=urst-appearance' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Customize', 'unstoppable-restaurant' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render utilities section.
     */
    private function render_utilities() {
        ?>
        <h2 class="urst-utilities-title"><?php esc_html_e( 'Quick Actions', 'unstoppable-restaurant' ); ?></h2>
        
        <div class="urst-utilities-grid">
            <!-- Shortcodes -->
            <div class="urst-utility-card">
                <span class="dashicons dashicons-shortcode"></span>
                <h4><?php esc_html_e( 'Shortcodes', 'unstoppable-restaurant' ); ?></h4>
                <p><?php esc_html_e( 'Copy shortcodes to display menus on any page.', 'unstoppable-restaurant' ); ?></p>
                <div class="urst-shortcode-box">
                    <code>[unstoppable_menu]</code>
                    <button type="button" class="button button-small urst-copy-btn" data-copy="[unstoppable_menu]">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </div>
            </div>

            <!-- View Menu -->
            <div class="urst-utility-card">
                <span class="dashicons dashicons-visibility"></span>
                <h4><?php esc_html_e( 'View Menu', 'unstoppable-restaurant' ); ?></h4>
                <p><?php esc_html_e( 'Preview your menu on the frontend.', 'unstoppable-restaurant' ); ?></p>
                <?php 
                $menu_page_id = $this->find_menu_page();
                if ( $menu_page_id ) : 
                ?>
                    <a href="<?php echo esc_url( get_permalink( $menu_page_id ) ); ?>" class="button" target="_blank">
                        <?php esc_html_e( 'Open Menu', 'unstoppable-restaurant' ); ?>
                        <span class="dashicons dashicons-external"></span>
                    </a>
                <?php else : ?>
                    <p class="urst-muted"><?php esc_html_e( 'Add shortcode to a page first.', 'unstoppable-restaurant' ); ?></p>
                <?php endif; ?>
            </div>

            <!-- Documentation -->
            <div class="urst-utility-card">
                <span class="dashicons dashicons-book"></span>
                <h4><?php esc_html_e( 'Documentation', 'unstoppable-restaurant' ); ?></h4>
                <p><?php esc_html_e( 'Learn how to get the most out of Unstoppable Restaurant.', 'unstoppable-restaurant' ); ?></p>
                <a href="https://unstoppablesolutions.app/docs/restaurant/" class="button" target="_blank">
                    <?php esc_html_e( 'Read Docs', 'unstoppable-restaurant' ); ?>
                    <span class="dashicons dashicons-external"></span>
                </a>
            </div>

            <!-- Diagnostics -->
            <div class="urst-utility-card">
                <span class="dashicons dashicons-admin-tools"></span>
                <h4><?php esc_html_e( 'Diagnostics', 'unstoppable-restaurant' ); ?></h4>
                <p><?php esc_html_e( 'System info and troubleshooting tools.', 'unstoppable-restaurant' ); ?></p>
                <button type="button" class="button urst-show-diagnostics">
                    <?php esc_html_e( 'View Info', 'unstoppable-restaurant' ); ?>
                </button>
                <div class="urst-diagnostics-panel" style="display: none;">
                    <?php $this->render_diagnostics(); ?>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Copy shortcode
            $('.urst-copy-btn').on('click', function() {
                var text = $(this).data('copy');
                navigator.clipboard.writeText(text).then(function() {
                    alert('Copied to clipboard!');
                });
            });

            // Toggle diagnostics
            $('.urst-show-diagnostics').on('click', function() {
                $(this).closest('.urst-utility-card').find('.urst-diagnostics-panel').slideToggle();
            });
        });
        </script>
        <?php
    }

    /**
     * Render diagnostics info.
     */
    private function render_diagnostics() {
        $repository = MenuRepository::instance();
        $menus = $repository->get_all();
        ?>
        <table class="urst-diagnostics-table">
            <tr>
                <th><?php esc_html_e( 'Plugin Version', 'unstoppable-restaurant' ); ?></th>
                <td><?php echo esc_html( URST_VERSION ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'WordPress Version', 'unstoppable-restaurant' ); ?></th>
                <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'WooCommerce Version', 'unstoppable-restaurant' ); ?></th>
                <td><?php echo defined( 'WC_VERSION' ) ? esc_html( WC_VERSION ) : esc_html__( 'Not installed', 'unstoppable-restaurant' ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'PHP Version', 'unstoppable-restaurant' ); ?></th>
                <td><?php echo esc_html( phpversion() ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Active Theme', 'unstoppable-restaurant' ); ?></th>
                <td><?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Total Menus', 'unstoppable-restaurant' ); ?></th>
                <td><?php echo esc_html( count( $menus ) ); ?></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Find a page that has the menu shortcode.
     *
     * @return int|false Page ID or false.
     */
    private function find_menu_page() {
        global $wpdb;

        $page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'page' 
            AND post_status = 'publish' 
            AND (post_content LIKE '%[unstoppable_menu%' OR post_content LIKE '%[unstoppable_menus%')
            LIMIT 1"
        );

        return $page_id ? absint( $page_id ) : false;
    }

    /**
     * Get dashboard CSS styles.
     *
     * @return string
     */
    private function get_dashboard_styles() {
        return '
            /* Dashboard Layout */
            .urst-dashboard { max-width: 1400px; margin: 20px auto; padding: 0 20px; }
            
            /* Header */
            .urst-dashboard-header {
                background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
                border-radius: 12px;
                padding: 30px;
                margin-bottom: 30px;
                color: #fff;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 20px;
            }
            .urst-header-main h1 {
                color: #fff;
                margin: 0 0 8px 0;
                font-size: 28px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .urst-header-main h1 .dashicons {
                font-size: 32px;
                width: 32px;
                height: 32px;
                color: #f0b849;
            }
            .urst-tagline {
                color: rgba(255,255,255,0.8);
                margin: 0;
                font-size: 15px;
            }
            
            /* Status Bar */
            .urst-status-bar {
                display: flex;
                gap: 24px;
                flex-wrap: wrap;
            }
            .urst-status-item {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                background: rgba(255,255,255,0.1);
                padding: 8px 16px;
                border-radius: 20px;
            }
            .urst-status-item .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
                opacity: 0.8;
            }
            .urst-status-indicator {
                width: 10px;
                height: 10px;
                border-radius: 50%;
                display: inline-block;
            }
            .urst-status-indicator.is-live {
                background: #10b981;
                box-shadow: 0 0 8px #10b981;
                animation: pulse 2s infinite;
            }
            .urst-status-indicator.is-paused {
                background: #ef4444;
            }
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }
            .urst-status-label {
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .urst-status-text strong {
                color: #fff;
            }
            
            /* Insights Panel - Phase 7.7 */
            .urst-insights-panel {
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 20px 24px;
                margin-bottom: 24px;
            }
            .urst-insights-title {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 16px;
                font-weight: 600;
                color: #1e1e1e;
                margin: 0 0 16px 0;
            }
            .urst-insights-title .dashicons {
                color: #f0b849;
            }
            .urst-insights-list {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .urst-insight {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                padding: 10px 14px;
                border-radius: 6px;
                font-size: 14px;
                line-height: 1.4;
            }
            .urst-insight .dashicons {
                flex-shrink: 0;
                margin-top: 1px;
            }
            .urst-insight-message {
                flex: 1;
            }
            .urst-insight-info {
                background: #f0f6fc;
                border-left: 3px solid #2271b1;
            }
            .urst-insight-info .dashicons {
                color: #2271b1;
            }
            .urst-insight-success {
                background: #ecfdf5;
                border-left: 3px solid #10b981;
            }
            .urst-insight-success .dashicons {
                color: #10b981;
            }
            .urst-insight-warning {
                background: #fffbeb;
                border-left: 3px solid #f59e0b;
            }
            .urst-insight-warning .dashicons {
                color: #f59e0b;
            }
            
            /* Dashboard Grid */
            .urst-dashboard-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 24px;
                margin-bottom: 40px;
            }
            
            /* Action Cards */
            .urst-card-action {
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 24px;
                display: flex;
                flex-direction: column;
                transition: all 0.2s;
            }
            .urst-card-action:hover {
                border-color: #2271b1;
                box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            }
            .urst-card-icon {
                width: 48px;
                height: 48px;
                background: linear-gradient(135deg, #f0f6fc 0%, #e8f0fe 100%);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 16px;
            }
            .urst-card-icon .dashicons {
                font-size: 24px;
                width: 24px;
                height: 24px;
                color: #2271b1;
            }
            .urst-card-content {
                flex: 1;
            }
            .urst-card-content h3 {
                margin: 0 0 8px 0;
                font-size: 18px;
                color: #1e1e1e;
            }
            .urst-card-stat {
                margin: 0 0 8px 0;
                font-size: 14px;
                color: #646970;
            }
            .urst-card-desc {
                margin: 0 0 16px 0;
                font-size: 13px;
                color: #646970;
                line-height: 1.5;
            }
            .urst-card-actions {
                margin-top: auto;
            }
            .urst-card-actions .button-primary {
                width: 100%;
                text-align: center;
                padding: 10px 16px;
                height: auto;
            }
            
            /* Badges */
            .urst-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 500;
                background: #f0f0f1;
                color: #646970;
            }
            .urst-badge-success {
                background: #d1fae5;
                color: #065f46;
            }
            
            /* Utilities Section */
            .urst-utilities {
                background: #f9fafb;
                border-radius: 12px;
                padding: 30px;
            }
            .urst-utilities-title {
                margin: 0 0 24px 0;
                font-size: 18px;
                color: #1e1e1e;
            }
            .urst-utilities-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 20px;
            }
            .urst-utility-card {
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                padding: 20px;
            }
            .urst-utility-card > .dashicons {
                font-size: 24px;
                width: 24px;
                height: 24px;
                color: #2271b1;
                margin-bottom: 12px;
            }
            .urst-utility-card h4 {
                margin: 0 0 8px 0;
                font-size: 15px;
            }
            .urst-utility-card p {
                margin: 0 0 12px 0;
                font-size: 13px;
                color: #646970;
            }
            .urst-shortcode-box {
                display: flex;
                align-items: center;
                gap: 8px;
                background: #f0f0f1;
                padding: 8px 12px;
                border-radius: 4px;
            }
            .urst-shortcode-box code {
                flex: 1;
                background: none;
                font-size: 13px;
            }
            .urst-copy-btn {
                padding: 4px 8px !important;
                min-height: 0 !important;
            }
            .urst-copy-btn .dashicons {
                font-size: 14px;
                width: 14px;
                height: 14px;
            }
            .urst-muted {
                color: #a0a5aa;
                font-style: italic;
            }
            .urst-utility-card .button .dashicons {
                font-size: 14px;
                width: 14px;
                height: 14px;
                vertical-align: text-bottom;
                margin-left: 4px;
            }
            
            /* Diagnostics */
            .urst-diagnostics-panel {
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px solid #e5e7eb;
            }
            .urst-diagnostics-table {
                width: 100%;
                font-size: 12px;
            }
            .urst-diagnostics-table th {
                text-align: left;
                padding: 4px 8px 4px 0;
                color: #646970;
                font-weight: 500;
            }
            .urst-diagnostics-table td {
                padding: 4px 0;
            }
            
            /* Responsive */
            @media (max-width: 782px) {
                .urst-dashboard-header {
                    flex-direction: column;
                    text-align: center;
                }
                .urst-status-bar {
                    justify-content: center;
                }
            }
        ';
    }
}
