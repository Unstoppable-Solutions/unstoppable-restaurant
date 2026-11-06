<?php
/**
 * Menu Payload Builder.
 *
 * THE BRAIN of the menu system.
 * Converts MenuDefinition → MenuPayload.
 *
 * This is the AUTHORITATIVE source for menu data.
 * Everything downstream consumes MenuPayload:
 * - MenuHtmlRenderer (frontend)
 * - Live Orders
 * - Printing
 * - REST API
 *
 * @package Unstoppable_Restaurant
 * @since 2.12.0
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
 * MenuPayloadBuilder class.
 *
 * Static builder - no state, just transformation.
 *
 * @since 2.12.0
 */
final class MenuPayloadBuilder {

    /**
     * Build a complete MenuPayload from a MenuDefinition.
     *
     * This is THE entry point for all menu data.
     *
     * @param MenuDefinition $menu    Menu definition.
     * @param string         $channel Channel: web | qr | kiosk | pos
     * @return MenuPayload
     */
    public static function build( MenuDefinition $menu, $channel = 'web' ) {
        global $wpdb;

        $start_time = microtime( true );

        // Get category IDs from menu definition.
        $category_ids = $menu->get_category_ids();

        // If no categories specified, get all product categories.
        if ( empty( $category_ids ) ) {
            $all_terms = get_terms( array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'fields'     => 'ids',
            ) );

            if ( ! is_wp_error( $all_terms ) ) {
                $category_ids = $all_terms;
            }
        }

        // Build the payload arrays.
        $categories_data = array();
        $products_data = array();
        $sections_data = array();

        if ( ! empty( $category_ids ) ) {
            // Get terms.
            $terms = get_terms( array(
                'taxonomy'   => 'product_cat',
                'include'    => $category_ids,
                'hide_empty' => true,
            ) );

            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                // Build term lookup and categories data.
                $term_lookup = array();
                $order = 1;

                foreach ( $category_ids as $cat_id ) {
                    foreach ( $terms as $term ) {
                        if ( $term->term_id === (int) $cat_id ) {
                            $term_lookup[ $term->term_id ] = $term;
                            $categories_data[] = array(
                                'id'          => $term->term_id,
                                'slug'        => $term->slug,
                                'name'        => $term->name,
                                'description' => $term->description,
                                'order'       => $order++,
                            );
                            break;
                        }
                    }
                }

                // Build products data.
                $products_data = self::build_products(
                    $category_ids,
                    $term_lookup,
                    $menu,
                    $channel
                );
            }
        }

        // Phase 7.3: Build sections if present.
        if ( $menu->has_sections() ) {
            $sections_data = self::build_sections( $menu, $products_data, $channel );
        }

        // Get global settings.
        $global_settings = get_option( 'urst_settings', array() );

        // Get menu-specific settings (if any explicitly set).
        $menu_settings = $menu->get_settings();

        // Merge settings: Menu defaults -> Menu specific -> Global
        // Global settings should override menu defaults.
        $merged_settings = array(
            'layout'            => $global_settings['layout'] ?? $menu_settings['layout'] ?? 'classic',
            'show_images'       => isset( $global_settings['show_images'] ) ? $global_settings['show_images'] : ( $menu_settings['show_images'] ?? true ),
            'show_prices'       => isset( $global_settings['show_prices'] ) ? $global_settings['show_prices'] : ( $menu_settings['show_prices'] ?? true ),
            'show_descriptions' => isset( $global_settings['show_descriptions'] ) ? $global_settings['show_descriptions'] : ( $menu_settings['show_descriptions'] ?? true ),
            'columns'           => $global_settings['columns'] ?? $menu_settings['columns'] ?? 3,
        );

        // Build the payload.
        $payload = new MenuPayload( array(
            'menu' => array(
                'id'       => $menu->get_id(),
                'slug'     => $menu->get_slug(),
                'name'     => $menu->get_name(),
                'settings' => $merged_settings,
            ),
            'categories' => $categories_data,
            'sections'   => $sections_data, // Phase 7.3
            'products'   => $products_data,
            'meta'       => array(
                'generated_at'   => current_time( 'c' ),
                'product_count'  => count( $products_data ),
                'category_count' => count( $categories_data ),
                'section_count'  => count( $sections_data ), // Phase 7.3
                'has_sections'   => ! empty( $sections_data ), // Phase 7.3
                'channel'        => $channel,
                'build_time_ms'  => 0, // Filled below.
            ),
        ) );

        // Record build time.
        $elapsed = round( ( microtime( true ) - $start_time ) * 1000, 2 );
        $payload->set_meta( 'build_time_ms', $elapsed );

        // Debug logging.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "URST: MenuPayloadBuilder built '{$menu->get_slug()}' in {$elapsed}ms (" . count( $products_data ) . " products)" );
        }

        /**
         * Filter the complete menu payload.
         *
         * @since 2.12.0
         * @param MenuPayload    $payload Menu payload.
         * @param MenuDefinition $menu    Menu definition.
         * @param string         $channel Channel.
         */
        return apply_filters( 'urst_menu_payload', $payload, $menu, $channel );
    }

    /**
     * Phase 7.3: Build sections array.
     *
     * Sections are presentation-only groupings owned by the menu.
     * Products are referenced by ID and copied into section payload.
     *
     * Phase 7.3.1 Hardening:
     * - Safely ignores deleted/missing products
     * - Supports dedupe to prevent same product in multiple sections
     * - All IDs/labels already sanitized by MenuDefinition
     *
     * @param MenuDefinition $menu          Menu definition.
     * @param array          $all_products  All products from build_products().
     * @param string         $channel       Channel.
     * @return array Sections with nested product data.
     */
    private static function build_sections( MenuDefinition $menu, array $all_products, $channel ) {
        $sections = array();
        $active_sections = $menu->get_active_sections();

        // Phase 7.3.1: Check dedupe setting.
        $dedupe = $menu->should_dedupe_section_products();
        $seen_products = array(); // Track product IDs already used.

        // Build product lookup by ID for fast access.
        $product_lookup = array();
        foreach ( $all_products as $product ) {
            if ( isset( $product['id'] ) ) {
                $product_lookup[ $product['id'] ] = $product;
            }
        }

        foreach ( $active_sections as $section_def ) {
            // ID already sanitized by MenuDefinition::sanitize_sections().
            $section_id = isset( $section_def['id'] ) ? $section_def['id'] : '';
            if ( empty( $section_id ) ) {
                continue;
            }

            // Product IDs already sanitized by MenuDefinition.
            $product_ids = isset( $section_def['product_ids'] ) ? (array) $section_def['product_ids'] : array();
            
            // Build products array for this section.
            $section_products = array();
            foreach ( $product_ids as $pid ) {
                // Phase 7.3.1: Skip if product doesn't exist (deleted).
                if ( ! isset( $product_lookup[ $pid ] ) ) {
                    continue;
                }

                // Phase 7.3.1: Skip if dedupe enabled and already seen.
                if ( $dedupe && isset( $seen_products[ $pid ] ) ) {
                    continue;
                }

                // Clone product and add section context.
                $product = $product_lookup[ $pid ];
                $product['menu_context']['section_id'] = $section_id;
                $product['menu_context']['section_name'] = $section_def['label'] ?? '';
                $section_products[] = $product;

                // Mark as seen for dedupe.
                if ( $dedupe ) {
                    $seen_products[ $pid ] = true;
                }
            }

            // Only include section if it has products.
            if ( ! empty( $section_products ) ) {
                $sections[] = array(
                    'id'          => $section_id,
                    'slug'        => $section_id, // Sections use ID as slug.
                    'label'       => $section_def['label'] ?? '',
                    'description' => $section_def['description'] ?? '',
                    'order'       => $section_def['order'] ?? 0,
                    'products'    => $section_products,
                );
            }
        }

        // Sort by order.
        usort( $sections, function( $a, $b ) {
            return ( $a['order'] ?? 0 ) - ( $b['order'] ?? 0 );
        } );

        return $sections;
    }

    /**
     * Build products array.
     *
     * Uses optimized batch queries for performance.
     *
     * @param array          $category_ids Category term IDs.
     * @param array          $term_lookup  Term objects keyed by ID.
     * @param MenuDefinition $menu         Menu definition.
     * @param string         $channel      Channel.
     * @return array Flat array of product data.
     */
    private static function build_products( array $category_ids, array $term_lookup, MenuDefinition $menu, $channel ) {
        global $wpdb;

        $filters = $menu->get_filters();
        $hide_out_of_stock = ! empty( $filters['hide_out_of_stock'] );

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
            return array();
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
        $modifier_assignments = self::get_modifier_assignments( $product_ids );

        // Get product labels.
        $product_labels = self::get_product_labels( $product_ids );

        // Get station hints.
        $station_hints = self::get_category_station_hints();

        // Build products array (flat, NOT nested in categories).
        $products = array();
        $seen_products = array(); // Track seen product+category combos to avoid duplicates.

        foreach ( $results as $row ) {
            $cat_id = (int) $row->term_id;
            $product_id = (int) $row->ID;

            // Skip duplicates (same product in same category).
            $key = "{$product_id}_{$cat_id}";
            if ( isset( $seen_products[ $key ] ) ) {
                continue;
            }
            $seen_products[ $key ] = true;

            // Get meta.
            $thumb_id = $thumbnail_ids[ $product_id ] ?? 0;
            $price = get_post_meta( $product_id, '_price', true );
            $regular_price = get_post_meta( $product_id, '_regular_price', true );
            $sale_price = get_post_meta( $product_id, '_sale_price', true );
            $stock_status = get_post_meta( $product_id, '_stock_status', true );

            // Skip out of stock if filter is set.
            if ( $hide_out_of_stock && 'outofstock' === $stock_status ) {
                continue;
            }

            // Get term info.
            $term = $term_lookup[ $cat_id ] ?? null;
            $category_name = $term ? $term->name : '';
            $category_station = $station_hints[ $cat_id ] ?? null;

            // Build product entry.
            $products[] = array(
                'id'          => $product_id,
                'name'        => $row->post_title,
                'slug'        => $row->post_name,
                'description' => $row->post_excerpt,
                'price'       => $price ?: '0',
                'price_html'  => $price ? wc_price( $price ) : '',
                'on_sale'     => ! empty( $sale_price ) && $sale_price !== $regular_price,
                'in_stock'    => 'outofstock' !== $stock_status,
                'image'       => $thumb_id 
                    ? wp_get_attachment_image_url( $thumb_id, 'woocommerce_thumbnail' ) 
                    : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
                'image_id'    => $thumb_id,
                'permalink'   => get_permalink( $product_id ),
                'category_id' => $cat_id,
                'menu_order'  => (int) $row->menu_order,

                // Menu context - critical for cart/orders/printing.
                // Phase 7.4: This is the CANONICAL structure.
                'menu_context' => array(
                    'menu_id'       => $menu->get_id(),
                    'menu_slug'     => $menu->get_slug(),
                    'menu_name'     => $menu->get_name(),
                    'category_id'   => $cat_id,
                    'category_name' => $category_name,
                    'station'       => $category_station,
                    'channel'       => $channel,
                ),

                // Modifiers - groups attached to this product.
                'modifiers' => array(
                    'groups' => $modifier_assignments[ $product_id ] ?? array(),
                ),

                // Labels - dietary/allergen info.
                'labels' => $product_labels[ $product_id ] ?? array(),
            );
        }

        return $products;
    }

    /**
     * Get modifier assignments for products.
     *
     * @param array $product_ids Product IDs.
     * @return array Keyed by product_id.
     */
    private static function get_modifier_assignments( array $product_ids ) {
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
     * @return array Keyed by product_id.
     */
    private static function get_product_labels( array $product_ids ) {
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
     * Get category station hints.
     *
     * @return array Keyed by term_id.
     */
    private static function get_category_station_hints() {
        static $hints = null;

        if ( null !== $hints ) {
            return $hints;
        }

        $hints = array();

        $terms = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ) );

        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $hint = get_term_meta( $term->term_id, '_urst_station_hint', true );
                if ( $hint ) {
                    $hints[ $term->term_id ] = $hint;
                }
            }
        }

        /**
         * Filter category station hints.
         *
         * @since 2.12.0
         * @param array $hints Keyed by term_id.
         */
        return apply_filters( 'urst_category_station_hints', $hints );
    }
}
