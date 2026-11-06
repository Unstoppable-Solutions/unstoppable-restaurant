<?php
/**
 * Category Payload.
 *
 * Structured category data with products.
 * Contains ProductPayload objects, not raw arrays.
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
 * CategoryPayload class.
 *
 * @since 2.9.0
 */
class CategoryPayload implements \JsonSerializable, \Countable, \IteratorAggregate {

    /**
     * Category term ID.
     *
     * @var int
     */
    private $id;

    /**
     * Category name.
     *
     * @var string
     */
    private $name;

    /**
     * Category slug.
     *
     * @var string
     */
    private $slug;

    /**
     * Category description.
     *
     * @var string
     */
    private $description;

    /**
     * Station hint for this category: kitchen | bar | counter | expo | null
     *
     * @var string|null
     */
    private $station_hint;

    /**
     * Products in this category.
     *
     * @var ProductPayload[]
     */
    private $products = array();

    /**
     * Constructor.
     *
     * @param array $data Category data.
     */
    public function __construct( array $data = array() ) {
        $this->id           = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
        $this->name         = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        $this->slug         = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';
        $this->description  = isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '';
        $this->station_hint = isset( $data['station_hint'] ) ? $this->sanitize_station( $data['station_hint'] ) : null;
    }

    /**
     * Sanitize station hint.
     *
     * @param string|null $station Station value.
     * @return string|null
     */
    private function sanitize_station( $station ) {
        if ( null === $station || '' === $station ) {
            return null;
        }

        $allowed = array( 'kitchen', 'bar', 'counter', 'expo', 'prep' );
        $station = sanitize_key( $station );

        return in_array( $station, $allowed, true ) ? $station : null;
    }

    /**
     * Get category ID.
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get category name.
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get category slug.
     *
     * @return string
     */
    public function get_slug() {
        return $this->slug;
    }

    /**
     * Get category description.
     *
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Get station hint.
     *
     * @return string|null
     */
    public function get_station_hint() {
        return $this->station_hint;
    }

    /**
     * Set station hint.
     *
     * @param string|null $station Station hint.
     */
    public function set_station_hint( $station ) {
        $this->station_hint = $this->sanitize_station( $station );
    }

    /**
     * Get all products.
     *
     * @return ProductPayload[]
     */
    public function get_products() {
        return $this->products;
    }

    /**
     * Add a product.
     *
     * @param ProductPayload $product Product payload.
     */
    public function add_product( ProductPayload $product ) {
        $this->products[] = $product;
    }

    /**
     * Check if category has products.
     *
     * @return bool
     */
    public function has_products() {
        return ! empty( $this->products );
    }

    /**
     * Get product count.
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count() {
        return count( $this->products );
    }

    /**
     * Get iterator for products.
     *
     * @return \ArrayIterator
     */
    #[\ReturnTypeWillChange]
    public function getIterator() {
        return new \ArrayIterator( $this->products );
    }

    /**
     * Convert to array.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id'           => $this->id,
            'name'         => $this->name,
            'slug'         => $this->slug,
            'description'  => $this->description,
            'station_hint' => $this->station_hint,
            'count'        => count( $this->products ),
            'products'     => array_map( function( $product ) {
                return $product->to_array();
            }, $this->products ),
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
            'description' => $this->description,
            'count'       => count( $this->products ),
            'products'    => array_map( function( $product ) {
                return $product->to_legacy_array();
            }, $this->products ),
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
}
