<?php
/**
 * Admin Template: Modifier Assignments
 *
 * @package Unstoppable_Restaurant
 *
 * @var array $products   Assigned product IDs.
 * @var array $categories Assigned category IDs.
 */

defined( 'ABSPATH' ) || exit;

// Get all products.
$all_products = wc_get_products( array(
    'limit'  => -1,
    'status' => 'publish',
    'return' => 'ids',
) );

// Get all product categories.
$all_categories = get_terms( array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
) );
?>

<style>
.urst-assignments { padding: 10px 0; }
.urst-assignments .urst-field-group { margin-bottom: 20px; }
.urst-assignments .urst-field-group label { display: block; font-weight: 600; margin-bottom: 5px; }
.urst-assignments .urst-field-group .description { color: #666; font-style: italic; margin-bottom: 8px; display: block; }
.urst-assignments .urst-checkbox-list { 
    max-height: 200px; 
    overflow-y: auto; 
    border: 1px solid #ddd; 
    padding: 10px; 
    background: #fff;
}
.urst-assignments .urst-checkbox-list label { 
    display: block; 
    font-weight: normal; 
    padding: 4px 0;
    cursor: pointer;
}
.urst-assignments .urst-checkbox-list label:hover { background: #f5f5f5; }
.urst-assignments .urst-checkbox-list input[type="checkbox"] { margin-right: 8px; }
</style>

<div class="urst-assignments">
    <div class="urst-field-group">
        <label><?php esc_html_e( 'Products', 'unstoppable-restaurant' ); ?></label>
        <span class="description"><?php esc_html_e( 'Select specific products to use this modifier flow.', 'unstoppable-restaurant' ); ?></span>
        
        <div class="urst-checkbox-list">
            <?php if ( empty( $all_products ) ) : ?>
                <p><?php esc_html_e( 'No products found.', 'unstoppable-restaurant' ); ?></p>
            <?php else : ?>
                <?php foreach ( $all_products as $product_id ) : ?>
                    <?php $product = wc_get_product( $product_id ); ?>
                    <?php if ( ! $product ) continue; ?>
                    <label>
                        <input type="checkbox" 
                               name="urst_products[]" 
                               value="<?php echo esc_attr( $product_id ); ?>" 
                               <?php checked( in_array( $product_id, $products, false ) ); ?>>
                        <?php echo esc_html( $product->get_name() ); ?>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="urst-field-group">
        <label><?php esc_html_e( 'Categories', 'unstoppable-restaurant' ); ?></label>
        <span class="description"><?php esc_html_e( 'Or apply to all products in these categories.', 'unstoppable-restaurant' ); ?></span>
        
        <div class="urst-checkbox-list">
            <?php if ( empty( $all_categories ) ) : ?>
                <p><?php esc_html_e( 'No categories found.', 'unstoppable-restaurant' ); ?></p>
            <?php else : ?>
                <?php foreach ( $all_categories as $category ) : ?>
                    <label>
                        <input type="checkbox" 
                               name="urst_categories[]" 
                               value="<?php echo esc_attr( $category->term_id ); ?>" 
                               <?php checked( in_array( $category->term_id, $categories, false ) ); ?>>
                        <?php echo esc_html( $category->name ); ?>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <p class="description">
        <?php esc_html_e( 'Products assigned directly take priority over category assignments.', 'unstoppable-restaurant' ); ?>
    </p>
</div>
