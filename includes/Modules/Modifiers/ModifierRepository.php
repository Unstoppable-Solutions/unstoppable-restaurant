<?php
/**
 * Modifier Repository.
 *
 * Handles storage and retrieval of modifier flows from the database.
 * Uses WordPress post meta for persistence.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Modules\Modifiers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ModifierRepository class.
 *
 * @since 2.3.0
 */
class ModifierRepository {

    /**
     * Post type for modifier flows.
     */
    const POST_TYPE = 'ur_modifier_flow';

    /**
     * Meta keys.
     */
    const META_GROUPS             = '_urst_modifier_groups';
    const META_ASSIGNED_PRODUCTS  = '_urst_assigned_products';
    const META_ASSIGNED_CATEGORIES = '_urst_assigned_categories';

    /**
     * Cache group for object cache.
     */
    const CACHE_GROUP = 'urst_modifiers';

    /**
     * Cache for product flows (in-memory for current request).
     *
     * @var array
     */
    private static $product_cache = array();

    /**
     * Cache for flow definitions (in-memory for current request).
     *
     * @var array
     */
    private static $flow_cache = array();

    /**
     * Get modifier flow for a product.
     *
     * Checks direct product assignment first, then category assignment.
     * Uses both in-memory and object caching.
     *
     * @param int $product_id Product ID.
     * @return array|null Flow data with groups or null if none found.
     */
    public static function get_flow_for_product( $product_id ) {
        $product_id = absint( $product_id );

        // Check in-memory cache.
        if ( isset( self::$product_cache[ $product_id ] ) ) {
            return self::$product_cache[ $product_id ];
        }

        // Check object cache.
        $cache_key = 'product_flow_' . $product_id;
        $cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            self::$product_cache[ $product_id ] = $cached;
            return $cached ?: null; // Convert empty string to null.
        }

        // Check direct product assignment.
        $flow = self::get_flow_by_product( $product_id );

        // Check category assignment.
        if ( ! $flow ) {
            $product = wc_get_product( $product_id );
            if ( $product ) {
                $category_ids = $product->get_category_ids();
                foreach ( $category_ids as $category_id ) {
                    $flow = self::get_flow_by_category( $category_id );
                    if ( $flow ) {
                        break;
                    }
                }
            }
        }

        // Cache result (use empty string for null to differentiate from cache miss).
        self::$product_cache[ $product_id ] = $flow;
        wp_cache_set( $cache_key, $flow ?: '', self::CACHE_GROUP, HOUR_IN_SECONDS );

        return $flow;
    }

    /**
     * Get flow assigned directly to a product.
     *
     * @param int $product_id Product ID.
     * @return array|null
     */
    public static function get_flow_by_product( $product_id ) {
        $product_id = absint( $product_id );
        
        // Query all published modifier flows.
        $flows = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ) );

        if ( empty( $flows ) ) {
            return null;
        }

        // Check each flow for the product assignment.
        foreach ( $flows as $flow_id ) {
            $assigned_products = get_post_meta( $flow_id, self::META_ASSIGNED_PRODUCTS, true );
            
            if ( is_array( $assigned_products ) && in_array( $product_id, $assigned_products, false ) ) {
                return self::build_flow_data( $flow_id );
            }
        }

        return null;
    }

    /**
     * Get flow assigned to a category.
     *
     * @param int $category_id Category ID.
     * @return array|null
     */
    public static function get_flow_by_category( $category_id ) {
        $category_id = absint( $category_id );
        
        // Query all published modifier flows.
        $flows = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ) );

        if ( empty( $flows ) ) {
            return null;
        }

        // Check each flow for the category assignment.
        foreach ( $flows as $flow_id ) {
            $assigned_categories = get_post_meta( $flow_id, self::META_ASSIGNED_CATEGORIES, true );
            
            if ( is_array( $assigned_categories ) && in_array( $category_id, $assigned_categories, false ) ) {
                return self::build_flow_data( $flow_id );
            }
        }

        return null;
    }

    /**
     * Get flow by post ID.
     *
     * @param int $flow_id Flow post ID.
     * @return array|null
     */
    public static function get_flow( $flow_id ) {
        $post = get_post( $flow_id );

        if ( ! $post || self::POST_TYPE !== $post->post_type ) {
            return null;
        }

        return self::build_flow_data( $flow_id );
    }

    /**
     * Build flow data from post ID.
     *
     * @param int $flow_id Flow post ID.
     * @return array|null
     */
    private static function build_flow_data( $flow_id ) {
        $post = get_post( $flow_id );
        if ( ! $post ) {
            return null;
        }

        $groups_data = get_post_meta( $flow_id, self::META_GROUPS, true );

        if ( empty( $groups_data ) || ! is_array( $groups_data ) ) {
            return null;
        }

        // Sort groups by sort_order.
        usort( $groups_data, function( $a, $b ) {
            $a_order = isset( $a['sort_order'] ) ? $a['sort_order'] : 0;
            $b_order = isset( $b['sort_order'] ) ? $b['sort_order'] : 0;
            return $a_order - $b_order;
        } );

        return array(
            'id'     => $flow_id,
            'name'   => $post->post_title,
            'groups' => $groups_data,
        );
    }

    /**
     * Save a modifier flow.
     *
     * @param array $data Flow data.
     * @return int|false Post ID on success, false on failure.
     */
    public static function save_flow( array $data ) {
        $flow_id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
        $name    = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        $groups  = isset( $data['groups'] ) ? $data['groups'] : array();

        // Sanitize groups.
        $sanitized_groups = self::sanitize_groups( $groups );

        $post_data = array(
            'post_type'   => self::POST_TYPE,
            'post_title'  => $name,
            'post_status' => 'publish',
        );

        if ( $flow_id > 0 ) {
            $post_data['ID'] = $flow_id;
            $result = wp_update_post( $post_data );
        } else {
            $result = wp_insert_post( $post_data );
        }

        if ( is_wp_error( $result ) || ! $result ) {
            return false;
        }

        $flow_id = $result;

        // Save groups.
        update_post_meta( $flow_id, self::META_GROUPS, $sanitized_groups );

        // Save assignments.
        if ( isset( $data['products'] ) ) {
            update_post_meta( $flow_id, self::META_ASSIGNED_PRODUCTS, array_map( 'absint', (array) $data['products'] ) );
        }

        if ( isset( $data['categories'] ) ) {
            update_post_meta( $flow_id, self::META_ASSIGNED_CATEGORIES, array_map( 'absint', (array) $data['categories'] ) );
        }

        // Clear cache.
        self::$cache = array();

        return $flow_id;
    }

    /**
     * Sanitize groups data.
     *
     * @param array $groups Raw groups data.
     * @return array Sanitized groups.
     */
    private static function sanitize_groups( array $groups ) {
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
                        'is_default'    => ! empty( $option['is_default'] ) || ! empty( $option['default'] ),
                        'image_url'     => isset( $option['image_url'] ) ? esc_url_raw( $option['image_url'] ) : '',
                        'sort_order'    => isset( $option['sort_order'] ) ? absint( $option['sort_order'] ) : $opt_index,
                    );
                }
            }

            $sanitized[] = $sanitized_group;
        }

        return $sanitized;
    }

    /**
     * Delete a flow.
     *
     * @param int $flow_id Flow post ID.
     * @return bool
     */
    public static function delete_flow( $flow_id ) {
        $result = wp_delete_post( $flow_id, true );
        self::$cache = array();
        return (bool) $result;
    }

    /**
     * Get all flows.
     *
     * @return array Array of flow summaries.
     */
    public static function get_all_flows() {
        $posts = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $flows = array();

        foreach ( $posts as $post ) {
            $groups = get_post_meta( $post->ID, self::META_GROUPS, true );
            $products = get_post_meta( $post->ID, self::META_ASSIGNED_PRODUCTS, true );
            $categories = get_post_meta( $post->ID, self::META_ASSIGNED_CATEGORIES, true );

            $flows[] = array(
                'id'          => $post->ID,
                'name'        => $post->post_title,
                'group_count' => is_array( $groups ) ? count( $groups ) : 0,
                'products'    => is_array( $products ) ? $products : array(),
                'categories'  => is_array( $categories ) ? $categories : array(),
            );
        }

        return $flows;
    }

    /**
     * Check if a product has a modifier flow.
     *
     * @param int $product_id Product ID.
     * @return bool
     */
    public static function product_has_flow( $product_id ) {
        $flow = self::get_flow_for_product( $product_id );
        return ! empty( $flow ) && ! empty( $flow['groups'] );
    }

    /**
     * Clear the cache.
     *
     * Clears both in-memory and object caches.
     */
    public static function clear_cache() {
        self::$product_cache = array();
        self::$flow_cache = array();

        // Clear object cache group if supported.
        wp_cache_flush_group( self::CACHE_GROUP );
    }
}
