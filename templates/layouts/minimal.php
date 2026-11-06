<?php
/**
 * Layout: Minimal (Orderable-style)
 *
 * Sidebar category/section tabs on left, product list on right.
 * Shows one category at a time for better performance.
 *
 * Phase 7.3: This template receives either categories OR sections via
 * the $categories variable (from get_display_groups()). The data structure
 * is identical - both have: id, slug, name, description, products[].
 *
 * @package Unstoppable_Restaurant
 *
 * @var string $menu_id     Menu ID.
 * @var string $menu_slug   Menu slug.
 * @var array  $categories  Display groups (categories OR sections) with products.
 * @var bool   $has_sections True if using sections instead of categories.
 * @var bool   $show_images Show product images.
 * @var bool   $show_prices Show prices.
 * @var bool   $show_descriptions Show descriptions.
 * @var object $payload     Full MenuPayload object for advanced usage.
 */

defined( 'ABSPATH' ) || exit;

// Phase 7.3.1: Graceful fallback if $categories is empty or invalid.
if ( empty( $categories ) || ! is_array( $categories ) ) {
    echo '<div class="urm-wrap urm-empty"><p>' . esc_html__( 'No menu items available.', 'unstoppable-restaurant' ) . '</p></div>';
    return;
}
?>

<style>
/* Container */
.urm-wrap { 
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
    max-width: 1200px; 
    margin: 0 auto; 
    display: flex;
    gap: 30px;
    padding: 20px;
}

/* Sidebar */
.urm-sidebar {
    flex: 0 0 200px;
    position: sticky;
    top: 100px;
    height: fit-content;
}

.urm-sidebar-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.urm-sidebar-btn {
    display: block;
    width: 100%;
    text-align: left;
    background: #f1f5f9;
    border: none;
    padding: 12px 16px;
    border-radius: 25px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #475569;
    transition: all 0.2s;
}

.urm-sidebar-btn:hover {
    background: #e2e8f0;
}

.urm-sidebar-btn.active {
    background: #f59e0b;
    color: white;
}

/* Main content */
.urm-main {
    flex: 1;
    min-width: 0;
}

/* Panel */
.urm-panel {
    display: none;
}

.urm-panel.active {
    display: block;
}

/* Product list */
.urm-product-list {
    display: flex;
    flex-direction: column;
    gap: 1px;
    background: #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
}

/* Product item */
.urm-product {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: white;
}

.urm-product:hover {
    background: #fafafa;
}

.urm-product-image {
    flex: 0 0 80px;
    width: 80px;
    height: 80px;
    border-radius: 8px;
    overflow: hidden;
    background: #f3f4f6;
}

.urm-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.urm-product-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 12px;
}

.urm-product-info {
    flex: 1;
    min-width: 0;
}

.urm-product-name {
    margin: 0 0 4px;
    font-size: 17px;
    font-weight: 600;
    color: #111827;
}

.urm-product-desc {
    margin: 0 0 8px;
    font-size: 14px;
    color: #6b7280;
    line-height: 1.4;
}

.urm-product-price {
    font-size: 16px;
    font-weight: 700;
    color: #111827;
}

.urm-product-actions {
    flex: 0 0 auto;
}

.urm-select-btn {
    background: transparent;
    border: 2px solid #f59e0b;
    color: #f59e0b;
    padding: 10px 24px;
    border-radius: 25px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.2s;
}

.urm-select-btn:hover {
    background: #f59e0b;
    color: white;
}

.urm-select-btn:disabled {
    border-color: #d1d5db;
    color: #9ca3af;
    cursor: not-allowed;
}

.urm-select-btn:disabled:hover {
    background: transparent;
    color: #9ca3af;
}

/* Sold out */
.urm-product.soldout {
    opacity: 0.6;
}

.urm-soldout-badge {
    background: #fee2e2;
    color: #991b1b;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

/* Mobile */
@media (max-width: 768px) {
    .urm-wrap {
        flex-direction: column;
        gap: 20px;
    }
    
    .urm-sidebar {
        position: static;
        flex: none;
        width: 100%;
    }
    
    .urm-sidebar-list {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .urm-sidebar-btn {
        flex: 0 0 auto;
        padding: 10px 16px;
        font-size: 13px;
    }
    
    .urm-product {
        flex-wrap: wrap;
        padding: 16px;
    }
    
    .urm-product-image {
        flex: 0 0 60px;
        width: 60px;
        height: 60px;
    }
    
    .urm-product-info {
        flex: 1 1 calc(100% - 80px);
    }
    
    .urm-product-actions {
        flex: 0 0 100%;
        margin-top: 12px;
    }
    
    .urm-select-btn {
        width: 100%;
    }
}
</style>

<div class="urm-wrap">
    
    <!-- Sidebar Categories -->
    <nav class="urm-sidebar">
        <ul class="urm-sidebar-list">
            <?php foreach ( $categories as $index => $category ) : ?>
                <li>
                    <button 
                        type="button" 
                        class="urm-sidebar-btn<?php echo 0 === $index ? ' active' : ''; ?>"
                        data-target="<?php echo esc_attr( $category['slug'] ); ?>"
                    >
                        <?php echo esc_html( $category['name'] ); ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="urm-main">
        <?php foreach ( $categories as $index => $category ) : ?>
            <div 
                class="urm-panel<?php echo 0 === $index ? ' active' : ''; ?>" 
                id="panel-<?php echo esc_attr( $category['slug'] ); ?>"
            >
                <div class="urm-product-list">
                    <?php foreach ( $category['products'] as $product ) : 
                        $out_of_stock = ! $product['in_stock'];
                        
                        // Build menu context.
                        $menu_context = isset( $product['menu_context'] ) ? $product['menu_context'] : array(
                            'menu_id'       => $menu_id ?? '',
                            'menu_slug'     => $menu_slug ?? '',
                            'category_id'   => $category['id'] ?? 0,
                            'category_name' => $category['name'] ?? '',
                        );
                    ?>
                        <div class="urm-product<?php echo $out_of_stock ? ' soldout' : ''; ?>" 
                            data-product-id="<?php echo esc_attr( $product['id'] ); ?>"
                            data-menu-id="<?php echo esc_attr( $menu_context['menu_id'] ?? '' ); ?>"
                            data-menu-slug="<?php echo esc_attr( $menu_context['menu_slug'] ?? '' ); ?>"
                            data-category-id="<?php echo esc_attr( $menu_context['category_id'] ?? 0 ); ?>"
                            data-category-name="<?php echo esc_attr( $menu_context['category_name'] ?? '' ); ?>"
                        >
                            
                            <?php if ( $show_images ) : ?>
                                <div class="urm-product-image">
                                    <?php if ( ! empty( $product['image_url'] ) ) : ?>
                                        <img 
                                            src="<?php echo esc_url( $product['image_url'] ); ?>" 
                                            alt="<?php echo esc_attr( $product['name'] ); ?>"
                                            loading="lazy"
                                        >
                                    <?php else : ?>
                                        <div class="urm-product-image-placeholder">No image</div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="urm-product-info">
                                <h3 class="urm-product-name"><?php echo esc_html( $product['name'] ); ?></h3>
                                
                                <?php if ( $show_descriptions && ! empty( $product['description'] ) ) : ?>
                                    <p class="urm-product-desc"><?php echo esc_html( wp_strip_all_tags( $product['description'] ) ); ?></p>
                                <?php endif; ?>
                                
                                <?php if ( $show_prices ) : ?>
                                    <?php if ( $out_of_stock ) : ?>
                                        <span class="urm-soldout-badge">Sold Out</span>
                                    <?php elseif ( ! empty( $product['price'] ) ) : ?>
                                        <span class="urm-product-price">$<?php echo esc_html( number_format( (float) $product['price'], 2 ) ); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="urm-product-actions">
                                <button 
                                    type="button"
                                    class="urm-select-btn"
                                    <?php echo $out_of_stock ? 'disabled' : ''; ?>
                                    onclick="urmSelectProduct(<?php echo esc_attr( $product['id'] ); ?>)"
                                >
                                    <?php echo $out_of_stock ? esc_html__( 'Sold Out', 'unstoppable-restaurant' ) : esc_html__( 'Select', 'unstoppable-restaurant' ); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </main>
    
</div>

<script>
(function() {
    // Tab switching
    document.querySelectorAll('.urm-sidebar-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = this.getAttribute('data-target');
            
            // Update sidebar buttons
            document.querySelectorAll('.urm-sidebar-btn').forEach(function(b) { 
                b.classList.remove('active'); 
            });
            this.classList.add('active');
            
            // Update panels
            document.querySelectorAll('.urm-panel').forEach(function(p) { 
                p.classList.remove('active'); 
            });
            document.getElementById('panel-' + target).classList.add('active');
            
            // Scroll to top of main area on mobile
            if (window.innerWidth < 768) {
                document.querySelector('.urm-main').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
    
    // Select product - add to cart with menu context
    window.urmSelectProduct = function(productId) {
        var btn = event.target;
        var productEl = btn.closest('.urm-product');
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Adding...';
        
        var formData = new FormData();
        formData.append('action', 'woocommerce_ajax_add_to_cart');
        formData.append('product_id', productId);
        formData.append('quantity', 1);
        
        // Pass menu context from data attributes
        if (productEl) {
            if (productEl.dataset.menuId) formData.append('urst_menu_id', productEl.dataset.menuId);
            if (productEl.dataset.menuSlug) formData.append('urst_menu_slug', productEl.dataset.menuSlug);
            if (productEl.dataset.categoryId) formData.append('urst_category_id', productEl.dataset.categoryId);
            if (productEl.dataset.categoryName) formData.append('urst_category_name', productEl.dataset.categoryName);
        }
        
        fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            btn.textContent = 'Added ✓';
            setTimeout(function() {
                btn.disabled = false;
                btn.textContent = originalText;
            }, 2000);
            
            // Refresh cart fragments
            if (typeof jQuery !== 'undefined') {
                jQuery(document.body).trigger('wc_fragment_refresh');
            }
        })
        .catch(function(err) {
            btn.textContent = 'Error';
            setTimeout(function() {
                btn.disabled = false;
                btn.textContent = originalText;
            }, 2000);
        });
    };
})();
</script>
