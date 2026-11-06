<?php
/**
 * Template: Empty State
 *
 * Displayed when no products are found.
 *
 * This template can be overridden by copying it to:
 * yourtheme/unstoppable-restaurant/layouts/empty.php
 *
 * @package Unstoppable_Restaurant
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="ur-wrap ur-empty">
    <div class="ur-empty__icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
    </div>
    <h2 class="ur-empty__title"><?php esc_html_e( 'No menu items found', 'unstoppable-restaurant' ); ?></h2>
    <p class="ur-empty__description">
        <?php esc_html_e( 'There are no products to display. Please add some products to your WooCommerce store.', 'unstoppable-restaurant' ); ?>
    </p>
    
    <?php if ( current_user_can( 'manage_woocommerce' ) ) : ?>
        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="ur-btn ur-btn--primary">
            <?php esc_html_e( 'Add Product', 'unstoppable-restaurant' ); ?>
        </a>
    <?php endif; ?>
</div>
