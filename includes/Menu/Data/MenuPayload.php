<?php
/**
 * Menu Payload.
 *
 * The SINGLE SOURCE OF TRUTH for "what a menu is showing right now."
 *
 * Not HTML. Not Woo products. Not shortcode output.
 * A structured, serializable object.
 *
 * Schema (AUTHORITATIVE):
 * {
 *   menu: { id, slug, name, settings },
 *   categories: [ { id, slug, name, order } ],
 *   products: [ { id, name, price, category_id, menu_context, modifiers, labels } ],
 *   meta: { generated_at, product_count }
 * }
 *
 * Products contain category_id references, NOT nested category objects.
 * This keeps the payload light and cacheable.
 *
 * @package Unstoppable_Restaurant
 * @since 2.12.0
 */

namespace Unstoppable\Restaurant\Menu\Data;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MenuPayload class.
 *
 * @since 2.12.0
 */
final class MenuPayload implements \JsonSerializable, \Countable, \IteratorAggregate {

    /**
     * Menu data.
     *
     * @var array
     */
    private $menu;

    /**
     * Categories array (flat).
     *
     * @var array
     */
    private $categories;

    /**
     * Phase 7.3: Sections array (with nested products).
     *
     * @var array
     */
    private $sections;

    /**
     * Products array (flat).
     *
     * @var array
     */
    private $products;

    /**
     * Metadata.
     *
     * @var array
     */
    private $meta;

    /**
     * Constructor.
     *
     * @param array $data Payload data matching schema.
     */
    public function __construct( array $data = array() ) {
        $this->menu = isset( $data['menu'] ) ? $data['menu'] : array(
            'id'       => null,
            'slug'     => '',
            'name'     => '',
            'settings' => array(),
        );

        $this->categories = isset( $data['categories'] ) ? $data['categories'] : array();
        $this->sections = isset( $data['sections'] ) ? $data['sections'] : array(); // Phase 7.3
        $this->products = isset( $data['products'] ) ? $data['products'] : array();

        $this->meta = array_merge( array(
            'generated_at'   => current_time( 'c' ),
            'product_count'  => count( $this->products ),
            'category_count' => count( $this->categories ),
            'section_count'  => count( $this->sections ), // Phase 7.3
            'has_sections'   => ! empty( $this->sections ), // Phase 7.3
            'channel'        => 'web',
        ), isset( $data['meta'] ) ? $data['meta'] : array() );
    }

    // =========================================================================
    // MENU ACCESSORS
    // =========================================================================

    /**
     * Get menu ID.
     *
     * @return int|null
     */
    public function get_menu_id() {
        return $this->menu['id'] ?? null;
    }

    /**
     * Get menu slug.
     *
     * @return string
     */
    public function get_menu_slug() {
        return $this->menu['slug'] ?? '';
    }

    /**
     * Get menu name.
     *
     * @return string
     */
    public function get_menu_name() {
        return $this->menu['name'] ?? '';
    }

    /**
     * Get menu settings.
     *
     * @return array
     */
    public function get_menu_settings() {
        return $this->menu['settings'] ?? array();
    }

    /**
     * Get a specific menu setting.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get_setting( $key, $default = null ) {
        $settings = $this->get_menu_settings();
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Get layout.
     *
     * @return string
     */
    public function get_layout() {
        return $this->get_setting( 'layout', 'classic' );
    }

    /**
     * Get channel.
     *
     * @return string
     */
    public function get_channel() {
        return $this->meta['channel'] ?? 'web';
    }

    // =========================================================================
    // CATEGORIES
    // =========================================================================

    /**
     * Get all categories.
     *
     * @return array
     */
    public function get_categories() {
        return $this->categories;
    }

    /**
     * Get category by ID.
     *
     * @param int $id Category ID.
     * @return array|null
     */
    public function get_category( $id ) {
        foreach ( $this->categories as $cat ) {
            if ( ( $cat['id'] ?? 0 ) === (int) $id ) {
                return $cat;
            }
        }
        return null;
    }

    /**
     * Check if payload has categories.
     *
     * @return bool
     */
    public function has_categories() {
        return ! empty( $this->categories );
    }

    /**
     * Get category count.
     *
     * @return int
     */
    public function get_category_count() {
        return count( $this->categories );
    }

    // =========================================================================
    // PHASE 7.3: SECTIONS
    // =========================================================================

    /**
     * Get all sections.
     *
     * @return array
     */
    public function get_sections() {
        return $this->sections;
    }

    /**
     * Get section by ID.
     *
     * @param string $id Section ID.
     * @return array|null
     */
    public function get_section( $id ) {
        foreach ( $this->sections as $section ) {
            if ( ( $section['id'] ?? '' ) === $id ) {
                return $section;
            }
        }
        return null;
    }

    /**
     * Check if payload has sections.
     *
     * @return bool
     */
    public function has_sections() {
        return ! empty( $this->sections );
    }

    /**
     * Get section count.
     *
     * @return int
     */
    public function get_section_count() {
        return count( $this->sections );
    }

    /**
     * Get products for a section.
     *
     * @param string $section_id Section ID.
     * @return array
     */
    public function get_products_by_section( $section_id ) {
        $section = $this->get_section( $section_id );
        return $section ? ( $section['products'] ?? array() ) : array();
    }

    // =========================================================================
    // PRODUCTS
    // =========================================================================

    /**
     * Get all products.
     *
     * @return array
     */
    public function get_products() {
        return $this->products;
    }

    /**
     * Get products by category ID.
     *
     * @param int $category_id Category ID.
     * @return array
     */
    public function get_products_by_category( $category_id ) {
        return array_filter( $this->products, function( $product ) use ( $category_id ) {
            return ( $product['category_id'] ?? 0 ) === (int) $category_id;
        } );
    }

    /**
     * Get products grouped by station.
     *
     * @return array Keyed by station: ['kitchen' => [...], 'bar' => [...]]
     */
    public function get_products_by_station() {
        $grouped = array();

        foreach ( $this->products as $product ) {
            $station = $product['menu_context']['station'] ?? 'kitchen';
            if ( empty( $station ) ) {
                $station = 'kitchen';
            }

            if ( ! isset( $grouped[ $station ] ) ) {
                $grouped[ $station ] = array();
            }

            $grouped[ $station ][] = $product;
        }

        return $grouped;
    }

    /**
     * Get product by ID.
     *
     * @param int $id Product ID.
     * @return array|null
     */
    public function get_product( $id ) {
        foreach ( $this->products as $product ) {
            if ( ( $product['id'] ?? 0 ) === (int) $id ) {
                return $product;
            }
        }
        return null;
    }

    /**
     * Get product count.
     *
     * @return int
     */
    public function get_product_count() {
        return count( $this->products );
    }

    /**
     * Check if payload is empty.
     *
     * @return bool
     */
    public function is_empty() {
        return empty( $this->products );
    }

    // =========================================================================
    // METADATA
    // =========================================================================

    /**
     * Get all metadata.
     *
     * @return array
     */
    public function get_meta() {
        return $this->meta;
    }

    /**
     * Get a specific meta value.
     *
     * @param string $key     Meta key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get_meta_value( $key, $default = null ) {
        return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : $default;
    }

    /**
     * Set a meta value.
     *
     * @param string $key   Meta key.
     * @param mixed  $value Value.
     */
    public function set_meta( $key, $value ) {
        $this->meta[ $key ] = $value;
    }

    /**
     * Get generation timestamp.
     *
     * @return string
     */
    public function get_generated_at() {
        return $this->meta['generated_at'] ?? '';
    }

    // =========================================================================
    // ITERATORS & COUNTABLE
    // =========================================================================

    /**
     * Count products (Countable interface).
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count() {
        return count( $this->products );
    }

    /**
     * Get iterator over products (IteratorAggregate interface).
     *
     * @return \ArrayIterator
     */
    #[\ReturnTypeWillChange]
    public function getIterator() {
        return new \ArrayIterator( $this->products );
    }

    // =========================================================================
    // SERIALIZATION
    // =========================================================================

    /**
     * Convert to array.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'menu'       => $this->menu,
            'categories' => $this->categories,
            'sections'   => $this->sections, // Phase 7.3
            'products'   => $this->products,
            'meta'       => $this->meta,
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

    // =========================================================================
    // LEGACY COMPATIBILITY (for existing templates)
    // =========================================================================

    /**
     * Convert to legacy nested format for backward compatibility.
     *
     * Returns categories with nested products array,
     * as expected by existing templates.
     *
     * @return array
     */
    public function to_legacy_categories() {
        $legacy = array();

        foreach ( $this->categories as $cat ) {
            $cat_id = $cat['id'] ?? 0;

            $cat_products = array();
            foreach ( $this->products as $product ) {
                if ( ( $product['category_id'] ?? 0 ) === $cat_id ) {
                    // Map to legacy product format.
                    $cat_products[] = array(
                        'id'           => $product['id'] ?? 0,
                        'name'         => $product['name'] ?? '',
                        'slug'         => $product['slug'] ?? '',
                        'type'         => 'simple',
                        'permalink'    => $product['permalink'] ?? '',
                        'description'  => $product['description'] ?? '',
                        'image_url'    => $product['image'] ?? '',
                        'image_id'     => $product['image_id'] ?? 0,
                        'price'        => $product['price'] ?? '',
                        'price_html'   => $product['price_html'] ?? '',
                        'on_sale'      => $product['on_sale'] ?? false,
                        'in_stock'     => $product['in_stock'] ?? true,
                        'stock_qty'    => null,
                        'is_variable'  => false,
                        'min_price'    => '',
                        'max_price'    => '',
                        'menu_context' => $product['menu_context'] ?? array(),
                    );
                }
            }

            $legacy[] = array(
                'id'          => $cat_id,
                'name'        => $cat['name'] ?? '',
                'slug'        => $cat['slug'] ?? '',
                'description' => $cat['description'] ?? '',
                'products'    => $cat_products,
            );
        }

        return $legacy;
    }

    /**
     * Phase 7.3: Convert sections to legacy nested format for templates.
     *
     * Returns sections with nested products array,
     * similar to to_legacy_categories but for sections.
     *
     * @return array
     */
    public function to_legacy_sections() {
        $legacy = array();

        foreach ( $this->sections as $section ) {
            $section_products = array();
            
            foreach ( $section['products'] ?? array() as $product ) {
                // Map to legacy product format.
                $section_products[] = array(
                    'id'           => $product['id'] ?? 0,
                    'name'         => $product['name'] ?? '',
                    'slug'         => $product['slug'] ?? '',
                    'type'         => 'simple',
                    'permalink'    => $product['permalink'] ?? '',
                    'description'  => $product['description'] ?? '',
                    'image_url'    => $product['image'] ?? '',
                    'image_id'     => $product['image_id'] ?? 0,
                    'price'        => $product['price'] ?? '',
                    'price_html'   => $product['price_html'] ?? '',
                    'on_sale'      => $product['on_sale'] ?? false,
                    'in_stock'     => $product['in_stock'] ?? true,
                    'stock_qty'    => null,
                    'is_variable'  => false,
                    'min_price'    => '',
                    'max_price'    => '',
                    'menu_context' => $product['menu_context'] ?? array(),
                );
            }

            $legacy[] = array(
                'id'          => $section['id'] ?? '',
                'name'        => $section['label'] ?? '',
                'slug'        => $section['slug'] ?? $section['id'] ?? '',
                'description' => $section['description'] ?? '',
                'products'    => $section_products,
            );
        }

        return $legacy;
    }

    /**
     * Phase 7.3: Get display groups - sections if present, otherwise categories.
     *
     * This is what templates should use when they want to render
     * grouped products regardless of whether sections are defined.
     *
     * @return array Array of groups with 'id', 'name', 'slug', 'description', 'products'.
     */
    public function get_display_groups() {
        if ( $this->has_sections() ) {
            return $this->to_legacy_sections();
        }
        return $this->to_legacy_categories();
    }
}
