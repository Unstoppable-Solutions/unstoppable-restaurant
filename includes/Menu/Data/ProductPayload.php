<?php
/**
 * Product Payload.
 *
 * Structured product data with menu context attached.
 * This is what MenuRenderer outputs for each product.
 *
 * Contains:
 * - Product data (id, name, price, etc.)
 * - Menu context (menu_id, category_id, station_hint)
 * - Modifier information (attached groups)
 *
 * @package Unstoppable_Restaurant
 * @since 2.9.0
 */

namespace Unstoppable\Restaurant\Menu\Data;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ProductPayload class.
 *
 * @since 2.9.0
 */
class ProductPayload implements \JsonSerializable {

    /**
     * WooCommerce product ID.
     *
     * @var int
     */
    private $id;

    /**
     * Product name.
     *
     * @var string
     */
    private $name;

    /**
     * Product slug.
     *
     * @var string
     */
    private $slug;

    /**
     * Product type: simple | variable
     *
     * @var string
     */
    private $type;

    /**
     * Product permalink.
     *
     * @var string
     */
    private $permalink;

    /**
     * Short description / excerpt.
     *
     * @var string
     */
    private $description;

    /**
     * Thumbnail image URL.
     *
     * @var string
     */
    private $image_url;

    /**
     * Thumbnail attachment ID.
     *
     * @var int
     */
    private $image_id;

    /**
     * Base price (numeric).
     *
     * @var string
     */
    private $price;

    /**
     * Formatted price HTML.
     *
     * @var string
     */
    private $price_html;

    /**
     * Whether product is on sale.
     *
     * @var bool
     */
    private $on_sale;

    /**
     * Whether product is in stock.
     *
     * @var bool
     */
    private $in_stock;

    /**
     * Stock quantity (null if not managed).
     *
     * @var int|null
     */
    private $stock_qty;

    /**
     * Menu context.
     *
     * @var MenuContext
     */
    private $context;

    /**
     * Modifier group IDs attached to this product.
     *
     * @var array
     */
    private $modifier_groups;

    /**
     * Product labels (e.g., 'spicy', 'vegan', 'gf').
     *
     * @var array
     */
    private $labels;

    /**
     * Constructor.
     *
     * @param array       $data    Product data.
     * @param MenuContext $context Menu context.
     */
    public function __construct( array $data, MenuContext $context ) {
        $this->id              = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
        $this->name            = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        $this->slug            = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';
        $this->type            = isset( $data['type'] ) ? sanitize_key( $data['type'] ) : 'simple';
        $this->permalink       = isset( $data['permalink'] ) ? esc_url( $data['permalink'] ) : '';
        $this->description     = isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '';
        $this->image_url       = isset( $data['image_url'] ) ? esc_url( $data['image_url'] ) : '';
        $this->image_id        = isset( $data['image_id'] ) ? absint( $data['image_id'] ) : 0;
        $this->price           = isset( $data['price'] ) ? sanitize_text_field( $data['price'] ) : '';
        $this->price_html      = isset( $data['price_html'] ) ? wp_kses_post( $data['price_html'] ) : '';
        $this->on_sale         = isset( $data['on_sale'] ) ? (bool) $data['on_sale'] : false;
        $this->in_stock        = isset( $data['in_stock'] ) ? (bool) $data['in_stock'] : true;
        $this->stock_qty       = isset( $data['stock_qty'] ) ? ( null === $data['stock_qty'] ? null : absint( $data['stock_qty'] ) ) : null;
        $this->modifier_groups = isset( $data['modifier_groups'] ) ? array_map( 'sanitize_key', (array) $data['modifier_groups'] ) : array();
        $this->labels          = isset( $data['labels'] ) ? array_map( 'sanitize_key', (array) $data['labels'] ) : array();
        $this->context         = $context;
    }

    /**
     * Get product ID.
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get product name.
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get product slug.
     *
     * @return string
     */
    public function get_slug() {
        return $this->slug;
    }

    /**
     * Get product type.
     *
     * @return string
     */
    public function get_type() {
        return $this->type;
    }

    /**
     * Get permalink.
     *
     * @return string
     */
    public function get_permalink() {
        return $this->permalink;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Get image URL.
     *
     * @return string
     */
    public function get_image_url() {
        return $this->image_url;
    }

    /**
     * Get image ID.
     *
     * @return int
     */
    public function get_image_id() {
        return $this->image_id;
    }

    /**
     * Get price.
     *
     * @return string
     */
    public function get_price() {
        return $this->price;
    }

    /**
     * Get formatted price HTML.
     *
     * @return string
     */
    public function get_price_html() {
        return $this->price_html;
    }

    /**
     * Check if on sale.
     *
     * @return bool
     */
    public function is_on_sale() {
        return $this->on_sale;
    }

    /**
     * Check if in stock.
     *
     * @return bool
     */
    public function is_in_stock() {
        return $this->in_stock;
    }

    /**
     * Get stock quantity.
     *
     * @return int|null
     */
    public function get_stock_qty() {
        return $this->stock_qty;
    }

    /**
     * Get menu context.
     *
     * @return MenuContext
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Get modifier group IDs.
     *
     * @return array
     */
    public function get_modifier_groups() {
        return $this->modifier_groups;
    }

    /**
     * Check if product has modifiers.
     *
     * @return bool
     */
    public function has_modifiers() {
        return ! empty( $this->modifier_groups );
    }

    /**
     * Get product labels.
     *
     * @return array
     */
    public function get_labels() {
        return $this->labels;
    }

    /**
     * Check if product has a specific label.
     *
     * @param string $label Label key.
     * @return bool
     */
    public function has_label( $label ) {
        return in_array( $label, $this->labels, true );
    }

    /**
     * Convert to array for templates.
     *
     * This is backward-compatible with current template format.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id'              => $this->id,
            'name'            => $this->name,
            'slug'            => $this->slug,
            'type'            => $this->type,
            'permalink'       => $this->permalink,
            'description'     => $this->description,
            'image_url'       => $this->image_url,
            'image_id'        => $this->image_id,
            'price'           => $this->price,
            'price_html'      => $this->price_html,
            'on_sale'         => $this->on_sale,
            'in_stock'        => $this->in_stock,
            'stock_qty'       => $this->stock_qty,
            'modifier_groups' => $this->modifier_groups,
            'labels'          => $this->labels,
            // Context embedded.
            'menu_context'    => $this->context->to_array(),
        );
    }

    /**
     * Convert to legacy array format for backward compatibility.
     *
     * @return array
     */
    public function to_legacy_array() {
        return array(
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'type'        => $this->type,
            'permalink'   => $this->permalink,
            'description' => $this->description,
            'image_url'   => $this->image_url,
            'image_id'    => $this->image_id,
            'price'       => $this->price,
            'price_html'  => $this->price_html,
            'on_sale'     => $this->on_sale,
            'in_stock'    => $this->in_stock,
            'stock_qty'   => $this->stock_qty,
            'is_variable' => 'variable' === $this->type,
            'min_price'   => '',
            'max_price'   => '',
        );
    }

    /**
     * JSON serialization.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize() {
        return $this->to_array();
    }

    /**
     * Get data for cart item meta.
     *
     * @return array
     */
    public function get_cart_meta() {
        return array(
            'urst_menu_context' => $this->context->to_array(),
        );
    }
}
