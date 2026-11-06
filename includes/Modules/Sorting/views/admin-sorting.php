<?php
/**
 * Admin Sorting Page.
 *
 * @package Unstoppable_Restaurant
 *
 * @var array $categories Product categories.
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Menu Order', 'unstoppable-restaurant' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Drag and drop to reorder categories and products. Changes are saved automatically.', 'unstoppable-restaurant' ); ?></p>

    <div class="urst-sorting-container">
        <div class="urst-sorting-panel">
            <h2><?php esc_html_e( 'Categories', 'unstoppable-restaurant' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Drag to reorder how categories appear in the menu.', 'unstoppable-restaurant' ); ?></p>
            
            <ul class="urst-sortable-list" id="urst-category-list">
                <?php foreach ( $categories as $category ) : ?>
                    <li class="urst-sortable-item" data-id="<?php echo esc_attr( $category->term_id ); ?>">
                        <span class="urst-sortable-handle dashicons dashicons-menu"></span>
                        <span class="urst-sortable-name"><?php echo esc_html( $category->name ); ?></span>
                        <span class="urst-sortable-count"><?php echo esc_html( $category->count ); ?> <?php esc_html_e( 'products', 'unstoppable-restaurant' ); ?></span>
                        <button type="button" class="button-link urst-edit-products" data-category-id="<?php echo esc_attr( $category->term_id ); ?>" data-category-name="<?php echo esc_attr( $category->name ); ?>">
                            <?php esc_html_e( 'Edit products', 'unstoppable-restaurant' ); ?> →
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <?php if ( empty( $categories ) ) : ?>
                <p class="urst-empty-message"><?php esc_html_e( 'No categories found. Create product categories in WooCommerce first.', 'unstoppable-restaurant' ); ?></p>
            <?php endif; ?>
        </div>

        <div class="urst-sorting-panel" id="urst-products-panel" style="display: none;">
            <h2>
                <button type="button" class="button-link urst-back-to-categories">← <?php esc_html_e( 'Back', 'unstoppable-restaurant' ); ?></button>
                <span id="urst-products-title"><?php esc_html_e( 'Products', 'unstoppable-restaurant' ); ?></span>
            </h2>
            <p class="description"><?php esc_html_e( 'Drag to reorder products within this category.', 'unstoppable-restaurant' ); ?></p>
            
            <ul class="urst-sortable-list" id="urst-product-list">
                <!-- Populated via AJAX -->
            </ul>
            
            <p class="urst-empty-message" id="urst-no-products" style="display: none;">
                <?php esc_html_e( 'No products in this category.', 'unstoppable-restaurant' ); ?>
            </p>
        </div>
    </div>

    <div class="urst-save-status" id="urst-save-status">
        <span class="dashicons dashicons-yes-alt"></span>
        <?php esc_html_e( 'Saved', 'unstoppable-restaurant' ); ?>
    </div>
</div>

<style>
.urst-sorting-container {
    display: flex;
    gap: 30px;
    margin-top: 20px;
}
.urst-sorting-panel {
    flex: 1;
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    max-width: 500px;
}
.urst-sorting-panel h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 10px;
}
.urst-back-to-categories {
    color: #2271b1;
    font-size: 14px;
}
.urst-sortable-list {
    margin: 0;
    padding: 0;
    list-style: none;
}
.urst-sortable-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    margin: 4px 0;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: grab;
}
.urst-sortable-item:hover {
    background: #f0f0f0;
}
.urst-sortable-item.ui-sortable-helper {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.urst-sortable-item.ui-sortable-placeholder {
    visibility: visible !important;
    background: #e0e7ff;
    border: 2px dashed #4f46e5;
}
.urst-sortable-handle {
    color: #999;
    cursor: grab;
}
.urst-sortable-name {
    flex: 1;
    font-weight: 500;
}
.urst-sortable-count {
    color: #666;
    font-size: 13px;
}
.urst-edit-products {
    color: #2271b1;
    font-size: 13px;
}
.urst-empty-message {
    color: #666;
    font-style: italic;
    padding: 20px;
    text-align: center;
}
.urst-save-status {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: #00a32a;
    color: #fff;
    padding: 10px 20px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
    opacity: 0;
    transform: translateY(10px);
    transition: all 0.3s ease;
}
.urst-save-status.is-visible {
    opacity: 1;
    transform: translateY(0);
}
</style>

<script>
(function($) {
    const config = {
        ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
        nonce: '<?php echo esc_js( wp_create_nonce( 'urst_admin_nonce' ) ); ?>'
    };

    let saveTimeout;

    function showSaveStatus() {
        $('#urst-save-status').addClass('is-visible');
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            $('#urst-save-status').removeClass('is-visible');
        }, 2000);
    }

    // Category sorting
    $('#urst-category-list').sortable({
        handle: '.urst-sortable-handle',
        placeholder: 'urst-sortable-item ui-sortable-placeholder',
        update: function() {
            const order = $(this).sortable('toArray', { attribute: 'data-id' });
            
            $.post(config.ajaxUrl, {
                action: 'urst_save_category_order',
                nonce: config.nonce,
                order: order
            }, function(response) {
                if (response.success) {
                    showSaveStatus();
                }
            });
        }
    });

    // Product sorting
    $('#urst-product-list').sortable({
        handle: '.urst-sortable-handle',
        placeholder: 'urst-sortable-item ui-sortable-placeholder',
        update: function() {
            const order = $(this).sortable('toArray', { attribute: 'data-id' });
            
            $.post(config.ajaxUrl, {
                action: 'urst_save_product_order',
                nonce: config.nonce,
                order: order
            }, function(response) {
                if (response.success) {
                    showSaveStatus();
                }
            });
        }
    });

    // Edit products button
    $('.urst-edit-products').on('click', function() {
        const categoryId = $(this).data('category-id');
        const categoryName = $(this).data('category-name');
        
        $('#urst-products-title').text(categoryName);
        $('#urst-product-list').html('<li style="padding: 20px; text-align: center;">Loading...</li>');
        $('#urst-no-products').hide();
        $('#urst-products-panel').show();
        
        // Fetch products via AJAX
        $.post(config.ajaxUrl, {
            action: 'urst_get_category_products',
            nonce: config.nonce,
            category_id: categoryId
        }, function(response) {
            if (response.success && response.data.products.length) {
                let html = '';
                response.data.products.forEach(function(product) {
                    html += `
                        <li class="urst-sortable-item" data-id="${product.id}">
                            <span class="urst-sortable-handle dashicons dashicons-menu"></span>
                            <span class="urst-sortable-name">${product.title}</span>
                        </li>
                    `;
                });
                $('#urst-product-list').html(html);
                $('#urst-product-list').sortable('refresh');
            } else {
                $('#urst-product-list').html('');
                $('#urst-no-products').show();
            }
        });
    });

    // Back to categories
    $('.urst-back-to-categories').on('click', function() {
        $('#urst-products-panel').hide();
    });
})(jQuery);
</script>
