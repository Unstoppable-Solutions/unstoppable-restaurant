<?php
/**
 * Menu Renderer.
 *
 * DATA-ONLY architecture:
 * This class ONLY builds structured MenuPayload.
 * It NEVER renders HTML.
 *
 * For HTML output, use MenuHtmlRenderer.
 *
 * Every product carries MenuContext.
 * Every category may have station_hint.
 * No queries in templates.
 *
 * @package Unstoppable_Restaurant
 * @since 2.9.0
 */

namespace Unstoppable\Restaurant\Menu;

use Unstoppable\Restaurant\Menu\Data\MenuPayload;
use Unstoppable\Restaurant\Menu\Data\CategoryPayload;
use Unstoppable\Restaurant\Menu\Data\ProductPayload;
use Unstoppable\Restaurant\Menu\Data\MenuContext;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MenuRenderer class.
 *
 * Builds structured menu payloads. Does NOT render HTML.
 *
 * @since 2.9.0
 */
class MenuRenderer {

    /**
     * Singleton instance.
     *
     * @var MenuRenderer|null
     */
    private static $instance = null;

    /**
     * Category station hints (cached).
     *
     * @var array
     */
    private $station_hints = null;

    /**
     * Get singleton instance.
     *
     * @return MenuRenderer
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Build structured data payload for a menu.
     *
     * This is the core data-building method.
     * NO HTML here - just data structures.
     *
     * @param MenuDefinition $menu    Menu definition.
     * @param string         $channel Channel: web | qr | kiosk | pos
     * @return MenuPayload
     */
    public function build_payload( MenuDefinition $menu, $channel = 'web' ) {
        global $wpdb;

        $start_time = microtime( true );

        // Create payload container.
        $payload = new MenuPayload( array(
            'menu_id'   => $menu->get_id(),
            'menu_slug' => $menu->get_slug(),
            'menu_name' => $menu->get_name(),
            'layout'    => $menu->get_layout(),
            'channel'   => $channel,
        ) );

        // Get category IDs from menu definition.
        $category_ids = $menu->get_category_ids();
        $filters = $menu->get_filters();

        // If no categories specified, get all product categories.
        if ( empty( $category_ids ) ) {
            $all_terms = get_terms( array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'fields'     => 'ids',
            ) );

            if ( is_wp_error( $all_terms ) ) {
                return $payload;
            }

            $category_ids = $all_terms;
        }

        if ( empty( $category_ids ) ) {
            return $payload;
        }

        // Get station hints for categories.
        $station_hints = $this->get_category_station_hints();

        // Get terms.
        $terms = get_terms( array(
            'taxonomy'   => 'product_cat',
            'include'    => $category_ids,
            'hide_empty' => true,
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return $payload;
        }

        // Build term lookup.
        $term_lookup = array();
        foreach ( $terms as $term ) {
            $term_lookup[ $term->term_id ] = $term;
        }

        $term_ids_str = implode( ',', array_map( 'intval', $category_ids ) );

        // Get all products with their categories.
        $results = $wpdb->get_results( "
            SELECT 
                p.ID,
                p.post_title,
                p.post_name,
                p.post_excerpt,
                p.menu_order,
                tt.term_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND tt.taxonomy = 'product_cat'
            AND tt.term_id IN ({$term_ids_str})
            ORDER BY p.menu_order ASC, p.post_title ASC
        " );

        if ( empty( $results ) ) {
            return $payload;
        }

        // Get all product IDs.
        $product_ids = array_unique( wp_list_pluck( $results, 'ID' ) );

        // Batch load post meta.
        update_postmeta_cache( $product_ids );

        // Get thumbnail IDs.
        $thumbnail_ids = array();
        foreach ( $product_ids as $pid ) {
            $thumb = get_post_meta( $pid, '_thumbnail_id', true );
            if ( $thumb ) {
                $thumbnail_ids[ $pid ] = $thumb;
            }
        }

        // Prime attachment cache.
        if ( ! empty( $thumbnail_ids ) ) {
            _prime_post_caches( array_unique( array_values( $thumbnail_ids ) ), false, true );
        }

        // Get modifier assignments.
        $modifier_assignments = $this->get_modifier_assignments( $product_ids );

        // Get product labels.
        $product_labels = $this->get_product_labels( $product_ids );

        // Build products by category.
        $products_by_cat = array();
        $hide_out_of_stock = ! empty( $filters['hide_out_of_stock'] );

        foreach ( $results as $row ) {
            $cat_id = (int) $row->term_id;

            if ( ! isset( $products_by_cat[ $cat_id ] ) ) {
                $products_by_cat[ $cat_id ] = array();
            }

            // Skip duplicates.
            if ( isset( $products_by_cat[ $cat_id ][ $row->ID ] ) ) {
                continue;
            }

            $thumb_id = $thumbnail_ids[ $row->ID ] ?? 0;
            $price = get_post_meta( $row->ID, '_price', true );
            $stock_status = get_post_meta( $row->ID, '_stock_status', true );

            // Skip out of stock if filter is set.
            if ( $hide_out_of_stock && 'outofstock' === $stock_status ) {
                continue;
            }

            // Get term for category name.
            $term = $term_lookup[ $cat_id ] ?? null;
            $category_name = $term ? $term->name : '';
            $category_station = $station_hints[ $cat_id ] ?? null;

            // Build menu context for this product.
            $context = new MenuContext( array(
                'menu_id'       => $menu->get_id(),
                'menu_slug'     => $menu->get_slug(),
                'category_id'   => $cat_id,
                'category_name' => $category_name,
                'station_hint'  => $category_station,
                'channel'       => $channel,
            ) );

            // Build product payload.
            $product_data = array(
                'id'              => (int) $row->ID,
                'name'            => $row->post_title,
                'slug'            => $row->post_name,
                'type'            => 'simple',
                'permalink'       => get_permalink( $row->ID ),
                'description'     => $row->post_excerpt,
                'image_url'       => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
                'image_id'        => $thumb_id,
                'price'           => $price,
                'price_html'      => $price ? wc_price( $price ) : '',
                'on_sale'         => false,
                'in_stock'        => 'outofstock' !== $stock_status,
                'stock_qty'       => null,
                'modifier_groups' => $modifier_assignments[ $row->ID ] ?? array(),
                'labels'          => $product_labels[ $row->ID ] ?? array(),
            );

            $products_by_cat[ $cat_id ][ $row->ID ] = new ProductPayload( $product_data, $context );
        }

        // Build category payloads in menu order.
        foreach ( $category_ids as $cat_id ) {
            if ( empty( $products_by_cat[ $cat_id ] ) ) {
                continue;
            }

            if ( ! isset( $term_lookup[ $cat_id ] ) ) {
                continue;
            }

            $term = $term_lookup[ $cat_id ];

            $category = new CategoryPayload( array(
                'id'           => $term->term_id,
                'name'         => $term->name,
                'slug'         => $term->slug,
                'description'  => $term->description,
                'station_hint' => $station_hints[ $term->term_id ] ?? null,
            ) );

            // Add products.
            foreach ( $products_by_cat[ $cat_id ] as $product ) {
                $category->add_product( $product );
            }

            $payload->add_category( $category );
        }

        // Debug timing.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $elapsed = round( ( microtime( true ) - $start_time ) * 1000, 2 );
            error_log( "URST: Menu payload '{$menu->get_slug()}' built in {$elapsed}ms ({$payload->get_product_count()} products)" );
        }

        /**
         * Filter the complete menu payload.
         *
         * @since 2.9.0
         * @param MenuPayload    $payload Menu payload.
         * @param MenuDefinition $menu    Menu definition.
         * @param string         $channel Channel.
         */
        return apply_filters( 'urst_menu_payload', $payload, $menu, $channel );
    }

    /**
     * Get category station hints.
     *
     * Station hints can be set via:
     * 1. Category meta _urst_station_hint
     * 2. Filter urst_category_station_hints
     *
     * @return array Keyed by term_id.
     */
    private function get_category_station_hints() {
        if ( null !== $this->station_hints ) {
            return $this->station_hints;
        }

        $this->station_hints = array();

        // Get from category meta.
        $terms = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ) );

        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $hint = get_term_meta( $term->term_id, '_urst_station_hint', true );
                if ( $hint ) {
                    $this->station_hints[ $term->term_id ] = $hint;
                }
            }
        }

        /**
         * Filter category station hints.
         *
         * @since 2.9.0
         * @param array $hints Keyed by term_id: ['12' => 'bar', '15' => 'kitchen']
         */
        $this->station_hints = apply_filters( 'urst_category_station_hints', $this->station_hints );

        return $this->station_hints;
    }

    /**
     * Get modifier assignments for products.
     *
     * @param array $product_ids Product IDs.
     * @return array Keyed by product_id: [123 => ['group-1', 'group-2']]
     */
    private function get_modifier_assignments( array $product_ids ) {
        if ( ! class_exists( '\Unstoppable\Restaurant\Modules\Modifiers\ModifierRepository' ) ) {
            return array();
        }

        $assignments = array();

        foreach ( $product_ids as $product_id ) {
            $groups = get_post_meta( $product_id, '_urst_modifier_groups', true );
            if ( is_array( $groups ) && ! empty( $groups ) ) {
                $assignments[ $product_id ] = $groups;
            }
        }

        return $assignments;
    }

    /**
     * Get product labels.
     *
     * @param array $product_ids Product IDs.
     * @return array Keyed by product_id: [123 => ['spicy', 'vegan']]
     */
    private function get_product_labels( array $product_ids ) {
        $labels = array();

        foreach ( $product_ids as $product_id ) {
            $product_labels = get_post_meta( $product_id, '_urst_labels', true );
            if ( is_array( $product_labels ) && ! empty( $product_labels ) ) {
                $labels[ $product_id ] = $product_labels;
            }
        }

        return $labels;
    }

    /**
     * Clear cached data.
     */
    public function clear_cache() {
        $this->station_hints = null;
    }
}
