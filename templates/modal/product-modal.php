<?php
/**
 * Template: Product Modal Content
 *
 * Displays product details inside the modal.
 * Contains placeholder regions for future features (modifiers, labels, availability).
 *
 * This template can be overridden by copying it to:
 * yourtheme/unstoppable-restaurant/modal/product-modal.php
 *
 * @package Unstoppable_Restaurant
 *
 * @var array $product Product data from ProductModal::format_product_data().
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="ur-modal__panel">
    <?php // Close button ?>
    <button 
        type="button" 
        class="ur-modal__close"
        data-ur-close-modal
        aria-label="<?php esc_attr_e( 'Close', 'unstoppable-restaurant' ); ?>"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.47 5.47a.75.75 0 011.06 0L12 10.94l5.47-5.47a.75.75 0 111.06 1.06L13.06 12l5.47 5.47a.75.75 0 11-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 01-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 010-1.06z" clip-rule="evenodd" />
        </svg>
    </button>

    <?php // Product Image ?>
    <?php if ( ! empty( $product['image_url'] ) ) : ?>
        <div class="ur-modal__image">
            <img 
                src="<?php echo esc_url( $product['image_url'] ); ?>" 
                alt="<?php echo esc_attr( $product['name'] ); ?>"
                loading="eager"
            />
            <?php if ( $product['on_sale'] ) : ?>
                <span class="ur-modal__badge ur-modal__badge--sale">
                    <?php esc_html_e( 'Sale', 'unstoppable-restaurant' ); ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php // Scrollable Body ?>
    <div class="ur-modal__body">
        <?php // Header: Name & Price ?>
        <header class="ur-modal__header">
            <h2 class="ur-modal__title" id="ur-modal-title">
                <?php echo esc_html( $product['name'] ); ?>
            </h2>
            <div class="ur-modal__price">
                <?php echo wp_kses_post( $product['price_html'] ); ?>
            </div>
        </header>

        <?php // Short Description ?>
        <?php if ( ! empty( $product['short_description'] ) ) : ?>
            <div class="ur-modal__description">
                <?php echo wp_kses_post( $product['short_description'] ); ?>
            </div>
        <?php endif; ?>

        <?php 
        // =====================================================================
        // PLACEHOLDER: Dietary / Label Badges (Phase 7)
        // This region will display badges like: Spicy, Vegan, Gluten-Free, etc.
        // =====================================================================
        ?>
        <div class="ur-modal__labels" data-ur-labels-region>
            <?php
            /**
             * Hook for product labels/badges.
             * Reserved for Phase 7 implementation.
             *
             * @since 2.2.0
             * @param array $product Product data.
             */
            do_action( 'urst_modal_product_labels', $product );
            ?>
        </div>

        <?php 
        // =====================================================================
        // PLACEHOLDER: Availability Messages (Phase 6/7)
        // This region will display: "Available for delivery", time restrictions, etc.
        // =====================================================================
        ?>
        <div class="ur-modal__availability" data-ur-availability-region>
            <?php
            /**
             * Hook for availability messages.
             * Reserved for Phase 6/7 implementation.
             *
             * @since 2.2.0
             * @param array $product Product data.
             */
            do_action( 'urst_modal_product_availability', $product );
            ?>
        </div>

        <?php 
        // =====================================================================
        // PLACEHOLDER: Modifier Steps Area (Phase 5)
        // This is where Conversational Modifiers will render their step UI.
        // The modifier flow will completely replace the simple quantity selector
        // when modifiers are attached to a product.
        // =====================================================================
        ?>
        <div class="ur-modal__modifiers" data-ur-modifiers-region data-product-id="<?php echo esc_attr( $product['id'] ); ?>">
            <?php
            /**
             * Hook for modifier flow rendering.
             * Reserved for Phase 5 implementation.
             *
             * @since 2.2.0
             * @param array $product Product data.
             */
            do_action( 'urst_modal_modifier_flow', $product );
            ?>
        </div>

        <?php // Quantity Selector (shown when no modifiers) ?>
        <div class="ur-modal__quantity" data-ur-quantity-region>
            <label class="ur-modal__quantity-label">
                <?php esc_html_e( 'Quantity', 'unstoppable-restaurant' ); ?>
            </label>
            <div class="ur-quantity ur-quantity--lg">
                <button 
                    type="button" 
                    class="ur-quantity__btn ur-quantity__btn--minus"
                    data-action="modal-decrease"
                    aria-label="<?php esc_attr_e( 'Decrease quantity', 'unstoppable-restaurant' ); ?>"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="20" height="20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
                    </svg>
                </button>
                <input 
                    type="number" 
                    class="ur-quantity__input" 
                    value="1" 
                    min="1" 
                    max="<?php echo esc_attr( $product['max_quantity'] ); ?>"
                    data-ur-modal-quantity
                    aria-label="<?php esc_attr_e( 'Quantity', 'unstoppable-restaurant' ); ?>"
                />
                <button 
                    type="button" 
                    class="ur-quantity__btn ur-quantity__btn--plus"
                    data-action="modal-increase"
                    aria-label="<?php esc_attr_e( 'Increase quantity', 'unstoppable-restaurant' ); ?>"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="20" height="20" aria-hidden="true">
                        <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                    </svg>
                </button>
            </div>
        </div>

        <?php 
        // =====================================================================
        // PLACEHOLDER: Special Instructions (Phase 5)
        // Text field for customer notes like "no onions", "extra sauce", etc.
        // Will be added as part of the modifier flow.
        // =====================================================================
        ?>
    </div>

    <?php // Sticky Footer ?>
    <footer class="ur-modal__footer">
        <?php if ( $product['in_stock'] ) : ?>
            <button 
                type="button" 
                class="ur-btn ur-btn--primary ur-btn--lg ur-btn--full"
                data-ur-modal-add-to-cart
                data-product-id="<?php echo esc_attr( $product['id'] ); ?>"
            >
                <span class="ur-modal__add-text">
                    <?php esc_html_e( 'Add to Order', 'unstoppable-restaurant' ); ?>
                </span>
                <span class="ur-modal__add-separator">—</span>
                <span class="ur-modal__add-price" data-ur-modal-total>
                    <?php echo wp_kses_post( $product['price_html'] ); ?>
                </span>
            </button>
        <?php else : ?>
            <button type="button" class="ur-btn ur-btn--secondary ur-btn--lg ur-btn--full" disabled>
                <?php esc_html_e( 'Out of Stock', 'unstoppable-restaurant' ); ?>
            </button>
        <?php endif; ?>
    </footer>
</div>
