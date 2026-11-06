<?php
/**
 * Template Part: Product Card Skeleton
 *
 * Displays a loading skeleton for product cards.
 * Used during initial load and AJAX refreshes.
 *
 * @package Unstoppable_Restaurant
 *
 * @var bool $show_images Show image placeholder.
 */

defined( 'ABSPATH' ) || exit;

$show_images = isset( $show_images ) ? $show_images : true;
?>

<article class="ur-product-card ur-product-card--skeleton" aria-hidden="true">
    <?php if ( $show_images ) : ?>
        <div class="ur-product-card__image">
            <div class="ur-skeleton" style="width: 100%; height: 100%;"></div>
        </div>
    <?php endif; ?>

    <div class="ur-product-card__content">
        <div class="ur-product-card__name ur-skeleton" style="width: 70%; height: 1.5rem;"></div>
        <div class="ur-product-card__description ur-skeleton" style="width: 100%; height: 2.5rem; margin-top: 0.5rem;"></div>
        
        <div class="ur-product-card__footer" style="margin-top: auto; padding-top: 1rem;">
            <div class="ur-product-card__price ur-skeleton" style="width: 4rem; height: 1.5rem;"></div>
            <div class="ur-skeleton" style="width: 2.5rem; height: 2.5rem; border-radius: 50%;"></div>
        </div>
    </div>
</article>
