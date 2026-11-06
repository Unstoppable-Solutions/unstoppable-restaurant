<?php
/**
 * Modifiers Module.
 *
 * Conversational Modifiers system - presents product customizations
 * in the natural order humans order food, step by step.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Modules\Modifiers;

use Unstoppable\Restaurant\Core\ModuleInterface;
use Unstoppable\Restaurant\Core\TemplateLoader;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load data model classes.
require_once __DIR__ . '/ModifierOption.php';
require_once __DIR__ . '/ModifierGroup.php';
require_once __DIR__ . '/ModifierEngine.php';
require_once __DIR__ . '/ModifierRepository.php';
require_once __DIR__ . '/ModifierCartData.php';

/**
 * Modifiers class.
 *
 * @since 1.0.0
 */
class Modifiers implements ModuleInterface {

    /**
     * Get module ID.
     *
     * @return string
     */
    public function get_id() {
        return 'modifiers';
    }

    /**
     * Get module name.
     *
     * @return string
     */
    public function get_name() {
        return __( 'Conversational Modifiers', 'unstoppable-restaurant' );
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
        // Register post type IMMEDIATELY - this runs during 'init' action,
        // so we can register the post type directly without a hook.
        // Using add_action('init', ...) from within init would never fire
        // because init has already started.
        $this->register_post_type();

        // Hook into product modal to inject modifier data.
        add_filter( 'urst_get_product_modifier_flow', array( $this, 'get_product_flow' ), 10, 2 );
        add_action( 'urst_modal_modifier_flow', array( $this, 'render_modifier_flow' ), 10, 1 );

        // Cart integration.
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_modifier_prices' ), 20, 1 );

        // Display in cart drawer.
        add_action( 'urst_cart_item_modifiers', array( $this, 'render_cart_item_modifiers' ), 10, 2 );

        // AJAX handlers.
        add_action( 'wp_ajax_urst_get_modifier_flow', array( $this, 'ajax_get_flow' ) );
        add_action( 'wp_ajax_nopriv_urst_get_modifier_flow', array( $this, 'ajax_get_flow' ) );
        add_action( 'wp_ajax_urst_validate_modifier_step', array( $this, 'ajax_validate_step' ) );
        add_action( 'wp_ajax_nopriv_urst_validate_modifier_step', array( $this, 'ajax_validate_step' ) );

        // Admin.
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_' . ModifierRepository::POST_TYPE, array( $this, 'save_meta_boxes' ) );
        add_action( 'admin_notices', array( $this, 'display_validation_warnings' ) );

        // Add admin menu item under main plugin menu.
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    /**
     * Register post type for modifier flows.
     */
    public function register_post_type() {
        register_post_type( ModifierRepository::POST_TYPE, array(
            'labels'       => array(
                'name'               => __( 'Modifier Flows', 'unstoppable-restaurant' ),
                'singular_name'      => __( 'Modifier Flow', 'unstoppable-restaurant' ),
                'add_new'            => __( 'Add Flow', 'unstoppable-restaurant' ),
                'add_new_item'       => __( 'Add New Modifier Flow', 'unstoppable-restaurant' ),
                'edit_item'          => __( 'Edit Modifier Flow', 'unstoppable-restaurant' ),
                'new_item'           => __( 'New Modifier Flow', 'unstoppable-restaurant' ),
                'view_item'          => __( 'View Modifier Flow', 'unstoppable-restaurant' ),
                'search_items'       => __( 'Search Modifier Flows', 'unstoppable-restaurant' ),
                'not_found'          => __( 'No modifier flows found.', 'unstoppable-restaurant' ),
                'not_found_in_trash' => __( 'No modifier flows found in Trash.', 'unstoppable-restaurant' ),
                'all_items'          => __( 'Modifier Flows', 'unstoppable-restaurant' ),
            ),
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false, // We add it manually.
            'supports'     => array( 'title' ),
            'capability_type' => 'post',
        ) );
    }

    /**
     * Add admin menu item.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'unstoppable-restaurant',
            __( 'Modifier Flows', 'unstoppable-restaurant' ),
            __( 'Modifiers', 'unstoppable-restaurant' ),
            'manage_woocommerce',
            'edit.php?post_type=' . ModifierRepository::POST_TYPE
        );
    }

    /**
     * Display validation warnings after save.
     */
    public function display_validation_warnings() {
        $screen = get_current_screen();
        if ( ! $screen || ModifierRepository::POST_TYPE !== $screen->post_type ) {
            return;
        }

        // Check for warnings from recent save.
        global $post;
        if ( ! $post ) {
            return;
        }

        $warnings = get_transient( 'urst_modifier_warnings_' . $post->ID );
        if ( ! $warnings ) {
            return;
        }

        // Clear transient.
        delete_transient( 'urst_modifier_warnings_' . $post->ID );

        // Display warnings.
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . esc_html__( 'Modifier Flow Warnings:', 'unstoppable-restaurant' ) . '</strong></p>';
        echo '<ul style="margin-left: 20px; list-style: disc;">';
        foreach ( $warnings as $warning ) {
            echo '<li>' . esc_html( $warning ) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Get modifier flow for a product.
     *
     * @param mixed $flow       Current flow value.
     * @param int   $product_id Product ID.
     * @return array|null
     */
    public function get_product_flow( $flow, $product_id ) {
        return ModifierRepository::get_flow_for_product( $product_id );
    }

    /**
     * Render modifier flow in product modal.
     *
     * @param array $product Product data.
     */
    public function render_modifier_flow( $product ) {
        $flow = ModifierRepository::get_flow_for_product( $product['id'] );

        if ( ! $flow || empty( $flow['groups'] ) ) {
            return;
        }

        TemplateLoader::instance()->get( 'modifiers/flow.php', array(
            'flow'    => $flow,
            'product' => $product,
        ) );
    }

    /**
     * AJAX: Get modifier flow data.
     */
    public function ajax_get_flow() {
        if ( ! check_ajax_referer( 'urst_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'unstoppable-restaurant' ) ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product.', 'unstoppable-restaurant' ) ) );
        }

        $flow = ModifierRepository::get_flow_for_product( $product_id );

        if ( ! $flow ) {
            wp_send_json_success( array( 'has_flow' => false ) );
        }

        // Get product price.
        $product = wc_get_product( $product_id );
        $base_price = $product ? floatval( $product->get_price() ) : 0;

        wp_send_json_success( array(
            'has_flow'   => true,
            'flow'       => $flow,
            'base_price' => $base_price,
        ) );
    }

    /**
     * AJAX: Validate a modifier step.
     */
    public function ajax_validate_step() {
        if ( ! check_ajax_referer( 'urst_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'unstoppable-restaurant' ) ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $group_id   = isset( $_POST['group_id'] ) ? sanitize_key( $_POST['group_id'] ) : '';
        $selections = isset( $_POST['selections'] ) ? array_map( 'sanitize_key', (array) $_POST['selections'] ) : array();

        $flow = ModifierRepository::get_flow_for_product( $product_id );

        if ( ! $flow ) {
            wp_send_json_error( array( 'message' => __( 'No modifier flow found.', 'unstoppable-restaurant' ) ) );
        }

        // Create engine and validate.
        $engine = new ModifierEngine( $flow['groups'] );

        // Find and validate the specific group.
        $validation = $engine->set_selections( $group_id, $selections );

        wp_send_json_success( array(
            'valid' => $validation['valid'],
            'error' => $validation['error'],
        ) );
    }

    /**
     * Add modifier data to cart item.
     *
     * @param array $cart_item_data Cart item data.
     * @param int   $product_id     Product ID.
     * @param int   $variation_id   Variation ID.
     * @return array
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        // Modifiers are passed via cart_item_data from frontend.
        // Already sanitized in CartDrawer module.
        return $cart_item_data;
    }

    /**
     * Display modifier data in cart/checkout.
     *
     * Handles both new normalized format and legacy format.
     *
     * @param array $item_data Item data.
     * @param array $cart_item Cart item.
     * @return array
     */
    public function display_cart_item_data( $item_data, $cart_item ) {
        if ( empty( $cart_item['ur_modifiers'] ) ) {
            return $item_data;
        }

        // Parse data (handles both formats).
        $cart_data = ModifierCartData::from_array( $cart_item['ur_modifiers'] );

        // If legacy data, try to enrich from current flow.
        if ( $cart_data->is_legacy() ) {
            $cart_data->enrich_from_product( $cart_item['product_id'] );
        }

        // Get display summary.
        $summary = $cart_data->get_display_summary();

        foreach ( $summary as $item ) {
            $item_data[] = array(
                'key'   => $item['key'],
                'value' => $item['value'],
            );
        }

        if ( ! empty( $cart_item['ur_special_instructions'] ) ) {
            $item_data[] = array(
                'key'   => __( 'Special Instructions', 'unstoppable-restaurant' ),
                'value' => $cart_item['ur_special_instructions'],
            );
        }

        return $item_data;
    }

    /**
     * Render modifiers in cart drawer item.
     *
     * Handles both new normalized format and legacy format.
     *
     * @param array $modifiers  Modifier data (new or legacy format).
     * @param array $cart_item Cart item data.
     */
    public function render_cart_item_modifiers( $modifiers, $cart_item ) {
        // Parse data (handles both formats).
        $cart_data = ModifierCartData::from_array( $modifiers );

        // If legacy data, try to enrich from current flow.
        if ( $cart_data->is_legacy() ) {
            $cart_data->enrich_from_product( $cart_item['product_id'] );
        }

        $kitchen_output = $cart_data->get_kitchen_output();

        if ( ! empty( $kitchen_output ) ) {
            echo '<div class="ur-cart-item__modifier-list">';
            foreach ( $kitchen_output as $line ) {
                echo '<span class="ur-cart-item__modifier">• ' . esc_html( $line ) . '</span>';
            }
            echo '</div>';
        }
    }

    /**
     * Add modifier meta to order item.
     *
     * Handles both new normalized format and legacy format.
     *
     * @param \WC_Order_Item_Product $item          Order item.
     * @param string                 $cart_item_key Cart item key.
     * @param array                  $values        Cart item values.
     * @param \WC_Order              $order         Order.
     */
    public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( ! empty( $values['ur_modifiers'] ) ) {
            // Parse data (handles both formats).
            $cart_data = ModifierCartData::from_array( $values['ur_modifiers'] );

            // If legacy data, try to enrich from current flow.
            if ( $cart_data->is_legacy() ) {
                $cart_data->enrich_from_product( $values['product_id'] );
            }

            // Store normalized data.
            $item->add_meta_data( '_ur_modifiers', $cart_data->to_array(), true );

            // Store human-readable kitchen text.
            if ( ! empty( $values['ur_special_instructions'] ) ) {
                $cart_data->special_instructions = sanitize_textarea_field( $values['ur_special_instructions'] );
            }

            $kitchen_output = $cart_data->get_kitchen_output();
            if ( ! empty( $kitchen_output ) ) {
                $item->add_meta_data( '_ur_kitchen_text', implode( "\n", $kitchen_output ), true );
            }
        }

        if ( ! empty( $values['ur_special_instructions'] ) ) {
            $item->add_meta_data( '_ur_special_instructions', $values['ur_special_instructions'], true );
            $item->add_meta_data( __( 'Special Instructions', 'unstoppable-restaurant' ), $values['ur_special_instructions'], true );
        }
    }

    /**
     * Calculate modifier prices.
     *
     * Handles both new normalized format and legacy format.
     *
     * @param \WC_Cart $cart Cart object.
     */
    public function calculate_modifier_prices( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( empty( $cart_item['ur_modifiers'] ) ) {
                continue;
            }

            // Parse data (handles both formats).
            $cart_data = ModifierCartData::from_array( $cart_item['ur_modifiers'] );

            // If legacy data, try to enrich from current flow.
            if ( $cart_data->is_legacy() ) {
                $cart_data->enrich_from_product( $cart_item['product_id'] );
            }

            // Use stored modifier total, or recalculate.
            $modifier_total = $cart_data->modifier_total;
            if ( $modifier_total <= 0 && ! $cart_data->is_empty() ) {
                $modifier_total = $cart_data->recalculate_total();
            }

            if ( $modifier_total > 0 ) {
                $base_price = floatval( $cart_item['data']->get_price() );
                $cart_item['data']->set_price( $base_price + $modifier_total );
            }
        }
    }

    /**
     * Add meta boxes to flow edit screen.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'urst_modifier_groups',
            __( 'Modifier Groups', 'unstoppable-restaurant' ),
            array( $this, 'render_groups_meta_box' ),
            ModifierRepository::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'urst_modifier_assignments',
            __( 'Assign to Products', 'unstoppable-restaurant' ),
            array( $this, 'render_assignments_meta_box' ),
            ModifierRepository::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Render groups meta box.
     *
     * @param \WP_Post $post Post object.
     */
    public function render_groups_meta_box( $post ) {
        wp_nonce_field( 'urst_save_modifier_flow', 'urst_modifier_nonce' );

        $groups = get_post_meta( $post->ID, ModifierRepository::META_GROUPS, true );
        if ( ! is_array( $groups ) ) {
            $groups = array();
        }

        TemplateLoader::instance()->get( 'admin/modifier-groups-editor.php', array(
            'groups' => $groups,
        ) );
    }

    /**
     * Render assignments meta box.
     *
     * @param \WP_Post $post Post object.
     */
    public function render_assignments_meta_box( $post ) {
        $products   = get_post_meta( $post->ID, ModifierRepository::META_ASSIGNED_PRODUCTS, true );
        $categories = get_post_meta( $post->ID, ModifierRepository::META_ASSIGNED_CATEGORIES, true );

        if ( ! is_array( $products ) ) {
            $products = array();
        }
        if ( ! is_array( $categories ) ) {
            $categories = array();
        }

        TemplateLoader::instance()->get( 'admin/modifier-assignments.php', array(
            'products'   => $products,
            'categories' => $categories,
        ) );
    }

    /**
     * Save meta boxes.
     *
     * @param int $post_id Post ID.
     */
    public function save_meta_boxes( $post_id ) {
        // Prevent infinite loop - wp_update_post in save_flow triggers this again.
        static $saving = array();
        if ( isset( $saving[ $post_id ] ) ) {
            return;
        }
        $saving[ $post_id ] = true;

        if ( ! isset( $_POST['urst_modifier_nonce'] ) ||
             ! wp_verify_nonce( $_POST['urst_modifier_nonce'], 'urst_save_modifier_flow' ) ) {
            unset( $saving[ $post_id ] );
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            unset( $saving[ $post_id ] );
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            unset( $saving[ $post_id ] );
            return;
        }

        // Save groups with validation.
        if ( isset( $_POST['urst_groups'] ) ) {
            $groups = json_decode( stripslashes( $_POST['urst_groups'] ), true );
            if ( is_array( $groups ) ) {
                // Validate groups before saving.
                $validation = $this->validate_groups( $groups );

                if ( ! empty( $validation['warnings'] ) ) {
                    // Store warnings to display after redirect.
                    set_transient( 'urst_modifier_warnings_' . $post_id, $validation['warnings'], 60 );
                }

                // Save groups directly to post meta (don't use save_flow which calls wp_update_post).
                $sanitized_groups = $this->sanitize_groups_for_save( $groups );
                update_post_meta( $post_id, ModifierRepository::META_GROUPS, $sanitized_groups );
            }
        }

        // Save products.
        if ( isset( $_POST['urst_products'] ) ) {
            $products = array_filter( array_map( 'absint', (array) $_POST['urst_products'] ) );
            update_post_meta( $post_id, ModifierRepository::META_ASSIGNED_PRODUCTS, $products );
        } else {
            delete_post_meta( $post_id, ModifierRepository::META_ASSIGNED_PRODUCTS );
        }

        // Save categories.
        if ( isset( $_POST['urst_categories'] ) ) {
            $categories = array_filter( array_map( 'absint', (array) $_POST['urst_categories'] ) );
            update_post_meta( $post_id, ModifierRepository::META_ASSIGNED_CATEGORIES, $categories );
        } else {
            delete_post_meta( $post_id, ModifierRepository::META_ASSIGNED_CATEGORIES );
        }

        ModifierRepository::clear_cache();
        unset( $saving[ $post_id ] );
    }

    /**
     * Sanitize groups for saving.
     *
     * @param array $groups Raw groups data.
     * @return array Sanitized groups.
     */
    private function sanitize_groups_for_save( array $groups ) {
        $sanitized = array();

        foreach ( $groups as $index => $group ) {
            $sanitized_group = array(
                'id'         => isset( $group['id'] ) ? sanitize_key( $group['id'] ) : 'grp_' . $index,
                'label'      => isset( $group['label'] ) ? sanitize_text_field( $group['label'] ) : '',
                'type'       => isset( $group['type'] ) && in_array( $group['type'], array( 'single', 'multiple' ), true )
                                ? $group['type'] : 'single',
                'required'   => ! empty( $group['required'] ),
                'min_select' => isset( $group['min_select'] ) ? absint( $group['min_select'] ) : 0,
                'max_select' => isset( $group['max_select'] ) ? absint( $group['max_select'] ) : 0,
                'sort_order' => isset( $group['sort_order'] ) ? absint( $group['sort_order'] ) : $index,
                'options'    => array(),
            );

            // Sanitize options.
            if ( ! empty( $group['options'] ) && is_array( $group['options'] ) ) {
                foreach ( $group['options'] as $opt_index => $option ) {
                    $sanitized_group['options'][] = array(
                        'id'            => isset( $option['id'] ) ? sanitize_key( $option['id'] ) : 'opt_' . $opt_index,
                        'label'         => isset( $option['label'] ) ? sanitize_text_field( $option['label'] ) : '',
                        'price'         => isset( $option['price'] ) ? floatval( $option['price'] ) : 0,
                        'kitchen_label' => isset( $option['kitchen_label'] ) ? sanitize_text_field( $option['kitchen_label'] ) : '',
                        'is_default'    => ! empty( $option['is_default'] ),
                        'sort_order'    => isset( $option['sort_order'] ) ? absint( $option['sort_order'] ) : $opt_index,
                    );
                }
            }

            $sanitized[] = $sanitized_group;
        }

        return $sanitized;
    }

    /**
     * Validate modifier groups.
     *
     * Returns warnings for problematic configurations.
     * Does not block saving - just warns.
     *
     * @param array $groups Groups data.
     * @return array { warnings: string[] }
     */
    private function validate_groups( $groups ) {
        $warnings = array();

        foreach ( $groups as $index => $group ) {
            $group_num = $index + 1;
            $label = ! empty( $group['label'] ) ? $group['label'] : sprintf( __( 'Group %d', 'unstoppable-restaurant' ), $group_num );

            // Warning: Required group with no options.
            if ( ! empty( $group['required'] ) && empty( $group['options'] ) ) {
                $warnings[] = sprintf(
                    /* translators: %s: group label */
                    __( '"%s" is marked as required but has no options. Customers won\'t be able to complete the order.', 'unstoppable-restaurant' ),
                    $label
                );
            }

            // Warning: Empty group label.
            if ( empty( $group['label'] ) ) {
                $warnings[] = sprintf(
                    /* translators: %d: group number */
                    __( 'Group %d has no label. Consider adding a question like "Choose your size".', 'unstoppable-restaurant' ),
                    $group_num
                );
            }

            // Warning: Min > Max.
            $min = isset( $group['min_select'] ) ? absint( $group['min_select'] ) : 0;
            $max = isset( $group['max_select'] ) ? absint( $group['max_select'] ) : 0;

            if ( $min > 0 && $max > 0 && $min > $max ) {
                $warnings[] = sprintf(
                    /* translators: %s: group label */
                    __( '"%s" has minimum selections (%d) greater than maximum (%d). This will cause validation errors.', 'unstoppable-restaurant' ),
                    $label,
                    $min,
                    $max
                );
            }

            // Warning: Min selections exceeds available options.
            $option_count = isset( $group['options'] ) ? count( $group['options'] ) : 0;
            if ( $min > 0 && $min > $option_count ) {
                $warnings[] = sprintf(
                    /* translators: 1: group label, 2: min selections, 3: option count */
                    __( '"%1$s" requires at least %2$d selection(s) but only has %3$d option(s).', 'unstoppable-restaurant' ),
                    $label,
                    $min,
                    $option_count
                );
            }

            // Check options.
            if ( ! empty( $group['options'] ) ) {
                foreach ( $group['options'] as $opt_index => $option ) {
                    if ( empty( $option['label'] ) ) {
                        $warnings[] = sprintf(
                            /* translators: 1: option number, 2: group label */
                            __( 'Option %1$d in "%2$s" has no label.', 'unstoppable-restaurant' ),
                            $opt_index + 1,
                            $label
                        );
                    }
                }
            }
        }

        return array( 'warnings' => $warnings );
    }
}
