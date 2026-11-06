<?php
/**
 * Template: Cart Drawer
 *
 * The slide-in cart drawer.
 * Desktop: Slides from right
 * Mobile: Slides from bottom
 *
 * This template can be overridden by copying it to:
 * yourtheme/unstoppable-restaurant/cart/drawer.php
 *
 * @package Unstoppable_Restaurant
 *
 * @var string $position Drawer position (left or right) - desktop only.
 */

defined( 'ABSPATH' ) || exit;

$template_loader = \Unstoppable\Restaurant\Core\TemplateLoader::instance();
$cart_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
$cart_total = WC()->cart ? WC()->cart->get_cart_subtotal() : wc_price( 0 );
?>

<?php // Overlay ?>
<div class="ur-overlay" data-ur-close-drawer aria-hidden="true"></div>

<?php // Drawer ?>
<aside 
    class="ur-drawer ur-drawer--<?php echo esc_attr( $position ); ?>"
    id="ur-cart-drawer"
    role="dialog"
    aria-modal="true"
    aria-labelledby="ur-drawer-title"
    aria-hidden="true"
>
    <div class="ur-drawer__inner">
        <?php // Header ?>
        <header class="ur-drawer__header">
            <h2 class="ur-drawer__title" id="ur-drawer-title">
                <?php esc_html_e( 'Your Order', 'unstoppable-restaurant' ); ?>
                <span class="ur-drawer__count" data-ur-cart-count><?php echo esc_html( $cart_count ); ?></span>
            </h2>
            <button 
                type="button" 
                class="ur-drawer__close"
                data-ur-close-drawer
                aria-label="<?php esc_attr_e( 'Close cart', 'unstoppable-restaurant' ); ?>"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.47 5.47a.75.75 0 011.06 0L12 10.94l5.47-5.47a.75.75 0 111.06 1.06L13.06 12l5.47 5.47a.75.75 0 11-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 01-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 010-1.06z" clip-rule="evenodd" />
                </svg>
            </button>
        </header>

        <?php // Cart Contents ?>
        <div class="ur-drawer__body">
            <div class="ur-cart-contents" data-ur-cart-contents>
                <?php
                $template_loader->get( 'cart/cart-contents.php', array(
                    'cart' => WC()->cart ? WC()->cart->get_cart() : array(),
                ) );
                ?>
            </div>
        </div>

        <?php // Footer ?>
        <footer class="ur-drawer__footer">
            <div class="ur-drawer__totals">
                <span class="ur-drawer__totals-label"><?php esc_html_e( 'Subtotal', 'unstoppable-restaurant' ); ?></span>
                <span class="ur-drawer__totals-value" data-ur-cart-total><?php echo wp_kses_post( $cart_total ); ?></span>
            </div>
            
            <div class="ur-drawer__actions">
                <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="ur-btn ur-btn--secondary ur-btn--full">
                    <?php esc_html_e( 'View Cart', 'unstoppable-restaurant' ); ?>
                </a>
                <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="ur-btn ur-btn--primary ur-btn--full">
                    <?php esc_html_e( 'Checkout', 'unstoppable-restaurant' ); ?>
                </a>
            </div>
        </footer>
    </div>
</aside>
