<?php
/**
 * Template Part: Product Card
 *
 * Displays a single product card in the menu layout.
 * This is a RENDER-ONLY component - no business logic.
 *
 * This template can be overridden by copying it to:
 * yourtheme/unstoppable-restaurant/layouts/partials/product-card.php
 *
 * @package Unstoppable_Restaurant
 *
 * @var array $product           Product data (includes menu_context if available).
 * @var bool  $show_images       Show product image.
 * @var bool  $show_prices       Show price.
 * @var bool  $show_descriptions Show description.
 * @var array $menu_context      Menu context (optional, for backward compat).
 */

defined( 'ABSPATH' ) || exit;

// Build CSS classes.
$classes = array( 'ur-product-card' );

if ( ! $show_images || empty( $product['image_url'] ) ) {
    $classes[] = 'ur-product-card--no-image';
}

if ( ! $product['in_stock'] ) {
    $classes[] = 'ur-product-card--out-of-stock';
}

// Extract menu context - can come from product array or separate variable.
$context = array();
if ( isset( $product['menu_context'] ) ) {
    $context = $product['menu_context'];
} elseif ( isset( $menu_context ) && is_array( $menu_context ) ) {
    $context = $menu_context;
}

// Build data attributes for menu context (used by JS add-to-cart).
// Phase 7.4: These data attributes are captured ONCE and sent to cart.
$data_attrs = array(
    'data-product-id' => $product['id'],
);

if ( ! empty( $context['menu_id'] ) ) {
    $data_attrs['data-menu-id'] = esc_attr( $context['menu_id'] );
}
if ( ! empty( $context['menu_slug'] ) ) {
    $data_attrs['data-menu-slug'] = esc_attr( $context['menu_slug'] );
}
if ( ! empty( $context['menu_name'] ) ) {
    $data_attrs['data-menu-name'] = esc_attr( $context['menu_name'] );
}
if ( ! empty( $context['category_id'] ) ) {
    $data_attrs['data-category-id'] = esc_attr( $context['category_id'] );
}
if ( ! empty( $context['category_name'] ) ) {
    $data_attrs['data-category-name'] = esc_attr( $context['category_name'] );
}
// Phase 7.3 future-proofing: section_id.
if ( ! empty( $context['section_id'] ) ) {
    $data_attrs['data-section-id'] = esc_attr( $context['section_id'] );
}
// Station hint (can come as 'station' or 'station_hint').
if ( ! empty( $context['station'] ) ) {
    $data_attrs['data-station'] = esc_attr( $context['station'] );
} elseif ( ! empty( $context['station_hint'] ) ) {
    $data_attrs['data-station'] = esc_attr( $context['station_hint'] );
}
if ( ! empty( $context['channel'] ) ) {
    $data_attrs['data-channel'] = esc_attr( $context['channel'] );
}

// Build data attributes string.
$data_attrs_str = '';
foreach ( $data_attrs as $key => $value ) {
    $data_attrs_str .= ' ' . $key . '="' . $value . '"';
}
?>

<article 
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    <?php echo $data_attrs_str; ?>
>
    <?php if ( $show_images ) : ?>
        <div class="ur-product-card__image">
            <?php if ( ! empty( $product['image_url'] ) ) : ?>
                <img 
                    src="<?php echo esc_url( $product['image_url'] ); ?>" 
                    alt="<?php echo esc_attr( $product['name'] ); ?>"
                    loading="lazy"
                    decoding="async"
                />
            <?php else : ?>
                <div class="ur-product-card__image--placeholder">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                </div>
            <?php endif; ?>
            
            <?php if ( $product['on_sale'] ) : ?>
                <div class="ur-product-card__sale-badge">
                    <span class="ur-product-label ur-product-label--sale">
                        <?php esc_html_e( 'Sale', 'unstoppable-restaurant' ); ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <?php
            /**
             * Product labels (Spicy, Vegan, etc.)
             *
             * @since 2.5.0
             * @param int $product_id Product ID.
             */
            do_action( 'urst_product_card_labels', $product['id'] );
            ?>
        </div>
    <?php endif; ?>

    <div class="ur-product-card__content">
        <h3 class="ur-product-card__name"><?php echo esc_html( $product['name'] ); ?></h3>
        
        <?php if ( $show_descriptions && ! empty( $product['description'] ) ) : ?>
            <p class="ur-product-card__description ur-line-clamp-2">
                <?php echo esc_html( wp_strip_all_tags( $product['description'] ) ); ?>
            </p>
        <?php endif; ?>

        <div class="ur-product-card__footer">
            <?php if ( $show_prices ) : ?>
                <div class="ur-product-card__price">
                    <?php if ( ! empty( $product['is_variable'] ) ) : ?>
                        <span class="ur-product-card__price-from"><?php esc_html_e( 'From', 'unstoppable-restaurant' ); ?></span>
                    <?php endif; ?>
                    <?php echo wp_kses_post( $product['price_html'] ); ?>
                </div>
            <?php else : ?>
                <div class="ur-product-card__price"></div>
            <?php endif; ?>
            
            <?php if ( $product['in_stock'] ) : ?>
                <button 
                    type="button" 
                    class="ur-product-card__add-btn"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Add %s to cart', 'unstoppable-restaurant' ), $product['name'] ) ); ?>"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12 3.75a.75.75 0 01.75.75v6.75h6.75a.75.75 0 010 1.5h-6.75v6.75a.75.75 0 01-1.5 0v-6.75H4.5a.75.75 0 010-1.5h6.75V4.5a.75.75 0 01.75-.75z" clip-rule="evenodd" />
                    </svg>
                </button>
            <?php else : ?>
                <span class="ur-product-card__stock-badge">
                    <?php esc_html_e( 'Sold Out', 'unstoppable-restaurant' ); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    
    <?php // Overlay for out of stock ?>
    <?php if ( ! $product['in_stock'] ) : ?>
        <div class="ur-product-card__overlay">
            <span class="ur-product-card__overlay-text"><?php esc_html_e( 'Sold Out', 'unstoppable-restaurant' ); ?></span>
        </div>
    <?php endif; ?>
</article>
