<?php
/**
 * Product Modal Module.
 *
 * Displays product details in a modal overlay.
 * Foundation for Conversational Modifiers (Phase 5).
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Modules\ProductModal;

use Unstoppable\Restaurant\Core\ModuleInterface;
use Unstoppable\Restaurant\Core\TemplateLoader;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ProductModal class.
 *
 * @since 1.0.0
 */
class ProductModal implements ModuleInterface {

    /**
     * Get module ID.
     *
     * @return string
     */
    public function get_id() {
        return 'product-modal';
    }

    /**
     * Get module name.
     *
     * @return string
     */
    public function get_name() {
        return __( 'Product Modal', 'unstoppable-restaurant' );
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
        // AJAX handler to get product modal content.
        add_action( 'wp_ajax_urst_get_product_modal', array( $this, 'ajax_get_product_modal' ) );
        add_action( 'wp_ajax_nopriv_urst_get_product_modal', array( $this, 'ajax_get_product_modal' ) );

        // Add modal container to footer.
        add_action( 'wp_footer', array( $this, 'render_modal_container' ) );
    }

    /**
     * Render modal container in footer.
     * The actual modal content is loaded via AJAX.
     */
    public function render_modal_container() {
        if ( is_admin() || is_cart() || is_checkout() ) {
            return;
        }
        ?>
        <div 
            id="ur-product-modal" 
            class="ur-modal-wrapper"
            aria-hidden="true"
            role="dialog"
            aria-modal="true"
            aria-labelledby="ur-modal-title"
        >
            <div class="ur-modal__backdrop" data-ur-close-modal></div>
            <div class="ur-modal__container">
                <div class="ur-modal__content" data-ur-modal-content>
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Get product modal content.
     */
    public function ajax_get_product_modal() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'urst_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'unstoppable-restaurant' ) ) );
        }

        // Sanitize input.
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        // Validate.
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product.', 'unstoppable-restaurant' ) ) );
        }

        $product = wc_get_product( $product_id );

        if ( ! $product || ! $product->exists() ) {
            wp_send_json_error( array( 'message' => __( 'Product not found.', 'unstoppable-restaurant' ) ) );
        }

        // Get formatted product data.
        $product_data = $this->format_product_data( $product );

        // Render modal HTML.
        $html = TemplateLoader::instance()->get( 'modal/product-modal.php', array(
            'product' => $product_data,
        ), true );

        wp_send_json_success( array(
            'html'    => $html,
            'product' => $product_data,
        ) );
    }

    /**
     * Format product data for modal.
     *
     * @param \WC_Product $product Product object.
     * @return array
     */
    private function format_product_data( $product ) {
        $image_id = $product->get_image_id();

        return array(
            'id'                => $product->get_id(),
            'name'              => $product->get_name(),
            'slug'              => $product->get_slug(),
            'type'              => $product->get_type(),
            'short_description' => $product->get_short_description(),
            'description'       => $product->get_description(),
            'price'             => $product->get_price(),
            'price_html'        => $product->get_price_html(),
            'regular_price'     => $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price(),
            'on_sale'           => $product->is_on_sale(),
            'in_stock'          => $product->is_in_stock(),
            'stock_quantity'    => $product->get_stock_quantity(),
            'max_quantity'      => $this->get_max_quantity( $product ),
            'image_url'         => $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '',
            'is_variable'       => $product->is_type( 'variable' ),
        );
    }

    /**
     * Get maximum purchasable quantity.
     *
     * @param \WC_Product $product Product object.
     * @return int
     */
    private function get_max_quantity( $product ) {
        $max = $product->get_max_purchase_quantity();
        
        // Default to 99 if no limit set.
        if ( $max < 0 ) {
            $max = 99;
        }

        return $max;
    }
}
