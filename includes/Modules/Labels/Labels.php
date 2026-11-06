<?php
/**
 * Labels Module.
 *
 * Lightweight product label system for visual indicators
 * like "Spicy", "Vegan", "Popular", etc.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Modules\Labels;

use Unstoppable\Restaurant\Core\ModuleInterface;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Labels class.
 *
 * @since 2.5.0
 */
class Labels implements ModuleInterface {

    /**
     * Meta key for product labels.
     */
    const META_KEY = '_urst_product_labels';

    /**
     * Available labels with their display config.
     *
     * @var array
     */
    private $available_labels = array();

    /**
     * Get module ID.
     *
     * @return string
     */
    public function get_id() {
        return 'labels';
    }

    /**
     * Get module name.
     *
     * @return string
     */
    public function get_name() {
        return __( 'Product Labels', 'unstoppable-restaurant' );
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
        $this->define_labels();

        // Admin: Add metabox to products.
        add_action( 'add_meta_boxes', array( $this, 'add_product_metabox' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_labels' ) );

        // Frontend: Render labels.
        add_action( 'urst_product_card_labels', array( $this, 'render_product_card_labels' ), 10, 1 );
        add_action( 'urst_modal_product_labels', array( $this, 'render_modal_labels' ), 10, 1 );

        // Make labels available via filter.
        add_filter( 'urst_get_product_labels', array( $this, 'get_product_labels' ), 10, 2 );
        add_filter( 'urst_available_labels', array( $this, 'get_available_labels' ) );
    }

    /**
     * Define available labels.
     */
    private function define_labels() {
        $this->available_labels = array(
            'spicy' => array(
                'label' => __( 'Spicy', 'unstoppable-restaurant' ),
                'icon'  => '🌶️',
                'color' => '#dc2626', // Red.
            ),
            'vegan' => array(
                'label' => __( 'Vegan', 'unstoppable-restaurant' ),
                'icon'  => '🌱',
                'color' => '#16a34a', // Green.
            ),
            'vegetarian' => array(
                'label' => __( 'Vegetarian', 'unstoppable-restaurant' ),
                'icon'  => '🥬',
                'color' => '#22c55e', // Light green.
            ),
            'gluten-free' => array(
                'label' => __( 'Gluten-Free', 'unstoppable-restaurant' ),
                'icon'  => '🌾',
                'color' => '#ca8a04', // Amber.
            ),
            'popular' => array(
                'label' => __( 'Popular', 'unstoppable-restaurant' ),
                'icon'  => '⭐',
                'color' => '#f59e0b', // Orange.
            ),
            'new' => array(
                'label' => __( 'New', 'unstoppable-restaurant' ),
                'icon'  => '✨',
                'color' => '#8b5cf6', // Purple.
            ),
            'chef-pick' => array(
                'label' => __( "Chef's Pick", 'unstoppable-restaurant' ),
                'icon'  => '👨‍🍳',
                'color' => '#0891b2', // Cyan.
            ),
            'contains-nuts' => array(
                'label' => __( 'Contains Nuts', 'unstoppable-restaurant' ),
                'icon'  => '🥜',
                'color' => '#78716c', // Gray.
            ),
        );

        /**
         * Filter available labels.
         *
         * @since 2.5.0
         * @param array $labels Available labels.
         */
        $this->available_labels = apply_filters( 'urst_define_labels', $this->available_labels );
    }

    /**
     * Get available labels.
     *
     * @return array
     */
    public function get_available_labels() {
        return $this->available_labels;
    }

    /**
     * Add product metabox.
     */
    public function add_product_metabox() {
        add_meta_box(
            'urst_product_labels',
            __( 'Product Labels', 'unstoppable-restaurant' ),
            array( $this, 'render_product_metabox' ),
            'product',
            'side',
            'default'
        );
    }

    /**
     * Render product metabox.
     *
     * @param \WP_Post $post Post object.
     */
    public function render_product_metabox( $post ) {
        $saved_labels = get_post_meta( $post->ID, self::META_KEY, true );
        if ( ! is_array( $saved_labels ) ) {
            $saved_labels = array();
        }

        wp_nonce_field( 'urst_save_product_labels', 'urst_labels_nonce' );
        ?>
        <div class="urst-product-labels-metabox">
            <p class="description" style="margin-bottom: 10px;">
                <?php esc_html_e( 'Select labels to display on this product.', 'unstoppable-restaurant' ); ?>
            </p>
            <?php foreach ( $this->available_labels as $key => $label ) : ?>
                <label style="display: block; margin-bottom: 6px; cursor: pointer;">
                    <input 
                        type="checkbox" 
                        name="urst_product_labels[]" 
                        value="<?php echo esc_attr( $key ); ?>"
                        <?php checked( in_array( $key, $saved_labels, true ) ); ?>
                    />
                    <span style="color: <?php echo esc_attr( $label['color'] ); ?>;">
                        <?php echo esc_html( $label['icon'] ); ?>
                    </span>
                    <?php echo esc_html( $label['label'] ); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Save product labels.
     *
     * @param int $post_id Post ID.
     */
    public function save_product_labels( $post_id ) {
        if ( ! isset( $_POST['urst_labels_nonce'] ) ||
             ! wp_verify_nonce( $_POST['urst_labels_nonce'], 'urst_save_product_labels' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $labels = array();

        if ( isset( $_POST['urst_product_labels'] ) && is_array( $_POST['urst_product_labels'] ) ) {
            $valid_keys = array_keys( $this->available_labels );
            foreach ( $_POST['urst_product_labels'] as $label ) {
                $label = sanitize_key( $label );
                if ( in_array( $label, $valid_keys, true ) ) {
                    $labels[] = $label;
                }
            }
        }

        update_post_meta( $post_id, self::META_KEY, $labels );
    }

    /**
     * Get labels for a product.
     *
     * @param array $labels     Current labels.
     * @param int   $product_id Product ID.
     * @return array
     */
    public function get_product_labels( $labels, $product_id ) {
        $saved = get_post_meta( $product_id, self::META_KEY, true );

        if ( ! is_array( $saved ) || empty( $saved ) ) {
            return array();
        }

        $result = array();
        foreach ( $saved as $key ) {
            if ( isset( $this->available_labels[ $key ] ) ) {
                $result[ $key ] = $this->available_labels[ $key ];
            }
        }

        return $result;
    }

    /**
     * Render labels on product card.
     *
     * @param int $product_id Product ID.
     */
    public function render_product_card_labels( $product_id ) {
        // Get from already-primed cache (update_postmeta_cache was called).
        $saved = get_post_meta( $product_id, self::META_KEY, true );

        if ( ! is_array( $saved ) || empty( $saved ) ) {
            return;
        }

        echo '<div class="ur-product-labels">';
        foreach ( $saved as $key ) {
            if ( isset( $this->available_labels[ $key ] ) ) {
                $label = $this->available_labels[ $key ];
                printf(
                    '<span class="ur-product-label ur-product-label--%s" style="--label-color: %s;" title="%s">%s</span>',
                    esc_attr( $key ),
                    esc_attr( $label['color'] ),
                    esc_attr( $label['label'] ),
                    esc_html( $label['icon'] )
                );
            }
        }
        echo '</div>';
    }

    /**
     * Render labels in modal.
     *
     * @param array $product Product data.
     */
    public function render_modal_labels( $product ) {
        $product_id = $product['id'] ?? 0;
        $labels = apply_filters( 'urst_get_product_labels', array(), $product_id );

        if ( empty( $labels ) ) {
            return;
        }

        echo '<div class="ur-modal-labels">';
        foreach ( $labels as $key => $label ) {
            printf(
                '<span class="ur-modal-label ur-modal-label--%s" style="--label-color: %s;">%s %s</span>',
                esc_attr( $key ),
                esc_attr( $label['color'] ),
                esc_html( $label['icon'] ),
                esc_html( $label['label'] )
            );
        }
        echo '</div>';
    }
}
