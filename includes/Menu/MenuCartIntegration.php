<?php
/**
 * Menu Cart Integration.
 *
 * PHASE 7.4 AUTHORITATIVE - Menu Context Enforcement.
 *
 * This class is THE SPINE of Unstoppable Restaurant.
 * It ensures menu context flows: menu → cart → order → printing/live orders.
 *
 * RULES (NON-NEGOTIABLE):
 * 1. Context is added ONCE at add-to-cart
 * 2. Context is IMMUTABLE after add-to-cart
 * 3. Context survives page reload, AJAX, quantity changes
 * 4. Context is copied VERBATIM to order item meta
 * 5. Order-level summary meta is created for printing/Live Orders
 * 6. If context is missing, attach default (silent fallback)
 *
 * @package Unstoppable_Restaurant
 * @since 2.9.0
 */

namespace Unstoppable\Restaurant\Menu;

use Unstoppable\Restaurant\Menu\Data\MenuContext;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MenuCartIntegration class.
 *
 * @since 2.9.0
 */
class MenuCartIntegration {

    /**
     * Singleton instance.
     *
     * @var MenuCartIntegration|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return MenuCartIntegration
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize hooks.
     */
    public function init() {
        // === CART INGRESS (7.4.1) ===
        // Add context to cart item data from POST.
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_menu_context_to_cart' ), 10, 3 );

        // === CART PERSISTENCE (7.4.2) ===
        // Restore context from session (page reload, etc).
        add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'restore_cart_item_context' ), 10, 3 );

        // === ORDER CREATION (7.4.3) ===
        // Transfer context to order item meta.
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_context_to_order_item' ), 10, 4 );
        
        // Create order-level summary meta.
        add_action( 'woocommerce_checkout_create_order', array( $this, 'create_order_summary' ), 10, 2 );

        // Also handle programmatic order creation (WC_Order::add_product).
        add_action( 'woocommerce_new_order_item', array( $this, 'ensure_order_item_context' ), 10, 3 );

        // === ADMIN DISPLAY ===
        // Display context in order admin (for debugging/verification).
        add_action( 'woocommerce_before_order_itemmeta', array( $this, 'display_context_in_admin' ), 10, 3 );
        
        // Add order context metabox.
        add_action( 'add_meta_boxes', array( $this, 'add_order_context_metabox' ) );
    }

    /**
     * Add menu context to cart item data (7.4.1 Cart Ingress).
     *
     * @param array $cart_item_data Cart item data.
     * @param int   $product_id     Product ID.
     * @param int   $variation_id   Variation ID.
     * @return array
     */
    public function add_menu_context_to_cart( $cart_item_data, $product_id, $variation_id ) {
        // Already has context? Don't override (immutability).
        if ( isset( $cart_item_data['urst_context'] ) ) {
            return $cart_item_data;
        }

        $context = null;

        // Try JSON context object first.
        $raw_context = null;
        if ( isset( $_POST['menu_context'] ) ) {
            $raw_context = $_POST['menu_context'];
        } elseif ( isset( $_POST['urst_menu_context'] ) ) {
            $raw_context = $_POST['urst_menu_context'];
        }

        if ( $raw_context ) {
            if ( is_string( $raw_context ) ) {
                $raw_context = json_decode( stripslashes( $raw_context ), true );
            }
            if ( is_array( $raw_context ) ) {
                $context = new MenuContext( $raw_context );
            }
        }

        // Fallback: try individual POST fields.
        if ( ! $context ) {
            $context_data = array();

            // Phase 7.4: Map POST fields to canonical context keys.
            $fields = array(
                'urst_menu_id'       => 'menu_id',
                'urst_menu_slug'     => 'menu_slug',
                'urst_menu_name'     => 'menu_name',
                'urst_category_id'   => 'category_id',
                'urst_category_name' => 'category_name',
                'urst_section_id'    => 'section_id',
                'urst_station'       => 'station',
                'urst_station_hint'  => 'station', // backward compat
                'urst_channel'       => 'channel',
            );

            foreach ( $fields as $post_key => $context_key ) {
                // Skip if already set (station vs station_hint)
                if ( isset( $context_data[ $context_key ] ) ) {
                    continue;
                }
                if ( isset( $_POST[ $post_key ] ) && '' !== $_POST[ $post_key ] ) {
                    $context_data[ $context_key ] = sanitize_text_field( $_POST[ $post_key ] );
                }
            }

            if ( ! empty( $context_data ) ) {
                $context = new MenuContext( $context_data );
            }
        }

        // FALLBACK: If no context provided, create default.
        // This ensures every cart item has context (silent fallback).
        if ( ! $context ) {
            $channel = isset( $_POST['urst_channel'] ) ? sanitize_key( $_POST['urst_channel'] ) : 'web';
            $context = MenuContext::create_default( $channel );
            
            // Log for debugging.
            if ( defined( 'URST_DEBUG_CONTEXT' ) && URST_DEBUG_CONTEXT ) {
                error_log( sprintf(
                    'URST Context: Created default for product %d (no context in POST)',
                    $product_id
                ) );
            }
        } else {
            // Log capture.
            if ( defined( 'URST_DEBUG_CONTEXT' ) && URST_DEBUG_CONTEXT ) {
                error_log( sprintf(
                    'URST Context: Captured for product %d - menu=%s, channel=%s',
                    $product_id,
                    $context->get_menu_slug(),
                    $context->get_channel()
                ) );
            }
        }

        // Store as array (not object) for serialization.
        // Use canonical key 'urst_context' (7.4.1 Single Source of Truth).
        $cart_item_data['urst_context'] = $context->to_array();

        return $cart_item_data;
    }

    /**
     * Restore menu context from session (7.4.2 Cart Persistence).
     *
     * @param array  $cart_item      Cart item.
     * @param array  $values         Stored values.
     * @param string $cart_item_key  Cart item key.
     * @return array
     */
    public function restore_cart_item_context( $cart_item, $values, $cart_item_key ) {
        // Context is stored in session, restore it.
        if ( isset( $values['urst_context'] ) ) {
            $cart_item['urst_context'] = $values['urst_context'];
        }
        // Legacy key support.
        elseif ( isset( $values['urst_menu_context'] ) ) {
            $cart_item['urst_context'] = $values['urst_menu_context'];
        }

        return $cart_item;
    }

    /**
     * Save menu context to order item meta (7.4.3 Order Creation).
     *
     * @param \WC_Order_Item_Product $item          Order item.
     * @param string                 $cart_item_key Cart item key.
     * @param array                  $values        Cart item values.
     * @param \WC_Order              $order         Order.
     */
    public function save_context_to_order_item( $item, $cart_item_key, $values, $order ) {
        $context = null;
        
        if ( isset( $values['urst_context'] ) ) {
            $context = $values['urst_context'];
        } elseif ( isset( $values['urst_menu_context'] ) ) {
            $context = $values['urst_menu_context'];
        }
        
        if ( $context ) {
            // Store context as order item meta.
            $item->add_meta_data( '_urst_context', $context, true );
            
            // Log transfer.
            if ( defined( 'URST_DEBUG_CONTEXT' ) && URST_DEBUG_CONTEXT ) {
                error_log( sprintf(
                    'URST Context: Copied to order item %d - menu=%s',
                    $item->get_id(),
                    $context['menu_slug'] ?? 'unknown'
                ) );
            }
        }
    }

    /**
     * Create order-level menu summary (7.4.3 Order Creation).
     *
     * This meta is what printing and Live Orders will read.
     *
     * @param \WC_Order $order     Order.
     * @param array     $data      Checkout data.
     */
    public function create_order_summary( $order, $data ) {
        $cart = WC()->cart;
        if ( ! $cart ) {
            return;
        }

        $menus_used = array();
        $channels = array();
        $stations = array();

        foreach ( $cart->get_cart() as $cart_item ) {
            $context = self::get_context_from_cart_item( $cart_item );
            if ( $context ) {
                $slug = $context->get_menu_slug();
                if ( $slug && ! in_array( $slug, $menus_used, true ) ) {
                    $menus_used[] = $slug;
                }

                $channel = $context->get_channel();
                if ( $channel && ! in_array( $channel, $channels, true ) ) {
                    $channels[] = $channel;
                }

                $station = $context->get_station_hint();
                if ( $station && ! in_array( $station, $stations, true ) ) {
                    $stations[] = $station;
                }
            }
        }

        // Store order-level summary (canonical schema).
        $summary = array(
            'menus_used' => $menus_used,
            'channel'    => count( $channels ) === 1 ? $channels[0] : 'mixed',
            'channels'   => $channels,
            'stations'   => $stations,
            'version'    => '1.0',
        );

        $order->update_meta_data( '_urst_order_context', $summary );
        
        // Log summary creation.
        if ( defined( 'URST_DEBUG_CONTEXT' ) && URST_DEBUG_CONTEXT ) {
            error_log( sprintf(
                'URST Context: Order %d summary - menus=%s, channel=%s',
                $order->get_id(),
                implode( ',', $menus_used ),
                $summary['channel']
            ) );
        }
    }

    /**
     * Ensure order items created programmatically have context.
     *
     * @param int            $item_id   Item ID.
     * @param \WC_Order_Item $item      Order item.
     * @param int            $order_id  Order ID.
     */
    public function ensure_order_item_context( $item_id, $item, $order_id ) {
        // Only for product items.
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            return;
        }

        // Already has context? Skip.
        $existing = $item->get_meta( '_urst_context', true );
        if ( ! empty( $existing ) ) {
            return;
        }

        // Create default context for programmatic orders.
        $context = MenuContext::create_default( 'pos' ); // Assume POS for programmatic.
        $item->add_meta_data( '_urst_context', $context->to_array(), true );
        $item->save_meta_data();
        
        // Log fallback.
        if ( defined( 'URST_DEBUG_CONTEXT' ) && URST_DEBUG_CONTEXT ) {
            error_log( sprintf(
                'URST Context: Fallback created for programmatic order item %d',
                $item_id
            ) );
        }
    }

    /**
     * Display menu context in order admin.
     *
     * @param int                    $item_id Item ID.
     * @param \WC_Order_Item_Product $item    Order item.
     * @param \WC_Product            $product Product.
     */
    public function display_context_in_admin( $item_id, $item, $product ) {
        // Only for line items.
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            return;
        }

        $context = MenuContext::from_order_item( $item );

        if ( ! $context ) {
            return;
        }

        ?>
        <div class="urst-order-item-context" style="margin-top: 8px; padding: 8px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 4px; font-size: 12px;">
            <strong style="color: #0369a1;">📍 Menu Context:</strong>
            <span style="color: #334155;">
                <?php echo esc_html( $context->get_menu_slug() ?: 'default' ); ?>
                <?php if ( $context->get_category_name() ) : ?>
                    → <?php echo esc_html( $context->get_category_name() ); ?>
                <?php endif; ?>
                <?php if ( $context->has_section() ) : ?>
                    <span style="background: #dbeafe; padding: 1px 6px; border-radius: 3px; margin-left: 4px;">
                        §<?php echo esc_html( $context->get_section_id() ); ?>
                    </span>
                <?php endif; ?>
                <?php if ( $context->has_station_hint() ) : ?>
                    <span style="background: #fef3c7; padding: 1px 6px; border-radius: 3px; margin-left: 4px;">
                        <?php echo esc_html( strtoupper( $context->get_station_hint() ) ); ?>
                    </span>
                <?php endif; ?>
                <span style="background: #e0e7ff; padding: 1px 6px; border-radius: 3px; margin-left: 4px;">
                    <?php echo esc_html( strtoupper( $context->get_channel() ) ); ?>
                </span>
            </span>
        </div>
        <?php
    }
    
    /**
     * Add order context metabox (7.4.7 Admin Inspector).
     */
    public function add_order_context_metabox() {
        $screen = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id( 'shop-order' )
            : 'shop_order';
            
        add_meta_box(
            'urst-order-context',
            __( '📍 Menu Context', 'unstoppable-restaurant' ),
            array( $this, 'render_order_context_metabox' ),
            $screen,
            'side',
            'default'
        );
    }
    
    /**
     * Render order context metabox.
     *
     * @param \WP_Post|\WC_Order $post_or_order Post or order object.
     */
    public function render_order_context_metabox( $post_or_order ) {
        $order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
        
        if ( ! $order ) {
            echo '<p>No order found.</p>';
            return;
        }
        
        // Get order-level context.
        $order_context = $order->get_meta( '_urst_order_context', true );
        
        echo '<div style="font-size: 12px;">';
        
        if ( $order_context ) {
            echo '<strong>Order Summary:</strong><br>';
            echo '<code style="display: block; background: #f5f5f5; padding: 8px; margin: 4px 0; font-size: 11px; overflow-x: auto;">';
            echo esc_html( wp_json_encode( $order_context, JSON_PRETTY_PRINT ) );
            echo '</code>';
        } else {
            echo '<p style="color: #666;">No order context found.</p>';
        }
        
        // Show first 2 item contexts.
        $items = $order->get_items();
        $count = 0;
        
        echo '<strong style="margin-top: 10px; display: block;">Item Contexts:</strong>';
        
        foreach ( $items as $item ) {
            if ( $count >= 2 ) {
                echo '<p style="color: #666; font-style: italic;">+ ' . ( count( $items ) - 2 ) . ' more items...</p>';
                break;
            }
            
            $context = $item->get_meta( '_urst_context', true );
            if ( $context ) {
                echo '<div style="margin: 4px 0;">';
                echo '<small>' . esc_html( $item->get_name() ) . ':</small><br>';
                echo '<code style="display: block; background: #f5f5f5; padding: 4px; font-size: 10px; overflow-x: auto;">';
                echo esc_html( wp_json_encode( $context ) );
                echo '</code>';
                echo '</div>';
            }
            
            $count++;
        }
        
        echo '</div>';
    }

    /**
     * Get menu context from a cart item.
     *
     * @param array $cart_item Cart item.
     * @return MenuContext|null
     */
    public static function get_context_from_cart_item( $cart_item ) {
        // Check canonical key first.
        if ( isset( $cart_item['urst_context'] ) ) {
            return MenuContext::from_array( $cart_item['urst_context'] );
        }
        // Legacy key support.
        if ( isset( $cart_item['urst_menu_context'] ) ) {
            return MenuContext::from_array( $cart_item['urst_menu_context'] );
        }
        return null;
    }

    /**
     * Get menu context from an order item.
     *
     * @param \WC_Order_Item_Product $item Order item.
     * @return MenuContext|null
     */
    public static function get_context_from_order_item( $item ) {
        // Check canonical key first.
        $data = $item->get_meta( '_urst_context', true );
        if ( empty( $data ) ) {
            // Legacy key support.
            $data = $item->get_meta( '_urst_menu_context', true );
        }
        
        if ( empty( $data ) || ! is_array( $data ) ) {
            return null;
        }

        return MenuContext::from_array( $data );
    }

    /**
     * Get order-level menu summary.
     *
     * @param \WC_Order $order Order.
     * @return array|null Summary or null.
     */
    public static function get_order_summary( $order ) {
        $summary = $order->get_meta( '_urst_order_context', true );
        if ( empty( $summary ) ) {
            // Legacy key support.
            $summary = $order->get_meta( '_urst_order_menu_summary', true );
        }
        return is_array( $summary ) ? $summary : null;
    }

    /**
     * Get all menu contexts from an order.
     *
     * @param \WC_Order $order Order.
     * @return MenuContext[] Array of contexts keyed by item ID.
     */
    public static function get_all_contexts_from_order( $order ) {
        $contexts = array();

        foreach ( $order->get_items() as $item ) {
            if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
                continue;
            }

            $context = self::get_context_from_order_item( $item );
            if ( $context ) {
                $contexts[ $item->get_id() ] = $context;
            }
        }

        return $contexts;
    }

    /**
     * Group order items by station.
     *
     * This is what printing will use for ticket routing.
     *
     * @param \WC_Order $order Order.
     * @return array Groups keyed by station.
     */
    public static function group_items_by_station( $order ) {
        $groups = array(
            'kitchen' => array(),
            'bar'     => array(),
            'counter' => array(),
            'expo'    => array(),
            'prep'    => array(),
        );

        foreach ( $order->get_items() as $item ) {
            if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
                continue;
            }

            $context = self::get_context_from_order_item( $item );
            $station = $context ? $context->get_station_hint() : null;

            // Default to kitchen if no station hint.
            if ( ! $station || ! isset( $groups[ $station ] ) ) {
                $station = 'kitchen';
            }

            $groups[ $station ][] = $item;
        }

        // Remove empty groups.
        return array_filter( $groups );
    }

    /**
     * Check if a cart item has menu context.
     *
     * @param array $cart_item Cart item.
     * @return bool
     */
    public static function has_context( $cart_item ) {
        return isset( $cart_item['urst_context'] ) || isset( $cart_item['urst_menu_context'] );
    }

    /**
     * Get station hint from cart item.
     *
     * @param array $cart_item Cart item.
     * @return string|null
     */
    public static function get_station_hint( $cart_item ) {
        $context = self::get_context_from_cart_item( $cart_item );
        return $context ? $context->get_station_hint() : null;
    }
}
