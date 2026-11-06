<?php
/**
 * Modifier Option Data Structure.
 *
 * Represents a single selectable option within a modifier group.
 * Example: "Extra Cheese" with +$1.50 price.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Modules\Modifiers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ModifierOption class.
 *
 * @since 2.3.0
 */
class ModifierOption {

    /**
     * Option ID.
     *
     * @var string
     */
    public $id;

    /**
     * Display label (shown to customer).
     *
     * @var string
     */
    public $label;

    /**
     * Price adjustment (can be positive, negative, or zero).
     *
     * @var float
     */
    public $price;

    /**
     * Kitchen-readable label (shown on tickets).
     * Falls back to $label if empty.
     *
     * @var string
     */
    public $kitchen_label;

    /**
     * Whether this option is selected by default.
     *
     * @var bool
     */
    public $is_default;

    /**
     * Optional image URL for visual selection.
     *
     * @var string
     */
    public $image_url;

    /**
     * Display order within the group.
     *
     * @var int
     */
    public $sort_order;

    /**
     * Constructor.
     *
     * @param array $data Option data.
     */
    public function __construct( array $data = array() ) {
        $this->id            = isset( $data['id'] ) ? sanitize_key( $data['id'] ) : $this->generate_id();
        $this->label         = isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : '';
        $this->price         = isset( $data['price'] ) ? floatval( $data['price'] ) : 0.0;
        $this->kitchen_label = isset( $data['kitchen_label'] ) ? sanitize_text_field( $data['kitchen_label'] ) : '';
        $this->is_default    = ! empty( $data['is_default'] ) || ! empty( $data['default'] );
        $this->image_url     = isset( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : '';
        $this->sort_order    = isset( $data['sort_order'] ) ? absint( $data['sort_order'] ) : 0;
    }

    /**
     * Generate unique ID.
     *
     * @return string
     */
    private function generate_id() {
        return 'opt_' . substr( md5( uniqid( '', true ) ), 0, 8 );
    }

    /**
     * Get display price string.
     *
     * @return string Formatted price like "+$1.50" or "-$0.50" or empty if zero.
     */
    public function get_price_display() {
        if ( abs( $this->price ) < 0.01 ) {
            return '';
        }

        $prefix = $this->price > 0 ? '+' : '';
        return $prefix . wc_price( $this->price );
    }

    /**
     * Get the text to display on kitchen tickets.
     *
     * @return string
     */
    public function get_kitchen_text() {
        return ! empty( $this->kitchen_label ) ? $this->kitchen_label : $this->label;
    }

    /**
     * Convert to array for storage/JSON.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id'            => $this->id,
            'label'         => $this->label,
            'price'         => $this->price,
            'kitchen_label' => $this->kitchen_label,
            'is_default'    => $this->is_default,
            'image_url'     => $this->image_url,
            'sort_order'    => $this->sort_order,
        );
    }
}
