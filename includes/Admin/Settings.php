<?php
/**
 * Admin Settings - Appearance.
 *
 * Phase 7.6: Moved from main dashboard to Appearance submenu.
 * Contains layout, color, and display settings.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Admin;

use Unstoppable\Restaurant\Core\LayoutRegistry;
use Unstoppable\Restaurant\Core\ProductQuery;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings class.
 *
 * @since 1.0.0
 */
class Settings {

    /**
     * Instance.
     *
     * @var Settings|null
     */
    private static $instance = null;

    /**
     * Option name.
     *
     * @var string
     */
    private $option_name = 'urst_settings';

    /**
     * Get instance.
     *
     * @return Settings
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize settings.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_submenu_page' ), 20 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Add submenu page under Restaurant menu.
     */
    public function add_submenu_page() {
        add_submenu_page(
            'unstoppable-restaurant',
            __( 'Appearance', 'unstoppable-restaurant' ),
            __( 'Appearance', 'unstoppable-restaurant' ),
            'manage_woocommerce',
            'urst-appearance',
            array( $this, 'render_page' )
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting(
            'urst_settings_group',
            $this->option_name,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => $this->get_defaults(),
            )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        // Check for Appearance page.
        if ( 'restaurant_page_urst-appearance' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'urst-admin',
            URST_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            URST_VERSION
        );
    }

    /**
     * Get default settings.
     *
     * Phase 2: Only layout-related settings.
     *
     * @return array
     */
    public function get_defaults() {
        return array(
            'layout'            => 'classic',
            'categories'        => array(),
            'show_images'       => true,
            'show_prices'       => true,
            'show_descriptions' => true,
            'primary_color'     => '#2563eb',
            'accent_color'      => '#059669',
        );
    }

    /**
     * Get a specific setting.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get( $key, $default = null ) {
        $settings = get_option( $this->option_name, array() );
        $defaults = $this->get_defaults();

        if ( isset( $settings[ $key ] ) ) {
            return $settings[ $key ];
        }

        if ( isset( $defaults[ $key ] ) ) {
            return $defaults[ $key ];
        }

        return $default;
    }

    /**
     * Get all settings.
     *
     * @return array
     */
    public function get_all() {
        $settings = get_option( $this->option_name, array() );
        return wp_parse_args( $settings, $this->get_defaults() );
    }

    /**
     * Sanitize settings.
     *
     * @param array $input Raw input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        $defaults  = $this->get_defaults();

        // Layout - whitelist validation.
        $registry        = LayoutRegistry::instance();
        $allowed_layouts = $registry->get_ids();

        $sanitized['layout'] = isset( $input['layout'] ) && in_array( $input['layout'], $allowed_layouts, true )
            ? $input['layout']
            : $defaults['layout'];

        // Categories - array of integers.
        $sanitized['categories'] = array();
        if ( isset( $input['categories'] ) && is_array( $input['categories'] ) ) {
            $sanitized['categories'] = array_map( 'absint', array_filter( $input['categories'] ) );
        }

        // Booleans.
        $boolean_fields = array( 'show_images', 'show_prices', 'show_descriptions' );
        foreach ( $boolean_fields as $field ) {
            $sanitized[ $field ] = ! empty( $input[ $field ] );
        }

        // Colors - validate hex format.
        $color_fields = array( 'primary_color', 'accent_color' );
        foreach ( $color_fields as $field ) {
            $color = isset( $input[ $field ] ) ? sanitize_hex_color( $input[ $field ] ) : '';
            $sanitized[ $field ] = ! empty( $color ) ? $color : $defaults[ $field ];
        }

        return $sanitized;
    }

    /**
     * Render the settings page.
     */
    public function render_page() {
        $settings   = $this->get_all();
        $registry   = LayoutRegistry::instance();
        $layouts    = $registry->get_all();
        $categories = ProductQuery::get_categories( true );
        ?>
        <div class="wrap urst-admin-wrap">
            <!-- Header -->
            <div class="urst-header-bar">
                <div>
                    <h1>
                        <span class="dashicons dashicons-admin-appearance"></span>
                        <?php esc_html_e( 'Appearance', 'unstoppable-restaurant' ); ?>
                    </h1>
                    <p class="urst-page-description"><?php esc_html_e( 'Customize your restaurant menu layout and display settings.', 'unstoppable-restaurant' ); ?></p>
                </div>
            </div>

            <div class="urst-admin-grid">
                <!-- Main Content -->
                <div class="urst-main-content">
                    
                    <!-- Settings Form -->
                    <form method="post" action="options.php">
                        <?php settings_fields( 'urst_settings_group' ); ?>
                        
                        <!-- Layout Selection Card -->
                        <div class="urst-card">
                            <div class="urst-card-header">
                                <h2><?php esc_html_e( 'Menu Layout', 'unstoppable-restaurant' ); ?></h2>
                            </div>
                            <div class="urst-card-body">
                                <div class="urst-layout-grid">
                                    <?php foreach ( $layouts as $layout_id => $layout ) : ?>
                                        <label class="urst-layout-card <?php echo $settings['layout'] === $layout_id ? 'is-selected' : ''; ?>">
                                            <input type="radio"
                                                   name="<?php echo esc_attr( $this->option_name ); ?>[layout]"
                                                   value="<?php echo esc_attr( $layout_id ); ?>"
                                                   <?php checked( $settings['layout'], $layout_id ); ?>>
                                            <div class="urst-layout-icon">
                                                <span class="dashicons <?php echo esc_attr( $layout['icon'] ); ?>"></span>
                                            </div>
                                            <span class="urst-layout-name"><?php echo esc_html( $layout['label'] ); ?></span>
                                            <p class="urst-layout-desc"><?php echo esc_html( $layout['description'] ); ?></p>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Display Settings Card -->
                        <div class="urst-card">
                            <div class="urst-card-header">
                                <h2><?php esc_html_e( 'Display Settings', 'unstoppable-restaurant' ); ?></h2>
                            </div>
                            <div class="urst-card-body">
                                
                                <!-- Category Selection -->
                                <?php if ( ! empty( $categories ) ) : ?>
                                <div class="urst-section">
                                    <h3 class="urst-section-title"><?php esc_html_e( 'Categories to Display', 'unstoppable-restaurant' ); ?></h3>
                                    <p class="urst-help-text" style="margin-bottom: 12px;">
                                        <span class="dashicons dashicons-info-outline"></span>
                                        <?php esc_html_e( 'Leave all unchecked to show all categories.', 'unstoppable-restaurant' ); ?>
                                    </p>
                                    <div class="urst-category-grid">
                                        <?php foreach ( $categories as $category ) : ?>
                                            <label class="urst-category-pill">
                                                <input type="checkbox"
                                                       name="<?php echo esc_attr( $this->option_name ); ?>[categories][]"
                                                       value="<?php echo esc_attr( $category['id'] ); ?>"
                                                       <?php checked( in_array( $category['id'], (array) $settings['categories'], true ) ); ?>>
                                                <span class="urst-category-name"><?php echo esc_html( $category['name'] ); ?></span>
                                                <span class="urst-category-count"><?php echo esc_html( $category['count'] ); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Display Options -->
                                <div class="urst-section">
                                    <h3 class="urst-section-title"><?php esc_html_e( 'Display Options', 'unstoppable-restaurant' ); ?></h3>
                                    <div class="urst-toggle-list">
                                        <div class="urst-toggle-item">
                                            <span class="urst-toggle-label"><?php esc_html_e( 'Show product images', 'unstoppable-restaurant' ); ?></span>
                                            <label class="urst-toggle">
                                                <input type="checkbox"
                                                       name="<?php echo esc_attr( $this->option_name ); ?>[show_images]"
                                                       value="1"
                                                       <?php checked( $settings['show_images'] ); ?>>
                                                <span class="urst-toggle-slider"></span>
                                            </label>
                                        </div>
                                        <div class="urst-toggle-item">
                                            <span class="urst-toggle-label"><?php esc_html_e( 'Show prices', 'unstoppable-restaurant' ); ?></span>
                                            <label class="urst-toggle">
                                                <input type="checkbox"
                                                       name="<?php echo esc_attr( $this->option_name ); ?>[show_prices]"
                                                       value="1"
                                                       <?php checked( $settings['show_prices'] ); ?>>
                                                <span class="urst-toggle-slider"></span>
                                            </label>
                                        </div>
                                        <div class="urst-toggle-item">
                                            <span class="urst-toggle-label"><?php esc_html_e( 'Show short descriptions', 'unstoppable-restaurant' ); ?></span>
                                            <label class="urst-toggle">
                                                <input type="checkbox"
                                                       name="<?php echo esc_attr( $this->option_name ); ?>[show_descriptions]"
                                                       value="1"
                                                       <?php checked( $settings['show_descriptions'] ); ?>>
                                                <span class="urst-toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Colors -->
                                <div class="urst-section">
                                    <h3 class="urst-section-title"><?php esc_html_e( 'Brand Colors', 'unstoppable-restaurant' ); ?></h3>
                                    <div class="urst-color-row">
                                        <div class="urst-color-field">
                                            <label for="urst-primary-color"><?php esc_html_e( 'Primary', 'unstoppable-restaurant' ); ?></label>
                                            <div class="urst-color-input">
                                                <input type="color"
                                                       id="urst-primary-color"
                                                       name="<?php echo esc_attr( $this->option_name ); ?>[primary_color]"
                                                       value="<?php echo esc_attr( $settings['primary_color'] ); ?>">
                                            </div>
                                        </div>
                                        <div class="urst-color-field">
                                            <label for="urst-accent-color"><?php esc_html_e( 'Accent', 'unstoppable-restaurant' ); ?></label>
                                            <div class="urst-color-input">
                                                <input type="color"
                                                       id="urst-accent-color"
                                                       name="<?php echo esc_attr( $this->option_name ); ?>[accent_color]"
                                                       value="<?php echo esc_attr( $settings['accent_color'] ); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div class="urst-card-footer">
                                <div class="urst-submit-row">
                                    <?php submit_button( __( 'Save Settings', 'unstoppable-restaurant' ), 'primary', 'submit', false ); ?>
                                    <span class="urst-save-status">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php esc_html_e( 'Saved', 'unstoppable-restaurant' ); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>

                <!-- Sidebar -->
                <div class="urst-sidebar">
                    
                    <!-- Quick Start Card -->
                    <div class="urst-card urst-card--quickstart">
                        <div class="urst-card-header">
                            <h2><?php esc_html_e( 'Quick Start', 'unstoppable-restaurant' ); ?></h2>
                        </div>
                        <div class="urst-card-body">
                            <p class="urst-help-text"><?php esc_html_e( 'Add your menu to any page using:', 'unstoppable-restaurant' ); ?></p>
                            <div class="urst-shortcode-box">
                                <code class="urst-shortcode">[unstoppable_menu]</code>
                            </div>
                            <p class="urst-help-text">
                                <span class="dashicons dashicons-editor-table"></span>
                                <?php esc_html_e( 'Or use the "Unstoppable Menu" block in the block editor.', 'unstoppable-restaurant' ); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Help Card -->
                    <div class="urst-info-card">
                        <h3>
                            <span class="dashicons dashicons-sos"></span>
                            <?php esc_html_e( 'Need Help?', 'unstoppable-restaurant' ); ?>
                        </h3>
                        <p><?php esc_html_e( 'Check out our documentation for setup guides, tutorials, and troubleshooting tips.', 'unstoppable-restaurant' ); ?></p>
                        <a href="https://unstoppablesolutions.app/docs" target="_blank" class="button">
                            <?php esc_html_e( 'View Documentation', 'unstoppable-restaurant' ); ?>
                        </a>
                    </div>

                    <?php if ( ! defined( 'URST_PRO_VERSION' ) ) : ?>
                    <!-- Pro Upsell Card -->
                    <div class="urst-info-card urst-card--pro">
                        <h3>
                            <span class="dashicons dashicons-star-filled"></span>
                            <?php esc_html_e( 'Upgrade to Pro', 'unstoppable-restaurant' ); ?>
                        </h3>
                        <p><?php esc_html_e( 'Unlock powerful features for your restaurant:', 'unstoppable-restaurant' ); ?></p>
                        <ul>
                            <li><?php esc_html_e( 'Auto-print to kitchen', 'unstoppable-restaurant' ); ?></li>
                            <li><?php esc_html_e( 'Multi-location support', 'unstoppable-restaurant' ); ?></li>
                            <li><?php esc_html_e( 'Advanced scheduling', 'unstoppable-restaurant' ); ?></li>
                            <li><?php esc_html_e( 'Priority support', 'unstoppable-restaurant' ); ?></li>
                        </ul>
                        <a href="https://unstoppablesolutions.app/plugins/unstoppable-restaurant-pro" target="_blank" class="button">
                            <?php esc_html_e( 'Get Pro Version', 'unstoppable-restaurant' ); ?>
                        </a>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <script>
        (function($) {
            // Update layout card selection
            $('.urst-layout-card input[type="radio"]').on('change', function() {
                $('.urst-layout-card').removeClass('is-selected');
                $(this).closest('.urst-layout-card').addClass('is-selected');
            });
        })(jQuery);
        </script>
        <?php
    }
}
