<?php
/**
 * Plugin Name: Unstoppable Restaurant for WooCommerce
 * Plugin URI: https://unstoppablesolutions.app/plugins/unstoppable-restaurant
 * Description: A next-generation WooCommerce restaurant and food ordering platform with premium layouts and conversational modifiers.
 * Version: 2.18.20
 * Author: Unstoppable Solutions
 * Author URI: https://unstoppablesolutions.app
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: unstoppable-restaurant
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.5
 * Requires Plugins: woocommerce
 *
 * @package Unstoppable_Restaurant
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin constants.
 * 
 * Using URST_ prefix (Unstoppable ReSTaurant) - 4+ characters, unique.
 */
define( 'URST_VERSION', '2.18.20' );
define( 'URST_PLUGIN_FILE', __FILE__ );
define( 'URST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'URST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'URST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'URST_PLUGIN_SLUG', 'unstoppable-restaurant' );

/**
 * Autoloader for plugin classes.
 *
 * @param string $class The fully-qualified class name.
 */
spl_autoload_register( function( $class ) {
    $prefix = 'Unstoppable\\Restaurant\\';
    $base_dir = URST_PLUGIN_DIR . 'includes/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class Unstoppable_Restaurant {

    /**
     * Plugin instance.
     *
     * @var Unstoppable_Restaurant|null
     */
    private static $instance = null;

    /**
     * Module manager instance.
     *
     * @var \Unstoppable\Restaurant\Core\ModuleManager|null
     */
    private $module_manager = null;

    /**
     * Get plugin instance.
     *
     * @since 1.0.0
     * @return Unstoppable_Restaurant
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Load textdomain.
        add_action( 'init', array( $this, 'load_textdomain' ), 5 );

        // Initialize plugin after init (after textdomain loaded).
        add_action( 'init', array( $this, 'init' ), 10 );

        // Declare HPOS compatibility.
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

        // Activation hook.
        register_activation_hook( URST_PLUGIN_FILE, array( $this, 'activate' ) );

        // Deactivation hook.
        register_deactivation_hook( URST_PLUGIN_FILE, array( $this, 'deactivate' ) );

        // Add settings link on plugins page.
        add_filter( 'plugin_action_links_' . URST_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ) );
    }

    /**
     * Load plugin textdomain.
     *
     * @since 2.5.2
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'unstoppable-restaurant',
            false,
            dirname( URST_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Initialize the plugin.
     *
     * @since 1.0.0
     */
    public function init() {
        // Check for WooCommerce.
        if ( ! $this->check_woocommerce() ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Initialize core components.
        $this->init_core();

        // Initialize module manager and load modules.
        $this->module_manager = new \Unstoppable\Restaurant\Core\ModuleManager();
        $this->module_manager->init();

        /**
         * Fires after Unstoppable Restaurant is fully initialized.
         *
         * @since 1.0.0
         */
        do_action( 'unstoppable_restaurant_init' );
    }

    /**
     * Initialize core components.
     */
    private function init_core() {
        // Assets manager.
        \Unstoppable\Restaurant\Core\Assets::instance()->init();

        // Template loader.
        \Unstoppable\Restaurant\Core\TemplateLoader::instance()->init();

        // Product query cache invalidation.
        \Unstoppable\Restaurant\Core\ProductQuery::init_cache_hooks();

        // Menu Post Type (v2.11.0+).
        // MUST init before shortcode to register CPT.
        \Unstoppable\Restaurant\Menu\MenuPostType::instance()->init();

        // Menu system (v2.9.0+).
        \Unstoppable\Restaurant\Menu\MenuShortcode::instance()->init();
        
        // Menu → Cart integration (v2.9.0+).
        // This ensures menu context flows to cart and order items.
        \Unstoppable\Restaurant\Menu\MenuCartIntegration::instance()->init();

        // Settings (admin only).
        if ( is_admin() ) {
            // Phase 7.6: Dashboard (main menu).
            \Unstoppable\Restaurant\Admin\Dashboard::instance()->init();
            
            // Appearance settings (submenu).
            \Unstoppable\Restaurant\Admin\Settings::instance()->init();
            \Unstoppable\Restaurant\Admin\Admin_Notices::instance()->init();
            
            // Phase 7.8: Health Check (stability guardrails).
            \Unstoppable\Restaurant\Admin\HealthCheck::instance()->init();
            
            // Menu Admin (v2.8.0+).
            \Unstoppable\Restaurant\Menu\Admin\MenuAdminPage::instance()->init();
            
            // Run migration if needed (v2.11.0+).
            $this->maybe_migrate_menus();
        }
    }

    /**
     * Migrate legacy menus from wp_options to CPT.
     *
     * Only runs once per site.
     */
    private function maybe_migrate_menus() {
        // Check if already migrated.
        if ( get_option( 'urst_menu_migration_complete' ) ) {
            return;
        }

        // Check if there are legacy menus.
        $legacy = get_option( 'urst_menu_definitions', array() );
        if ( empty( $legacy ) ) {
            // No legacy menus, mark as complete.
            update_option( 'urst_menu_migration_complete', time() );
            return;
        }

        // Run migration.
        $repository = \Unstoppable\Restaurant\Menu\MenuRepository::instance();
        $migrated = $repository->migrate_from_legacy();

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $migrated > 0 ) {
            error_log( "URST: Migrated {$migrated} menus from wp_options to CPT" );
        }
    }

    /**
     * Check if WooCommerce is active.
     *
     * @return bool
     */
    private function check_woocommerce() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Admin notice for missing WooCommerce.
     */
    public function woocommerce_missing_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: %s: WooCommerce plugin link */
                    esc_html__( 'Unstoppable Restaurant for WooCommerce requires %s to be installed and active.', 'unstoppable-restaurant' ),
                    '<a href="' . esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ) . '">WooCommerce</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Declare HPOS compatibility.
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                URST_PLUGIN_FILE,
                true
            );
        }
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        // Set default options if not already set.
        if ( false === get_option( 'urst_settings' ) ) {
            update_option( 'urst_settings', $this->get_default_settings() );
        }

        // Create default menu if it doesn't exist (v2.8.0+).
        $this->ensure_default_menu();

        // Set version.
        update_option( 'urst_version', URST_VERSION );

        // Set install date for review notice.
        if ( ! get_option( 'urst_install_date' ) ) {
            update_option( 'urst_install_date', time() );
        }

        // Flush rewrite rules on next load.
        set_transient( 'urst_flush_rewrite_rules', true, 60 );
    }

    /**
     * Ensure default menu exists.
     *
     * @since 2.8.0
     */
    private function ensure_default_menu() {
        $repository = \Unstoppable\Restaurant\Menu\MenuRepository::instance();
        
        if ( ! $repository->exists( 'default-menu' ) ) {
            $repository->create_default_menu();
        }
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Flush rewrite rules.
        flush_rewrite_rules();

        // Clear any transients.
        delete_transient( 'urst_flush_rewrite_rules' );
    }

    /**
     * Get default settings.
     *
     * @return array
     */
    private function get_default_settings() {
        return array(
            'layout'               => 'classic',
            'primary_color'        => '#2563eb',
            'accent_color'         => '#059669',
            'enable_cart_drawer'   => true,
            'cart_drawer_position' => 'right',
            'enable_modifiers'     => true,
            'service_types'        => array( 'pickup', 'delivery' ),
            'default_service'      => 'pickup',
            'show_images'          => true,
            'show_prices'          => true,
            'show_descriptions'    => true,
        );
    }

    /**
     * Add plugin action links.
     *
     * @param array $links Existing links.
     * @return array Modified links.
     */
    public function add_plugin_action_links( $links ) {
        $plugin_links = array(
            '<a href="' . esc_url( admin_url( 'admin.php?page=unstoppable-restaurant&tab=settings' ) ) . '">Settings</a>',
        );

        return array_merge( $plugin_links, $links );
    }

    /**
     * Get module manager.
     *
     * @return \Unstoppable\Restaurant\Core\ModuleManager|null
     */
    public function modules() {
        return $this->module_manager;
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     *
     * @throws Exception Cannot unserialize singleton.
     */
    public function __wakeup() {
        throw new Exception( esc_html__( 'Cannot unserialize singleton.', 'unstoppable-restaurant' ) );
    }

    /**
     * Get scheduling manager.
     *
     * @since 2.4.0
     * @return \Unstoppable\Restaurant\Modules\Scheduling\ScheduleManager|null
     */
    public function scheduling() {
        if ( class_exists( '\\Unstoppable\\Restaurant\\Modules\\Scheduling\\ScheduleManager' ) ) {
            return \Unstoppable\Restaurant\Modules\Scheduling\ScheduleManager::instance();
        }
        return null;
    }
}

/**
 * Get the main plugin instance.
 *
 * @since 1.0.0
 * @return Unstoppable_Restaurant
 */
function unstoppable_restaurant() {
    return Unstoppable_Restaurant::instance();
}

// Initialize the plugin.
unstoppable_restaurant();
