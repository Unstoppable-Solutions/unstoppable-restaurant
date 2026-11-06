<?php
/**
 * Template: Cart Contents
 *
 * The cart items list inside the drawer.
 * This template is loaded via AJAX to update cart contents.
 *
 * This template can be overridden by copying it to:
 * yourtheme/unstoppable-restaurant/cart/cart-contents.php
 *
 * @package Unstoppable_Restaurant
 *
 * @var array $cart Cart contents from WC()->cart->get_cart().
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $cart ) ) : ?>
    <div class="ur-cart-empty">
        <div class="ur-cart-empty__icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="48" height="48" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
            </svg>
        </div>
        <p class="ur-cart-empty__text"><?php esc_html_e( 'Your order is empty', 'unstoppable-restaurant' ); ?></p>
        <p class="ur-cart-empty__subtext"><?php esc_html_e( 'Browse the menu to get started', 'unstoppable-restaurant' ); ?></p>
    </div>

<?php else : ?>
    <ul class="ur-cart-items">
        <?php foreach ( $cart as $cart_item_key => $cart_item ) :
            $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
            $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
            
            if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 ) {
                continue;
            }
            
            $product_name     = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
            $thumbnail        = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'thumbnail' ), $cart_item, $cart_item_key );
            $product_price    = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key );
            $max_quantity     = $_product->get_max_purchase_quantity();
            $current_quantity = $cart_item['quantity'];
        ?>
            <li class="ur-cart-item" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>">
                <div class="ur-cart-item__image">
                    <?php echo wp_kses_post( $thumbnail ); ?>
                </div>
                
                <div class="ur-cart-item__details">
                    <h4 class="ur-cart-item__name"><?php echo esc_html( $product_name ); ?></h4>
                    
                    <?php // Placeholder for modifier summary (Phase 5) ?>
                    <?php if ( ! empty( $cart_item['ur_modifiers'] ) ) : ?>
                        <div class="ur-cart-item__modifiers">
                            <?php
                            /**
                             * Display modifier selections in cart.
                             * Placeholder hook for Phase 5.
                             *
                             * @since 1.0.0
                             * @param array $modifiers  Modifier selections.
                             * @param array $cart_item  Cart item data.
                             */
                            do_action( 'urst_cart_item_modifiers', $cart_item['ur_modifiers'], $cart_item );
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php // Special instructions ?>
                    <?php if ( ! empty( $cart_item['ur_special_instructions'] ) ) : ?>
                        <div class="ur-cart-item__instructions">
                            <em><?php echo esc_html( $cart_item['ur_special_instructions'] ); ?></em>
                        </div>
                    <?php endif; ?>
                    
                    <div class="ur-cart-item__price"><?php echo wp_kses_post( $product_price ); ?></div>
                </div>
                
                <div class="ur-cart-item__actions">
                    <div class="ur-quantity">
                        <button 
                            type="button" 
                            class="ur-quantity__btn ur-quantity__btn--minus"
                            data-action="decrease"
                            data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>"
                            aria-label="<?php esc_attr_e( 'Decrease quantity', 'unstoppable-restaurant' ); ?>"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        
                        <span class="ur-quantity__value"><?php echo esc_html( $current_quantity ); ?></span>
                        
                        <button 
                            type="button" 
                            class="ur-quantity__btn ur-quantity__btn--plus"
                            data-action="increase"
                            data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>"
                            aria-label="<?php esc_attr_e( 'Increase quantity', 'unstoppable-restaurant' ); ?>"
                            <?php echo ( $max_quantity > 0 && $current_quantity >= $max_quantity ) ? 'disabled' : ''; ?>
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16" aria-hidden="true">
                                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                            </svg>
                        </button>
                    </div>
                    
                    <button 
                        type="button" 
                        class="ur-cart-item__remove"
                        data-action="remove"
                        data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Remove %s from cart', 'unstoppable-restaurant' ), $product_name ) ); ?>"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.519.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
