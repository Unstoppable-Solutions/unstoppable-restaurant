<?php
/**
 * Product Query.
 *
 * Centralized product query layer. All product queries in layouts
 * must go through this class.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ProductQuery class.
 *
 * @since 1.0.0
 */
class ProductQuery {

    /**
     * Initialize cache invalidation hooks.
     *
     * @since 2.5.3
     */
    public static function init_cache_hooks() {
        // Invalidate on product changes.
        add_action( 'woocommerce_update_product', array( __CLASS__, 'clear_cache' ) );
        add_action( 'woocommerce_new_product', array( __CLASS__, 'clear_cache' ) );
        add_action( 'before_delete_post', array( __CLASS__, 'clear_cache' ) );
        
        // Invalidate on category changes.
        add_action( 'edited_product_cat', array( __CLASS__, 'clear_cache' ) );
        add_action( 'created_product_cat', array( __CLASS__, 'clear_cache' ) );
        add_action( 'delete_product_cat', array( __CLASS__, 'clear_cache' ) );
    }

    /**
     * Clear product query cache.
     *
     * @since 2.5.3
     */
    public static function clear_cache() {
        delete_transient( 'urst_menu_v2' );
    }

    /**
     * Get products with optional filtering.
     *
     * @param array $args {
     *     Query arguments.
     *
     *     @type array  $category_ids Array of category term IDs. Empty for all.
     *     @type int    $limit        Number of products. -1 for all.
     *     @type string $orderby      Order by field. Default 'menu_order'.
     *     @type string $order        Order direction. Default 'ASC'.
     *     @type bool   $in_stock     Only show in-stock products. Default false.
     * }
     * @return array Array of formatted product data.
     */
    public static function get_products( array $args = array() ) {
        $defaults = array(
            'category_ids' => array(),
            'limit'        => -1,
            'orderby'      => 'menu_order',
            'order'        => 'ASC',
            'in_stock'     => false,
        );

        $args = wp_parse_args( $args, $defaults );

        // Build WooCommerce query args.
        $query_args = array(
            'status'  => 'publish',
            'limit'   => intval( $args['limit'] ),
            'orderby' => sanitize_key( $args['orderby'] ),
            'order'   => in_array( strtoupper( $args['order'] ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $args['order'] ) : 'ASC',
        );

        // Add category filter.
        if ( ! empty( $args['category_ids'] ) ) {
            $slugs = array();
            foreach ( (array) $args['category_ids'] as $cat_id ) {
                $term = get_term( absint( $cat_id ), 'product_cat' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $slugs[] = $term->slug;
                }
            }
            if ( ! empty( $slugs ) ) {
                $query_args['category'] = $slugs;
            }
        }

        // Stock filter.
        if ( $args['in_stock'] ) {
            $query_args['stock_status'] = 'instock';
        }

        /**
         * Filter product query args before execution.
         *
         * @since 1.0.0
         * @param array $query_args WooCommerce product query args.
         * @param array $args       Original arguments passed to get_products.
         */
        $query_args = apply_filters( 'urst_product_query_args', $query_args, $args );

        $products = wc_get_products( $query_args );

        $formatted = array();
        foreach ( $products as $product ) {
            $formatted[] = self::format_product( $product );
        }

        return $formatted;
    }

    /**
     * Get products grouped by category.
     *
     * @param array $category_ids Specific category IDs. Empty for all categories.
     * @return array Array of categories with their products.
     */
    public static function get_products_by_category( array $category_ids = array() ) {
        // Cache key includes category filter.
        $cache_key = 'urst_menu_v2';
        if ( ! empty( $category_ids ) ) {
            sort( $category_ids );
            $cache_key .= '_' . md5( implode( ',', $category_ids ) );
        }
        
        // Check transient.
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        global $wpdb;
        
        $categories = array();

        // Get categories with products.
        $term_args = array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
        );
        
        // Filter to specific categories if provided.
        if ( ! empty( $category_ids ) ) {
            $term_args['include'] = $category_ids;
        }
        
        $terms = get_terms( $term_args );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return array();
        }
        
        // Get term IDs for SQL filter.
        $term_ids = wp_list_pluck( $terms, 'term_id' );
        $term_ids_str = implode( ',', array_map( 'intval', $term_ids ) );

        // Get ALL published products with their categories in ONE query.
        $results = $wpdb->get_results( "
            SELECT 
                p.ID,
                p.post_title,
                p.post_name,
                p.post_excerpt,
                p.menu_order,
                tr.term_taxonomy_id,
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
            return array();
        }

        // Get all product IDs.
        $product_ids = array_unique( wp_list_pluck( $results, 'ID' ) );
        
        // Batch get all post meta we need.
        update_postmeta_cache( $product_ids );
        
        // Get all thumbnails in one query.
        $thumbnail_ids = array();
        foreach ( $product_ids as $pid ) {
            $thumb = get_post_meta( $pid, '_thumbnail_id', true );
            if ( $thumb ) {
                $thumbnail_ids[ $pid ] = $thumb;
            }
        }
        
        // Pre-cache attachment URLs.
        if ( ! empty( $thumbnail_ids ) ) {
            $attachment_ids = array_unique( array_values( $thumbnail_ids ) );
            // Prime the cache.
            _prime_post_caches( $attachment_ids, false, true );
        }

        // Build products by category.
        $products_by_cat = array();
        foreach ( $results as $row ) {
            $cat_id = (int) $row->term_id;
            if ( ! isset( $products_by_cat[ $cat_id ] ) ) {
                $products_by_cat[ $cat_id ] = array();
            }
            
            // Avoid duplicates.
            if ( isset( $products_by_cat[ $cat_id ][ $row->ID ] ) ) {
                continue;
            }
            
            $thumb_id = $thumbnail_ids[ $row->ID ] ?? 0;
            $price = get_post_meta( $row->ID, '_price', true );
            $stock = get_post_meta( $row->ID, '_stock_status', true );
            
            $products_by_cat[ $cat_id ][ $row->ID ] = array(
                'id'          => (int) $row->ID,
                'name'        => $row->post_title,
                'slug'        => $row->post_name,
                'type'        => 'simple',
                'permalink'   => get_permalink( $row->ID ),
                'description' => $row->post_excerpt,
                'image_url'   => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
                'image_id'    => $thumb_id,
                'price'       => $price,
                'price_html'  => $price ? wc_price( $price ) : '',
                'on_sale'     => false,
                'in_stock'    => $stock !== 'outofstock',
                'stock_qty'   => null,
                'is_variable' => false,
                'min_price'   => '',
                'max_price'   => '',
            );
        }

        // Build final categories array.
        foreach ( $terms as $term ) {
            if ( empty( $products_by_cat[ $term->term_id ] ) ) {
                continue;
            }

            $categories[] = array(
                'id'          => $term->term_id,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'description' => $term->description,
                'count'       => count( $products_by_cat[ $term->term_id ] ),
                'products'    => array_values( $products_by_cat[ $term->term_id ] ),
            );
        }

        // Cache for 10 minutes.
        set_transient( $cache_key, $categories, 10 * MINUTE_IN_SECONDS );

        return apply_filters( 'urst_categories_with_products', $categories );
    }

    /**
     * Get a single product by ID.
     *
     * @param int $product_id Product ID.
     * @return array|null Formatted product data or null.
     */
    public static function get_product( $product_id ) {
        $product = wc_get_product( absint( $product_id ) );

        if ( ! $product ) {
            return null;
        }

        return self::format_product( $product );
    }

    /**
     * Format a WC_Product object into a clean data array.
     *
     * This is the ONLY place where product data formatting happens.
     * Templates receive this standardized array, never raw WC_Product objects.
     *
     * @param \WC_Product $product Product object.
     * @return array Formatted product data.
     */
    public static function format_product( $product ) {
        $image_id = $product->get_image_id();

        $data = array(
            // Core data.
            'id'          => $product->get_id(),
            'name'        => $product->get_name(),
            'slug'        => $product->get_slug(),
            'type'        => $product->get_type(),
            'permalink'   => $product->get_permalink(),

            // Display content.
            'description' => $product->get_short_description(),
            'image_url'   => $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '',
            'image_id'    => $image_id,

            // Pricing.
            'price'       => $product->get_price(),
            'price_html'  => $product->get_price_html(),
            'on_sale'     => $product->is_on_sale(),

            // Stock.
            'in_stock'    => $product->is_in_stock(),
            'stock_qty'   => $product->get_stock_quantity(),

            // For variable products.
            'is_variable' => $product->is_type( 'variable' ),
            'min_price'   => '',
            'max_price'   => '',
        );

        // Add variable product price range.
        if ( $product->is_type( 'variable' ) ) {
            $data['min_price'] = $product->get_variation_price( 'min' );
            $data['max_price'] = $product->get_variation_price( 'max' );
        }

        /**
         * Filter formatted product data.
         *
         * @since 1.0.0
         * @param array       $data    Formatted product data.
         * @param \WC_Product $product WooCommerce product object.
         */
        return apply_filters( 'urst_formatted_product', $data, $product );
    }

    /**
     * Get category data by ID.
     *
     * @param int $category_id Category term ID.
     * @return array|null Category data or null.
     */
    public static function get_category( $category_id ) {
        $term = get_term( absint( $category_id ), 'product_cat' );

        if ( ! $term || is_wp_error( $term ) ) {
            return null;
        }

        return array(
            'id'          => $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
            'count'       => $term->count,
        );
    }

    /**
     * Get all product categories.
     *
     * @param bool $hide_empty Only return categories with products.
     * @return array Array of category data.
     */
    public static function get_categories( $hide_empty = true ) {
        $terms = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => $hide_empty,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return array();
        }

        $categories = array();

        foreach ( $terms as $term ) {
            $categories[] = array(
                'id'          => $term->term_id,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'description' => $term->description,
                'count'       => $term->count,
            );
        }

        return $categories;
    }
}
