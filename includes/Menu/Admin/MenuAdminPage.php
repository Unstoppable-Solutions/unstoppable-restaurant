<?php
/**
 * Menu Admin Page.
 *
 * Admin interface for managing menu definitions.
 * Scope v1: List, Add, Edit (name, slug, layout, categories)
 *
 * NO filters. NO scheduling. NO visibility. NO preview.
 *
 * @package Unstoppable_Restaurant
 * @since 2.8.0
 */

namespace Unstoppable\Restaurant\Menu\Admin;

use Unstoppable\Restaurant\Menu\MenuRepository;
use Unstoppable\Restaurant\Menu\MenuDefinition;
use Unstoppable\Restaurant\Core\LayoutRegistry;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MenuAdminPage class.
 *
 * @since 2.8.0
 */
class MenuAdminPage {

    /**
     * Singleton instance.
     *
     * @var MenuAdminPage|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return MenuAdminPage
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize admin page.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu_page' ), 20 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    /**
     * Register the menu page.
     */
    public function register_menu_page() {
        add_submenu_page(
            'unstoppable-restaurant',
            __( 'Menus', 'unstoppable-restaurant' ),
            __( 'Menus', 'unstoppable-restaurant' ),
            'manage_woocommerce',
            'urst-menus',
            array( $this, 'render_page' )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'restaurant_page_urst-menus' !== $hook ) {
            return;
        }

        // jQuery UI Sortable for category ordering.
        wp_enqueue_script( 'jquery-ui-sortable' );

        // Inline styles.
        wp_add_inline_style( 'wp-admin', $this->get_inline_styles() );

        // Inline script.
        wp_add_inline_script( 'jquery-ui-sortable', $this->get_inline_script() );
    }

    /**
     * Handle form actions.
     */
    public function handle_actions() {
        // Only on our page.
        if ( ! isset( $_GET['page'] ) || 'urst-menus' !== $_GET['page'] ) {
            return;
        }

        $repository = MenuRepository::instance();

        // Handle save.
        if ( isset( $_POST['urst_save_menu'] ) ) {
            $this->handle_save( $repository );
        }

        // Handle delete.
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['menu_id'] ) ) {
            $this->handle_delete( $repository );
        }
    }

    /**
     * Handle menu save.
     *
     * @param MenuRepository $repository Repository instance.
     */
    private function handle_save( MenuRepository $repository ) {
        // Verify nonce.
        if ( ! isset( $_POST['urst_menu_nonce'] ) || ! wp_verify_nonce( $_POST['urst_menu_nonce'], 'urst_save_menu' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'unstoppable-restaurant' ) );
        }

        // Check capability.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'unstoppable-restaurant' ) );
        }

        // Sanitize input.
        $menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;
        $name = isset( $_POST['menu_name'] ) ? sanitize_text_field( $_POST['menu_name'] ) : '';
        $slug = isset( $_POST['menu_slug'] ) ? sanitize_title( $_POST['menu_slug'] ) : '';
        $layout = isset( $_POST['menu_layout'] ) ? sanitize_key( $_POST['menu_layout'] ) : 'classic';
        $category_ids = isset( $_POST['menu_categories'] ) ? array_map( 'absint', (array) $_POST['menu_categories'] ) : array();

        // Phase 7.3: Process sections data.
        $sections = $this->sanitize_sections_input();

        // Phase 7.5: Process rules data.
        $rules = $this->sanitize_rules_input();
        $priority = isset( $_POST['menu_priority'] ) ? absint( $_POST['menu_priority'] ) : 10;
        if ( $priority < 1 ) {
            $priority = 10;
        }
        if ( $priority > 100 ) {
            $priority = 100;
        }

        // Validate.
        if ( empty( $name ) ) {
            add_settings_error( 'urst_menus', 'empty_name', __( 'Menu name is required.', 'unstoppable-restaurant' ), 'error' );
            return;
        }

        // Build menu data using NEW schema (v2.11.0+).
        $menu_data = array(
            'name'         => $name,
            'slug'         => $slug,
            'category_ids' => $category_ids,
            'status'       => 'publish',
            'settings'     => array(
                'layout'   => $layout,
                'sections' => $sections, // Phase 7.3
                'rules'    => $rules,    // Phase 7.5
                'priority' => $priority, // Phase 7.5
            ),
        );

        // Update or create.
        if ( $menu_id > 0 ) {
            // Updating existing.
            $existing = $repository->get_by_id( $menu_id );
            if ( $existing ) {
                $menu_data['id'] = $menu_id;
                // Preserve existing settings, merge new.
                $existing_settings = $existing->get_settings();
                $menu_data['settings'] = array_merge(
                    $existing_settings,
                    $menu_data['settings']
                );
            }
            $menu = MenuDefinition::from_array( $menu_data );
            $repository->save( $menu );
            add_settings_error( 'urst_menus', 'updated', __( 'Menu updated.', 'unstoppable-restaurant' ), 'success' );
        } else {
            // Creating new.
            $repository->create( $menu_data );
            add_settings_error( 'urst_menus', 'created', __( 'Menu created.', 'unstoppable-restaurant' ), 'success' );
        }

        // Clear cache.
        $repository->clear_cache();

        // Redirect to list.
        wp_safe_redirect( admin_url( 'admin.php?page=urst-menus&saved=1' ) );
        exit;
    }

    /**
     * Phase 7.5: Sanitize rules input from POST data.
     *
     * @return array Sanitized rules array.
     */
    private function sanitize_rules_input() {
        $rules = array(
            'enabled'    => isset( $_POST['menu_rules_enabled'] ) && '1' === $_POST['menu_rules_enabled'],
            'days'       => array(),
            'time_start' => '',
            'time_end'   => '',
            'date_start' => '',
            'date_end'   => '',
        );

        // Days of week (0-6).
        if ( isset( $_POST['menu_rules_days'] ) && is_array( $_POST['menu_rules_days'] ) ) {
            $rules['days'] = array_filter( array_map( function( $day ) {
                $day = absint( $day );
                return ( $day >= 0 && $day <= 6 ) ? $day : null;
            }, $_POST['menu_rules_days'] ), function( $v ) {
                return null !== $v;
            } );
            $rules['days'] = array_values( $rules['days'] ); // Re-index.
        }

        // Time window (HH:MM format).
        if ( isset( $_POST['menu_rules_time_start'] ) && preg_match( '/^\d{2}:\d{2}$/', $_POST['menu_rules_time_start'] ) ) {
            $rules['time_start'] = sanitize_text_field( $_POST['menu_rules_time_start'] );
        }
        if ( isset( $_POST['menu_rules_time_end'] ) && preg_match( '/^\d{2}:\d{2}$/', $_POST['menu_rules_time_end'] ) ) {
            $rules['time_end'] = sanitize_text_field( $_POST['menu_rules_time_end'] );
        }

        // Date range (YYYY-MM-DD format).
        if ( isset( $_POST['menu_rules_date_start'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['menu_rules_date_start'] ) ) {
            $rules['date_start'] = sanitize_text_field( $_POST['menu_rules_date_start'] );
        }
        if ( isset( $_POST['menu_rules_date_end'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['menu_rules_date_end'] ) ) {
            $rules['date_end'] = sanitize_text_field( $_POST['menu_rules_date_end'] );
        }

        return $rules;
    }

    /**
     * Phase 7.3: Sanitize sections input from POST data.
     *
     * @return array Sanitized sections array.
     */
    private function sanitize_sections_input() {
        if ( ! isset( $_POST['menu_sections'] ) || ! is_array( $_POST['menu_sections'] ) ) {
            return array();
        }

        $sections = array();
        $order = 1;

        foreach ( $_POST['menu_sections'] as $section_data ) {
            // Skip empty sections.
            $label = isset( $section_data['label'] ) ? sanitize_text_field( $section_data['label'] ) : '';
            if ( empty( $label ) ) {
                continue;
            }

            // Generate ID from label or use existing.
            $id = isset( $section_data['id'] ) && ! empty( $section_data['id'] ) 
                ? sanitize_key( $section_data['id'] ) 
                : sanitize_key( $label );

            // Sanitize product IDs.
            $product_ids = array();
            if ( isset( $section_data['product_ids'] ) && is_array( $section_data['product_ids'] ) ) {
                $product_ids = array_filter( array_map( 'absint', $section_data['product_ids'] ) );
            }

            $sections[] = array(
                'id'          => $id,
                'label'       => $label,
                'description' => isset( $section_data['description'] ) ? sanitize_textarea_field( $section_data['description'] ) : '',
                'product_ids' => $product_ids,
                'order'       => $order++,
                'is_active'   => isset( $section_data['is_active'] ) ? (bool) $section_data['is_active'] : true,
            );
        }

        return $sections;
    }

    /**
     * Handle menu delete.
     *
     * @param MenuRepository $repository Repository instance.
     */
    private function handle_delete( MenuRepository $repository ) {
        $menu_id = isset( $_GET['menu_id'] ) ? absint( $_GET['menu_id'] ) : 0;

        if ( $menu_id <= 0 ) {
            return;
        }

        // Verify nonce.
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'urst_delete_menu_' . $menu_id ) ) {
            wp_die( esc_html__( 'Security check failed.', 'unstoppable-restaurant' ) );
        }

        // Check capability.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'unstoppable-restaurant' ) );
        }

        // Don't allow deleting default menu.
        $menu = $repository->get_by_id( $menu_id );
        if ( $menu && 'default-menu' === $menu->get_slug() ) {
            add_settings_error( 'urst_menus', 'cannot_delete', __( 'Cannot delete the default menu.', 'unstoppable-restaurant' ), 'error' );
            return;
        }

        $repository->delete( $menu_id );
        $repository->clear_cache();

        wp_safe_redirect( admin_url( 'admin.php?page=urst-menus&deleted=1' ) );
        exit;
    }

    /**
     * Render the admin page.
     */
    public function render_page() {
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
        $menu_id = isset( $_GET['menu_id'] ) ? absint( $_GET['menu_id'] ) : 0;

        ?>
        <div class="wrap urst-menus-wrap">
            <?php
            // Show notices.
            settings_errors( 'urst_menus' );

            if ( isset( $_GET['saved'] ) ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Menu saved successfully.', 'unstoppable-restaurant' ) . '</p></div>';
            }
            if ( isset( $_GET['deleted'] ) ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Menu deleted.', 'unstoppable-restaurant' ) . '</p></div>';
            }

            switch ( $action ) {
                case 'add':
                    $this->render_edit_form( null );
                    break;

                case 'edit':
                    $menu = MenuRepository::instance()->get_by_id( $menu_id );
                    if ( $menu ) {
                        $this->render_edit_form( $menu );
                    } else {
                        echo '<div class="notice notice-error"><p>' . esc_html__( 'Menu not found.', 'unstoppable-restaurant' ) . '</p></div>';
                        $this->render_list();
                    }
                    break;

                case 'list':
                default:
                    $this->render_list();
                    break;
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render menu list.
     */
    private function render_list() {
        $menus = MenuRepository::instance()->get_all();
        $layouts = LayoutRegistry::instance()->get_all();

        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e( 'Menus', 'unstoppable-restaurant' ); ?></h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=urst-menus&action=add' ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Add New', 'unstoppable-restaurant' ); ?>
        </a>
        <hr class="wp-header-end">

        <table class="wp-list-table widefat fixed striped urst-menus-table">
            <thead>
                <tr>
                    <th scope="col" class="column-name"><?php esc_html_e( 'Name', 'unstoppable-restaurant' ); ?></th>
                    <th scope="col" class="column-slug"><?php esc_html_e( 'Slug', 'unstoppable-restaurant' ); ?></th>
                    <th scope="col" class="column-layout"><?php esc_html_e( 'Layout', 'unstoppable-restaurant' ); ?></th>
                    <th scope="col" class="column-categories"><?php esc_html_e( 'Categories', 'unstoppable-restaurant' ); ?></th>
                    <th scope="col" class="column-shortcode"><?php esc_html_e( 'Shortcode', 'unstoppable-restaurant' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $menus ) ) : ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e( 'No menus found.', 'unstoppable-restaurant' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $menus as $menu ) : 
                        $layout_key = $menu->get_layout();
                        $layout_name = $layout_key; // Default to key itself.
                        if ( isset( $layouts[ $layout_key ] ) && isset( $layouts[ $layout_key ]['name'] ) ) {
                            $layout_name = $layouts[ $layout_key ]['name'];
                        }
                        $cat_count = count( $menu->get_category_ids() );
                        $cat_label = $cat_count > 0 ? sprintf( _n( '%d category', '%d categories', $cat_count, 'unstoppable-restaurant' ), $cat_count ) : __( 'All', 'unstoppable-restaurant' );
                        $is_default = 'default-menu' === $menu->get_slug();
                    ?>
                        <tr>
                            <td class="column-name">
                                <strong>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=urst-menus&action=edit&menu_id=' . $menu->get_id() ) ); ?>">
                                        <?php echo esc_html( $menu->get_name() ); ?>
                                    </a>
                                    <?php if ( $is_default ) : ?>
                                        <span class="urst-default-badge"><?php esc_html_e( 'Default', 'unstoppable-restaurant' ); ?></span>
                                    <?php endif; ?>
                                </strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=urst-menus&action=edit&menu_id=' . $menu->get_id() ) ); ?>">
                                            <?php esc_html_e( 'Edit', 'unstoppable-restaurant' ); ?>
                                        </a>
                                    </span>
                                    <?php if ( ! $is_default ) : ?>
                                        <span class="delete"> | 
                                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=urst-menus&action=delete&menu_id=' . $menu->get_id() ), 'urst_delete_menu_' . $menu->get_id() ) ); ?>" class="urst-delete-link" onclick="return confirm('<?php esc_attr_e( 'Delete this menu?', 'unstoppable-restaurant' ); ?>');">
                                                <?php esc_html_e( 'Delete', 'unstoppable-restaurant' ); ?>
                                            </a>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="column-slug">
                                <code><?php echo esc_html( $menu->get_slug() ); ?></code>
                            </td>
                            <td class="column-layout">
                                <?php echo esc_html( $layout_name ); ?>
                            </td>
                            <td class="column-categories">
                                <?php echo esc_html( $cat_label ); ?>
                            </td>
                            <td class="column-shortcode">
                                <code>[unstoppable_menu menu="<?php echo esc_attr( $menu->get_slug() ); ?>"]</code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render edit form.
     *
     * @param MenuDefinition|null $menu Menu to edit, or null for new.
     */
    private function render_edit_form( $menu ) {
        $is_new = null === $menu;
        $page_title = $is_new ? __( 'Add New Menu', 'unstoppable-restaurant' ) : __( 'Edit Menu', 'unstoppable-restaurant' );
        $is_default = $menu && 'default-menu' === $menu->get_slug();

        // Get available layouts.
        $layouts = LayoutRegistry::instance()->get_all();

        // Get available categories.
        $categories = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ) );

        // Get selected categories (preserving order).
        $selected_categories = $menu ? $menu->get_category_ids() : array();

        ?>
        <h1>
            <?php echo esc_html( $page_title ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=urst-menus' ) ); ?>" class="page-title-action">
                <?php esc_html_e( '← Back to List', 'unstoppable-restaurant' ); ?>
            </a>
        </h1>
        <hr class="wp-header-end">

        <form method="post" class="urst-menu-form">
            <?php wp_nonce_field( 'urst_save_menu', 'urst_menu_nonce' ); ?>
            <input type="hidden" name="menu_id" value="<?php echo esc_attr( $menu ? $menu->get_id() : '' ); ?>">

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="menu_name"><?php esc_html_e( 'Menu Name', 'unstoppable-restaurant' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="menu_name" id="menu_name" class="regular-text" required
                            value="<?php echo esc_attr( $menu ? $menu->get_name() : '' ); ?>">
                        <p class="description"><?php esc_html_e( 'A descriptive name for this menu.', 'unstoppable-restaurant' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="menu_slug"><?php esc_html_e( 'Slug', 'unstoppable-restaurant' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="menu_slug" id="menu_slug" class="regular-text"
                            value="<?php echo esc_attr( $menu ? $menu->get_slug() : '' ); ?>"
                            <?php echo $is_default ? 'readonly' : ''; ?>>
                        <p class="description">
                            <?php esc_html_e( 'URL-friendly identifier. Leave blank to auto-generate.', 'unstoppable-restaurant' ); ?>
                            <?php if ( $is_default ) : ?>
                                <br><em><?php esc_html_e( 'The default menu slug cannot be changed.', 'unstoppable-restaurant' ); ?></em>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="menu_layout"><?php esc_html_e( 'Layout', 'unstoppable-restaurant' ); ?></label>
                    </th>
                    <td>
                        <div class="urst-layout-selector">
                            <?php foreach ( $layouts as $layout_id => $layout ) : 
                                $checked = $menu && $menu->get_layout() === $layout_id;
                                if ( ! $menu && 'classic' === $layout_id ) {
                                    $checked = true;
                                }
                                $layout_name = isset( $layout['label'] ) ? $layout['label'] : ( isset( $layout['name'] ) ? $layout['name'] : $layout_id );
                            ?>
                                <label class="urst-layout-option <?php echo $checked ? 'selected' : ''; ?>">
                                    <input type="radio" name="menu_layout" value="<?php echo esc_attr( $layout_id ); ?>" <?php checked( $checked ); ?>>
                                    <span class="urst-layout-name"><?php echo esc_html( $layout_name ); ?></span>
                                    <?php if ( ! empty( $layout['description'] ) ) : ?>
                                        <span class="urst-layout-desc"><?php echo esc_html( $layout['description'] ); ?></span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Categories', 'unstoppable-restaurant' ); ?></label>
                    </th>
                    <td>
                        <p class="description" style="margin-bottom: 10px;">
                            <?php esc_html_e( 'Select categories to include. Drag to reorder. Leave empty for all categories.', 'unstoppable-restaurant' ); ?>
                        </p>
                        
                        <?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
                            <div class="urst-category-picker">
                                <div class="urst-category-available">
                                    <h4><?php esc_html_e( 'Available', 'unstoppable-restaurant' ); ?></h4>
                                    <ul class="urst-category-list" id="urst-available-categories">
                                        <?php foreach ( $categories as $cat ) : 
                                            if ( in_array( $cat->term_id, $selected_categories, true ) ) continue;
                                        ?>
                                            <li data-id="<?php echo esc_attr( $cat->term_id ); ?>">
                                                <label>
                                                    <input type="checkbox" value="<?php echo esc_attr( $cat->term_id ); ?>">
                                                    <?php echo esc_html( $cat->name ); ?>
                                                    <span class="count">(<?php echo esc_html( $cat->count ); ?>)</span>
                                                </label>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>

                                <div class="urst-category-buttons">
                                    <button type="button" class="button" id="urst-add-categories">→</button>
                                    <button type="button" class="button" id="urst-remove-categories">←</button>
                                </div>

                                <div class="urst-category-selected">
                                    <h4><?php esc_html_e( 'Selected (drag to reorder)', 'unstoppable-restaurant' ); ?></h4>
                                    <ul class="urst-category-list urst-sortable" id="urst-selected-categories">
                                        <?php foreach ( $selected_categories as $cat_id ) : 
                                            $cat = get_term( $cat_id, 'product_cat' );
                                            if ( ! $cat || is_wp_error( $cat ) ) continue;
                                        ?>
                                            <li data-id="<?php echo esc_attr( $cat->term_id ); ?>">
                                                <input type="hidden" name="menu_categories[]" value="<?php echo esc_attr( $cat->term_id ); ?>">
                                                <span class="dashicons dashicons-menu"></span>
                                                <?php echo esc_html( $cat->name ); ?>
                                                <span class="count">(<?php echo esc_html( $cat->count ); ?>)</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php else : ?>
                            <p><?php esc_html_e( 'No product categories found.', 'unstoppable-restaurant' ); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>

                <?php // Phase 7.3: Sections UI ?>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Menu Sections', 'unstoppable-restaurant' ); ?></label>
                    </th>
                    <td>
                        <p class="description" style="margin-bottom: 10px;">
                            <?php esc_html_e( 'Optional: Group products into custom sections. If no sections are defined, products will be grouped by category.', 'unstoppable-restaurant' ); ?>
                        </p>
                        
                        <?php 
                        $existing_sections = $menu ? $menu->get_sections() : array();
                        $all_products = $this->get_all_menu_products( $selected_categories );
                        ?>
                        
                        <div id="urst-sections-container">
                            <?php if ( ! empty( $existing_sections ) ) : ?>
                                <?php foreach ( $existing_sections as $index => $section ) : ?>
                                    <?php $this->render_section_row( $index, $section, $all_products ); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <p style="margin-top: 12px;">
                            <button type="button" class="button" id="urst-add-section">
                                <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
                                <?php esc_html_e( 'Add Section', 'unstoppable-restaurant' ); ?>
                            </button>
                        </p>

                        <?php // Hidden template for new sections ?>
                        <script type="text/template" id="urst-section-template">
                            <?php $this->render_section_row( '{{INDEX}}', array(), $all_products ); ?>
                        </script>
                    </td>
                </tr>
            </table>

            <?php // Phase 7.5: Rules Admin UI ?>
            <h2 class="urst-section-title">
                <span class="dashicons dashicons-clock"></span>
                <?php esc_html_e( 'Scheduling Rules', 'unstoppable-restaurant' ); ?>
            </h2>
            <p class="description" style="margin-bottom: 16px;">
                <?php esc_html_e( 'Control when this menu is active. Leave all options empty for an always-active menu.', 'unstoppable-restaurant' ); ?>
            </p>
            
            <?php 
            $rules = $menu ? $menu->get_setting( 'rules', array() ) : array();
            $rules_enabled = isset( $rules['enabled'] ) ? (bool) $rules['enabled'] : false;
            $priority = $menu ? absint( $menu->get_setting( 'priority', 10 ) ) : 10;
            $days = isset( $rules['days'] ) ? (array) $rules['days'] : array();
            $time_start = isset( $rules['time_start'] ) ? $rules['time_start'] : '';
            $time_end = isset( $rules['time_end'] ) ? $rules['time_end'] : '';
            $date_start = isset( $rules['date_start'] ) ? $rules['date_start'] : '';
            $date_end = isset( $rules['date_end'] ) ? $rules['date_end'] : '';
            
            $day_names = array(
                0 => __( 'Sunday', 'unstoppable-restaurant' ),
                1 => __( 'Monday', 'unstoppable-restaurant' ),
                2 => __( 'Tuesday', 'unstoppable-restaurant' ),
                3 => __( 'Wednesday', 'unstoppable-restaurant' ),
                4 => __( 'Thursday', 'unstoppable-restaurant' ),
                5 => __( 'Friday', 'unstoppable-restaurant' ),
                6 => __( 'Saturday', 'unstoppable-restaurant' ),
            );
            ?>
            
            <table class="form-table urst-rules-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="menu_rules_enabled"><?php esc_html_e( 'Enable Scheduling', 'unstoppable-restaurant' ); ?></label>
                    </th>
                    <td>
                        <div class="urst-toggle-wrapper">
                            <label class="urst-toggle" for="menu_rules_enabled">
                                <input type="checkbox" name="menu_rules_enabled" id="menu_rules_enabled" value="1" <?php checked( $rules_enabled ); ?>>
                                <span class="urst-toggle-slider"></span>
                            </label>
                            <label class="urst-toggle-text" for="menu_rules_enabled"><?php esc_html_e( 'Use scheduling rules for this menu', 'unstoppable-restaurant' ); ?></label>
                        </div>
                        <p class="description"><?php esc_html_e( 'When enabled, this menu will only be active when ALL rules below are satisfied.', 'unstoppable-restaurant' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="menu_priority"><?php esc_html_e( 'Priority', 'unstoppable-restaurant' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="menu_priority" id="menu_priority" class="small-text" 
                               value="<?php echo esc_attr( $priority ); ?>" min="1" max="100">
                        <p class="description"><?php esc_html_e( 'Lower number = higher priority. When multiple menus are active, the highest priority menu is shown first. Default: 10', 'unstoppable-restaurant' ); ?></p>
                    </td>
                </tr>

                <tr class="urst-rules-row">
                    <th scope="row">
                        <label><?php esc_html_e( 'Days of Week', 'unstoppable-restaurant' ); ?></label>
                    </th>
                    <td>
                        <div class="urst-days-picker">
                            <?php foreach ( $day_names as $day_num => $day_name ) : ?>
                                <label class="urst-day-option <?php echo in_array( $day_num, $days, false ) ? 'selected' : ''; ?>">
                                    <input type="checkbox" name="menu_rules_days[]" value="<?php echo esc_attr( $day_num ); ?>" <?php checked( in_array( $day_num, $days, false ) ); ?>>
                                    <span class="urst-day-abbr"><?php echo esc_html( mb_substr( $day_name, 0, 3 ) ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="description"><?php esc_html_e( 'Select days when this menu should be active. Leave all unchecked for every day.', 'unstoppable-restaurant' ); ?></p>
                    </td>
                </tr>

                <tr class="urst-rules-row">
                    <th scope="row">
                        <label><?php esc_html_e( 'Time Window', 'unstoppable-restaurant' ); ?></label>
                    </th>
                    <td>
                        <div class="urst-time-picker">
                            <input type="time" name="menu_rules_time_start" id="menu_rules_time_start" 
                                   value="<?php echo esc_attr( $time_start ); ?>" class="urst-time-input">
                            <span class="urst-time-separator"><?php esc_html_e( 'to', 'unstoppable-restaurant' ); ?></span>
                            <input type="time" name="menu_rules_time_end" id="menu_rules_time_end" 
                                   value="<?php echo esc_attr( $time_end ); ?>" class="urst-time-input">
                        </div>
                        <p class="description"><?php esc_html_e( 'Active only during this time range. Leave empty for all day. Example: 11:00 to 15:00 for lunch menu.', 'unstoppable-restaurant' ); ?></p>
                        
                        <?php // Quick presets ?>
                        <div class="urst-time-presets">
                            <span class="urst-presets-label"><?php esc_html_e( 'Presets:', 'unstoppable-restaurant' ); ?></span>
                            <button type="button" class="button-link urst-time-preset" data-start="06:00" data-end="11:00"><?php esc_html_e( 'Breakfast', 'unstoppable-restaurant' ); ?></button>
                            <button type="button" class="button-link urst-time-preset" data-start="11:00" data-end="15:00"><?php esc_html_e( 'Lunch', 'unstoppable-restaurant' ); ?></button>
                            <button type="button" class="button-link urst-time-preset" data-start="17:00" data-end="22:00"><?php esc_html_e( 'Dinner', 'unstoppable-restaurant' ); ?></button>
                            <button type="button" class="button-link urst-time-preset" data-start="22:00" data-end="02:00"><?php esc_html_e( 'Late Night', 'unstoppable-restaurant' ); ?></button>
                            <button type="button" class="button-link urst-time-preset" data-start="" data-end=""><?php esc_html_e( 'All Day', 'unstoppable-restaurant' ); ?></button>
                        </div>
                    </td>
                </tr>

                <tr class="urst-rules-row">
                    <th scope="row">
                        <label><?php esc_html_e( 'Date Range', 'unstoppable-restaurant' ); ?></label>
                    </th>
                    <td>
                        <div class="urst-date-picker">
                            <input type="date" name="menu_rules_date_start" id="menu_rules_date_start" 
                                   value="<?php echo esc_attr( $date_start ); ?>" class="urst-date-input">
                            <span class="urst-date-separator"><?php esc_html_e( 'to', 'unstoppable-restaurant' ); ?></span>
                            <input type="date" name="menu_rules_date_end" id="menu_rules_date_end" 
                                   value="<?php echo esc_attr( $date_end ); ?>" class="urst-date-input">
                        </div>
                        <p class="description"><?php esc_html_e( 'Active only during this date range. Leave empty for no date restrictions. Useful for seasonal menus.', 'unstoppable-restaurant' ); ?></p>
                    </td>
                </tr>
                
                <?php if ( ! $is_new && $menu ) : ?>
                <tr class="urst-rules-row">
                    <th scope="row">
                        <label><?php esc_html_e( 'Preview Schedule', 'unstoppable-restaurant' ); ?></label>
                    </th>
                    <td>
                        <div class="urst-preview-tester">
                            <input type="datetime-local" id="urst-preview-datetime" class="urst-datetime-input">
                            <button type="button" class="button" id="urst-preview-rules">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php esc_html_e( 'Test', 'unstoppable-restaurant' ); ?>
                            </button>
                            <button type="button" class="button-link" id="urst-preview-now">
                                <?php esc_html_e( 'Use current time', 'unstoppable-restaurant' ); ?>
                            </button>
                        </div>
                        <div id="urst-preview-result" class="urst-preview-result" style="display:none;">
                            <span class="urst-preview-status"></span>
                            <span class="urst-preview-reason"></span>
                        </div>
                        <p class="description"><?php esc_html_e( 'Test if this menu would be active at a specific date and time.', 'unstoppable-restaurant' ); ?></p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <p class="submit">
                <button type="submit" name="urst_save_menu" class="button button-primary">
                    <?php echo $is_new ? esc_html__( 'Create Menu', 'unstoppable-restaurant' ) : esc_html__( 'Save Changes', 'unstoppable-restaurant' ); ?>
                </button>
            </p>
        </form>
        <?php
    }

    /**
     * Phase 7.3: Render a single section row.
     *
     * @param int|string $index    Section index.
     * @param array      $section  Section data.
     * @param array      $products Available products.
     */
    private function render_section_row( $index, $section, $products ) {
        $id = isset( $section['id'] ) ? $section['id'] : '';
        $label = isset( $section['label'] ) ? $section['label'] : '';
        $description = isset( $section['description'] ) ? $section['description'] : '';
        $product_ids = isset( $section['product_ids'] ) ? (array) $section['product_ids'] : array();
        $is_active = isset( $section['is_active'] ) ? (bool) $section['is_active'] : true;
        ?>
        <div class="urst-section-row" data-index="<?php echo esc_attr( $index ); ?>">
            <div class="urst-section-header">
                <span class="dashicons dashicons-menu urst-section-handle" title="<?php esc_attr_e( 'Drag to reorder', 'unstoppable-restaurant' ); ?>"></span>
                <input type="text" 
                       name="menu_sections[<?php echo esc_attr( $index ); ?>][label]" 
                       value="<?php echo esc_attr( $label ); ?>"
                       class="urst-section-label"
                       placeholder="<?php esc_attr_e( 'Section name', 'unstoppable-restaurant' ); ?>">
                <input type="hidden" name="menu_sections[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>">
                <input type="hidden" name="menu_sections[<?php echo esc_attr( $index ); ?>][is_active]" value="1">
                <button type="button" class="button-link urst-section-toggle" title="<?php esc_attr_e( 'Expand/Collapse', 'unstoppable-restaurant' ); ?>">
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </button>
                <button type="button" class="button-link urst-section-remove" title="<?php esc_attr_e( 'Remove section', 'unstoppable-restaurant' ); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
            <div class="urst-section-body">
                <div class="urst-section-products">
                    <label><?php esc_html_e( 'Products:', 'unstoppable-restaurant' ); ?></label>
                    <select name="menu_sections[<?php echo esc_attr( $index ); ?>][product_ids][]" 
                            multiple="multiple" 
                            class="urst-product-select"
                            data-placeholder="<?php esc_attr_e( 'Select products...', 'unstoppable-restaurant' ); ?>">
                        <?php foreach ( $products as $product ) : ?>
                            <option value="<?php echo esc_attr( $product['id'] ); ?>" <?php selected( in_array( $product['id'], $product_ids, true ) ); ?>>
                                <?php echo esc_html( $product['name'] ); ?>
                                <?php if ( ! empty( $product['category'] ) ) : ?>
                                    (<?php echo esc_html( $product['category'] ); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Select products to include in this section. Hold Ctrl/Cmd to select multiple.', 'unstoppable-restaurant' ); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Phase 7.3: Get all products available for the menu.
     *
     * @param array $category_ids Selected category IDs (empty = all).
     * @return array Products with id, name, category.
     */
    private function get_all_menu_products( $category_ids = array() ) {
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        // Filter by categories if specified.
        if ( ! empty( $category_ids ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $category_ids,
                ),
            );
        }

        $query = new \WP_Query( $args );
        $products = array();

        foreach ( $query->posts as $post ) {
            $categories = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
            $products[] = array(
                'id'       => $post->ID,
                'name'     => $post->post_title,
                'category' => is_array( $categories ) && ! empty( $categories ) ? $categories[0] : '',
            );
        }

        return $products;
    }

    /**
     * Get inline styles.
     *
     * @return string
     */
    private function get_inline_styles() {
        return '
            .urst-menus-wrap { max-width: 1200px; }
            .urst-menus-table .column-name { width: 25%; }
            .urst-menus-table .column-slug { width: 15%; }
            .urst-menus-table .column-layout { width: 12%; }
            .urst-menus-table .column-categories { width: 12%; }
            .urst-menus-table .column-shortcode { width: 30%; }
            .urst-menus-table code { background: #f0f0f1; padding: 2px 6px; font-size: 12px; }
            .urst-default-badge { background: #2271b1; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 8px; vertical-align: middle; }
            .urst-delete-link { color: #b32d2e !important; }
            
            .urst-menu-form { max-width: 800px; }
            
            .urst-layout-selector { display: flex; flex-wrap: wrap; gap: 12px; }
            .urst-layout-option { display: block; padding: 12px 16px; border: 2px solid #dcdcde; border-radius: 4px; cursor: pointer; min-width: 140px; transition: all 0.15s; }
            .urst-layout-option:hover { border-color: #2271b1; }
            .urst-layout-option.selected { border-color: #2271b1; background: #f0f6fc; }
            .urst-layout-option input { display: none; }
            .urst-layout-name { display: block; font-weight: 600; margin-bottom: 4px; }
            .urst-layout-desc { display: block; font-size: 12px; color: #646970; }
            
            .urst-category-picker { display: flex; gap: 16px; align-items: flex-start; }
            .urst-category-available, .urst-category-selected { flex: 1; background: #f9f9f9; border: 1px solid #dcdcde; border-radius: 4px; padding: 12px; }
            .urst-category-available h4, .urst-category-selected h4 { margin: 0 0 10px 0; font-size: 13px; }
            .urst-category-buttons { display: flex; flex-direction: column; gap: 8px; padding-top: 40px; }
            .urst-category-buttons .button { min-width: 40px; }
            .urst-category-list { list-style: none; margin: 0; padding: 0; max-height: 300px; overflow-y: auto; }
            .urst-category-list li { padding: 6px 8px; margin-bottom: 4px; background: #fff; border: 1px solid #dcdcde; border-radius: 3px; cursor: pointer; display: flex; align-items: center; gap: 6px; }
            .urst-category-list li:hover { border-color: #2271b1; }
            .urst-category-list li label { display: flex; align-items: center; gap: 6px; cursor: pointer; flex: 1; }
            .urst-category-list .count { color: #646970; font-size: 12px; }
            .urst-category-list .dashicons { color: #646970; cursor: grab; }
            .urst-sortable li { cursor: grab; }
            .urst-sortable li.ui-sortable-helper { background: #f0f6fc; border-color: #2271b1; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .urst-sortable li.ui-sortable-placeholder { visibility: visible !important; border: 2px dashed #2271b1; background: transparent; }

            /* Phase 7.3: Sections UI */
            #urst-sections-container { margin-bottom: 8px; }
            .urst-section-row { background: #f9f9f9; border: 1px solid #dcdcde; border-radius: 4px; margin-bottom: 8px; }
            .urst-section-row.ui-sortable-helper { box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
            .urst-section-row.ui-sortable-placeholder { visibility: visible !important; border: 2px dashed #2271b1; background: transparent; }
            .urst-section-header { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-bottom: 1px solid #dcdcde; background: #fff; border-radius: 3px 3px 0 0; }
            .urst-section-header .urst-section-handle { cursor: grab; color: #646970; }
            .urst-section-header .urst-section-label { flex: 1; padding: 4px 8px; border: 1px solid #dcdcde; border-radius: 3px; font-weight: 500; }
            .urst-section-header .button-link { color: #646970; padding: 4px; text-decoration: none; }
            .urst-section-header .button-link:hover { color: #2271b1; }
            .urst-section-header .urst-section-remove:hover { color: #b32d2e; }
            .urst-section-body { padding: 12px; display: block; }
            .urst-section-body.collapsed { display: none; }
            .urst-section-products label { display: block; font-weight: 500; margin-bottom: 6px; }
            .urst-product-select { width: 100%; min-height: 120px; }
            .urst-section-toggle .dashicons { transition: transform 0.2s; }
            .urst-section-row.collapsed .urst-section-toggle .dashicons { transform: rotate(-90deg); }
            .urst-section-row.collapsed .urst-section-header { border-bottom: none; border-radius: 3px; }

            /* Phase 7.5: Rules Admin UI */
            .urst-section-title { display: flex; align-items: center; gap: 8px; margin: 32px 0 8px 0; padding-top: 24px; border-top: 1px solid #dcdcde; font-size: 1.3em; }
            .urst-section-title .dashicons { color: #2271b1; }
            .urst-rules-table { margin-top: 16px; }
            
            /* Toggle switch */
            .urst-toggle { display: flex; align-items: center; gap: 12px; cursor: pointer; }
            .urst-toggle input { display: none; }
            .urst-toggle-slider { position: relative; width: 44px; height: 24px; background: #c3c4c7; border-radius: 12px; transition: background 0.2s; }
            .urst-toggle-slider:before { content: ""; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; background: white; border-radius: 50%; transition: transform 0.2s; }
            .urst-toggle input:checked + .urst-toggle-slider { background: #2271b1; }
            .urst-toggle input:checked + .urst-toggle-slider:before { transform: translateX(20px); }
            .urst-toggle-label { font-weight: 400; }

            /* Days picker */
            .urst-days-picker { display: flex; gap: 6px; flex-wrap: wrap; }
            .urst-day-option { display: flex; align-items: center; justify-content: center; width: 48px; height: 40px; border: 2px solid #dcdcde; border-radius: 6px; cursor: pointer; transition: all 0.15s; background: #fff; }
            .urst-day-option:hover { border-color: #2271b1; }
            .urst-day-option.selected { border-color: #2271b1; background: #f0f6fc; }
            .urst-day-option input { display: none; }
            .urst-day-abbr { font-size: 13px; font-weight: 500; color: #1e1e1e; }
            .urst-day-option.selected .urst-day-abbr { color: #2271b1; }

            /* Time picker */
            .urst-time-picker { display: flex; align-items: center; gap: 10px; }
            .urst-time-input { width: 120px; }
            .urst-time-separator { color: #646970; }
            .urst-time-presets { margin-top: 10px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
            .urst-presets-label { color: #646970; font-size: 12px; }
            .urst-time-preset { font-size: 12px; padding: 4px 8px; background: #f0f0f1; border-radius: 3px; text-decoration: none; }
            .urst-time-preset:hover { background: #e0e0e0; color: #1e1e1e; }

            /* Date picker */
            .urst-date-picker { display: flex; align-items: center; gap: 10px; }
            .urst-date-input { width: 160px; }
            .urst-date-separator { color: #646970; }

            /* Preview tester */
            .urst-preview-tester { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
            .urst-datetime-input { width: 220px; }
            .urst-preview-result { margin-top: 12px; padding: 12px 16px; border-radius: 4px; display: flex; align-items: center; gap: 8px; }
            .urst-preview-result.active { background: #d1fae5; border: 1px solid #10b981; }
            .urst-preview-result.inactive { background: #fef2f2; border: 1px solid #ef4444; }
            .urst-preview-status { font-weight: 600; }
            .urst-preview-reason { color: #646970; }

            /* Disable rules rows when scheduling is off */
            .urst-rules-row { transition: opacity 0.2s; }
            .urst-rules-disabled .urst-rules-row { opacity: 0.5; pointer-events: none; }
        ';
    }

    /**
     * Get inline script.
     *
     * @return string
     */
    private function get_inline_script() {
        return "
            jQuery(document).ready(function($) {
                // Layout selector
                $('.urst-layout-option').on('click', function() {
                    $('.urst-layout-option').removeClass('selected');
                    $(this).addClass('selected');
                    $(this).find('input').prop('checked', true);
                });

                // Category picker - add categories
                $('#urst-add-categories').on('click', function() {
                    $('#urst-available-categories input:checked').each(function() {
                        var li = $(this).closest('li');
                        var id = li.data('id');
                        var name = li.find('label').text().trim();
                        var count = li.find('.count').text();
                        
                        li.remove();
                        
                        $('#urst-selected-categories').append(
                            '<li data-id=\"' + id + '\">' +
                            '<input type=\"hidden\" name=\"menu_categories[]\" value=\"' + id + '\">' +
                            '<span class=\"dashicons dashicons-menu\"></span> ' +
                            name.replace(count, '') +
                            '<span class=\"count\">' + count + '</span>' +
                            '</li>'
                        );
                    });
                });

                // Category picker - remove categories
                $('#urst-remove-categories').on('click', function() {
                    $('#urst-selected-categories li').each(function() {
                        if ($(this).hasClass('selected-for-remove')) {
                            var li = $(this);
                            var id = li.data('id');
                            var name = li.text().trim();
                            var count = li.find('.count').text();
                            
                            li.remove();
                            
                            $('#urst-available-categories').append(
                                '<li data-id=\"' + id + '\">' +
                                '<label><input type=\"checkbox\" value=\"' + id + '\">' +
                                name.replace(count, '').trim() +
                                ' <span class=\"count\">' + count + '</span></label>' +
                                '</li>'
                            );
                        }
                    });
                });

                // Toggle selection in selected list
                $('#urst-selected-categories').on('click', 'li', function(e) {
                    if (!$(e.target).is('.dashicons')) {
                        $(this).toggleClass('selected-for-remove');
                    }
                });

                // Sortable for selected categories
                $('#urst-selected-categories').sortable({
                    placeholder: 'ui-sortable-placeholder',
                    handle: '.dashicons',
                    cursor: 'grabbing'
                });

                // ===== Phase 7.3: Sections functionality =====
                var sectionIndex = $('#urst-sections-container .urst-section-row').length;

                // Make sections sortable
                $('#urst-sections-container').sortable({
                    placeholder: 'ui-sortable-placeholder',
                    handle: '.urst-section-handle',
                    cursor: 'grabbing',
                    items: '.urst-section-row',
                    update: function() {
                        reindexSections();
                    }
                });

                // Add new section
                $('#urst-add-section').on('click', function() {
                    var template = $('#urst-section-template').html();
                    template = template.replace(/{{INDEX}}/g, sectionIndex);
                    $('#urst-sections-container').append(template);
                    sectionIndex++;
                });

                // Remove section
                $(document).on('click', '.urst-section-remove', function() {
                    if (confirm('Remove this section?')) {
                        $(this).closest('.urst-section-row').remove();
                        reindexSections();
                    }
                });

                // Toggle section collapse
                $(document).on('click', '.urst-section-toggle', function() {
                    var row = $(this).closest('.urst-section-row');
                    row.toggleClass('collapsed');
                    row.find('.urst-section-body').toggleClass('collapsed');
                });

                // Reindex sections after reorder/delete
                function reindexSections() {
                    $('#urst-sections-container .urst-section-row').each(function(newIndex) {
                        var row = $(this);
                        row.attr('data-index', newIndex);
                        row.find('input, select, textarea').each(function() {
                            var name = $(this).attr('name');
                            if (name) {
                                name = name.replace(/menu_sections\\[\\d+\\]/g, 'menu_sections[' + newIndex + ']');
                                $(this).attr('name', name);
                            }
                        });
                    });
                    sectionIndex = $('#urst-sections-container .urst-section-row').length;
                }

                // ===== Phase 7.5: Rules functionality =====
                
                // Toggle rules enabled state
                function updateRulesState() {
                    var enabled = $('#menu_rules_enabled').is(':checked');
                    $('.urst-rules-table').toggleClass('urst-rules-disabled', !enabled);
                }
                updateRulesState();
                $('#menu_rules_enabled').on('change', updateRulesState);

                // Day picker toggle
                $('.urst-day-option').on('click', function() {
                    $(this).toggleClass('selected');
                    var checkbox = $(this).find('input[type=checkbox]');
                    checkbox.prop('checked', !checkbox.prop('checked'));
                });

                // Time presets
                $('.urst-time-preset').on('click', function() {
                    var start = $(this).data('start');
                    var end = $(this).data('end');
                    $('#menu_rules_time_start').val(start);
                    $('#menu_rules_time_end').val(end);
                });

                // Preview - use current time
                $('#urst-preview-now').on('click', function() {
                    var now = new Date();
                    var offset = now.getTimezoneOffset();
                    now = new Date(now.getTime() - (offset * 60000));
                    var isoStr = now.toISOString().slice(0, 16);
                    $('#urst-preview-datetime').val(isoStr);
                });

                // Preview - test rules (requires AJAX endpoint)
                \$('#urst-preview-rules').on('click', function() {
                    var datetime = \$('#urst-preview-datetime').val();
                    if (!datetime) {
                        alert('Please select a date and time to test.');
                        return;
                    }

                    var \$result = \$('#urst-preview-result');
                    var \$status = \$result.find('.urst-preview-status');
                    var \$reason = \$result.find('.urst-preview-reason');

                    // Get current form values
                    var rulesEnabled = \$('#menu_rules_enabled').is(':checked');
                    var days = [];
                    \$('input[name=\"menu_rules_days[]\"]:checked').each(function() {
                        days.push(parseInt(\$(this).val()));
                    });
                    var timeStart = \$('#menu_rules_time_start').val();
                    var timeEnd = \$('#menu_rules_time_end').val();
                    var dateStart = \$('#menu_rules_date_start').val();
                    var dateEnd = \$('#menu_rules_date_end').val();

                    // Client-side preview (simple check)
                    var testDate = new Date(datetime);
                    var dayOfWeek = testDate.getDay();
                    var testTime = datetime.slice(11, 16);
                    var testDateStr = datetime.slice(0, 10);

                    var isActive = true;
                    var reason = '';

                    if (!rulesEnabled) {
                        reason = 'Scheduling is disabled - menu always active';
                    } else {
                        // Check day of week
                        if (days.length > 0 && days.indexOf(dayOfWeek) === -1) {
                            isActive = false;
                            reason = 'Not active on this day of week';
                        }
                        
                        // Check time window
                        if (isActive && timeStart && timeEnd) {
                            if (timeStart <= timeEnd) {
                                // Normal range (e.g., 11:00 to 15:00)
                                if (testTime < timeStart || testTime > timeEnd) {
                                    isActive = false;
                                    reason = 'Outside time window (' + timeStart + ' - ' + timeEnd + ')';
                                }
                            } else {
                                // Overnight range (e.g., 22:00 to 02:00)
                                if (testTime < timeStart && testTime > timeEnd) {
                                    isActive = false;
                                    reason = 'Outside time window (' + timeStart + ' - ' + timeEnd + ')';
                                }
                            }
                        }
                        
                        // Check date range
                        if (isActive && dateStart && testDateStr < dateStart) {
                            isActive = false;
                            reason = 'Before start date (' + dateStart + ')';
                        }
                        if (isActive && dateEnd && testDateStr > dateEnd) {
                            isActive = false;
                            reason = 'After end date (' + dateEnd + ')';
                        }
                        
                        if (isActive) {
                            reason = 'All rules satisfied';
                        }
                    }

                    \$result.removeClass('active inactive').addClass(isActive ? 'active' : 'inactive').show();
                    \$status.text(isActive ? '✓ ACTIVE' : '✗ INACTIVE');
                    \$reason.text(reason);
                });
            });
        ";
    }
}
