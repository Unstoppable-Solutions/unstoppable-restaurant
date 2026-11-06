<?php
/**
 * Layout: Modern Tabs
 *
 * Sticky horizontal category/section tabs with smooth scroll navigation.
 * Active tab highlighting on scroll.
 *
 * Phase 7.3: This template receives either categories OR sections via
 * the $categories variable (from get_display_groups()). The data structure
 * is identical - both have: id, slug, name, description, products[].
 *
 * This template can be overridden by copying it to:
 * yourtheme/unstoppable-restaurant/layouts/tabs.php
 *
 * @package Unstoppable_Restaurant
 *
 * @var string $menu_id     Menu ID.
 * @var string $menu_slug   Menu slug.
 * @var string $layout      Layout type.
 * @var array  $categories  Display groups (categories OR sections) with products.
 * @var bool   $has_sections True if using sections instead of categories.
 * @var int    $columns     Number of columns.
 * @var bool   $show_images Show product images.
 * @var bool   $show_prices Show prices.
 * @var bool   $show_descriptions Show descriptions.
 * @var array  $settings    Plugin settings.
 * @var object $payload     Full MenuPayload object for advanced usage.
 */

defined( 'ABSPATH' ) || exit;

$template_loader = \Unstoppable\Restaurant\Core\TemplateLoader::instance();

// Phase 7.3.1: Graceful fallback if $categories is empty or invalid.
if ( empty( $categories ) || ! is_array( $categories ) ) {
    echo '<div class="ur-wrap ur-menu ur-layout-tabs ur-empty"><p>' . esc_html__( 'No menu items available.', 'unstoppable-restaurant' ) . '</p></div>';
    return;
}
?>

<div class="ur-wrap ur-menu ur-layout-tabs" data-layout="tabs">
    
    <?php // Sticky Category Tabs ?>
    <nav class="ur-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Menu categories', 'unstoppable-restaurant' ); ?>">
        <div class="ur-tabs__fade-left" aria-hidden="true"></div>
        <div class="ur-tabs__scroll">
            <ul class="ur-tabs__list">
                <?php foreach ( $categories as $index => $category ) : ?>
                    <li class="ur-tab" role="presentation">
                        <button 
                            type="button"
                            role="tab"
                            class="ur-tab__button<?php echo 0 === $index ? ' is-active' : ''; ?>"
                            id="tab-<?php echo esc_attr( $category['slug'] ); ?>"
                            aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
                            aria-controls="panel-<?php echo esc_attr( $category['slug'] ); ?>"
                            data-category="<?php echo esc_attr( $category['slug'] ); ?>"
                        >
                            <?php echo esc_html( $category['name'] ); ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="ur-tabs__fade-right" aria-hidden="true"></div>
    </nav>

    <?php // Main Content ?>
    <main class="ur-main">
        <?php foreach ( $categories as $index => $category ) : ?>
            <section 
                id="panel-<?php echo esc_attr( $category['slug'] ); ?>" 
                class="ur-category"
                role="tabpanel"
                aria-labelledby="tab-<?php echo esc_attr( $category['slug'] ); ?>"
                data-category-id="<?php echo esc_attr( $category['id'] ); ?>"
                style="display: block !important; visibility: visible !important; opacity: 1 !important;"
            >
                <header class="ur-category__header">
                    <h2 class="ur-category__title"><?php echo esc_html( $category['name'] ); ?></h2>
                    <?php if ( ! empty( $category['description'] ) ) : ?>
                        <p class="ur-category__description"><?php echo esc_html( $category['description'] ); ?></p>
                    <?php endif; ?>
                </header>

                <div class="ur-products" style="--ur-grid-columns: <?php echo esc_attr( $columns ); ?>; display: grid !important; visibility: visible !important;">
                    <?php
                    foreach ( $category['products'] as $product ) :
                        // Pass menu context - comes from payload->to_array() format.
                        $menu_context = isset( $product['menu_context'] ) ? $product['menu_context'] : array(
                            'menu_id'       => $menu_id ?? '',
                            'menu_slug'     => $menu_slug ?? '',
                            'category_id'   => $category['id'] ?? 0,
                            'category_name' => $category['name'] ?? '',
                        );
                        
                        $template_loader->get( 'layouts/partials/product-card.php', [
                            'product'           => $product,
                            'show_images'       => $show_images,
                            'show_prices'       => $show_prices,
                            'show_descriptions' => $show_descriptions,
                            'menu_context'      => $menu_context,
                        ] );
                    endforeach;
                    ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>

</div>

<?php // Inline script for tabs functionality ?>
<script>
(function() {
    'use strict';
    
    const menu = document.querySelector('.ur-layout-tabs');
    if (!menu) return;
    
    const tabsContainer = menu.querySelector('.ur-tabs');
    const tabsScroll = menu.querySelector('.ur-tabs__scroll');
    const tabs = menu.querySelectorAll('.ur-tab__button');
    const sections = menu.querySelectorAll('.ur-category');
    
    // Get sticky offset (for themes with fixed headers)
    function getStickyOffset() {
        const computed = getComputedStyle(tabsContainer);
        const offset = computed.getPropertyValue('--ur-sticky-offset');
        return offset ? parseInt(offset, 10) : 0;
    }
    
    // Tab click handling
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            const targetId = 'panel-' + this.dataset.category;
            const target = document.getElementById(targetId);
            
            if (target) {
                // Get the tabs height and any sticky offset
                const tabsHeight = tabsContainer.offsetHeight;
                const stickyOffset = getStickyOffset();
                
                // Calculate target position: section top - tabs height - sticky offset - small gap
                const targetTop = target.getBoundingClientRect().top + window.pageYOffset;
                const scrollPosition = targetTop - tabsHeight - stickyOffset - 20;
                
                // Temporarily disable scroll observer to prevent conflicts
                isScrolling = true;
                
                window.scrollTo({
                    top: Math.max(0, scrollPosition),
                    behavior: 'smooth'
                });
                
                // Update active state immediately for better UX
                tabs.forEach(function(t) {
                    t.classList.remove('is-active');
                    t.setAttribute('aria-selected', 'false');
                });
                this.classList.add('is-active');
                this.setAttribute('aria-selected', 'true');
                
                // Re-enable scroll observer after animation
                setTimeout(function() {
                    isScrolling = false;
                }, 800);
            }
        });
    });
    
    // Scroll observer flag
    let isScrolling = false;
    
    // Update active tab on scroll
    if (sections.length && tabs.length) {
        const tabsHeight = tabsContainer.offsetHeight;
        const stickyOffset = getStickyOffset();
        
        const observer = new IntersectionObserver(function(entries) {
            if (isScrolling) return; // Skip during programmatic scroll
            
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const slug = entry.target.id.replace('panel-', '');
                    
                    tabs.forEach(function(tab) {
                        const isActive = tab.dataset.category === slug;
                        tab.classList.toggle('is-active', isActive);
                        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        
                        // Scroll active tab into view in the horizontal scroll
                        if (isActive && tabsScroll) {
                            const tabRect = tab.getBoundingClientRect();
                            const scrollRect = tabsScroll.getBoundingClientRect();
                            
                            if (tabRect.left < scrollRect.left || tabRect.right > scrollRect.right) {
                                tab.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                            }
                        }
                    });
                }
            });
        }, {
            rootMargin: '-' + (tabsHeight + stickyOffset + 40) + 'px 0px -50% 0px',
            threshold: 0
        });
        
        sections.forEach(function(section) {
            observer.observe(section);
        });
    }
    
    // Check scroll indicators
    function updateScrollIndicators() {
        if (!tabsScroll) return;
        
        const canScrollLeft = tabsScroll.scrollLeft > 0;
        const canScrollRight = tabsScroll.scrollLeft < (tabsScroll.scrollWidth - tabsScroll.clientWidth - 1);
        
        tabsContainer.classList.toggle('ur-tabs--can-scroll-left', canScrollLeft);
        tabsContainer.classList.toggle('ur-tabs--can-scroll-right', canScrollRight);
    }
    
    if (tabsScroll) {
        tabsScroll.addEventListener('scroll', updateScrollIndicators);
        window.addEventListener('resize', updateScrollIndicators);
        updateScrollIndicators();
    }
    
    // Keyboard navigation
    tabs.forEach(function(tab, index) {
        tab.addEventListener('keydown', function(e) {
            let targetIndex;
            
            if (e.key === 'ArrowLeft') {
                targetIndex = index - 1;
            } else if (e.key === 'ArrowRight') {
                targetIndex = index + 1;
            } else if (e.key === 'Home') {
                targetIndex = 0;
            } else if (e.key === 'End') {
                targetIndex = tabs.length - 1;
            }
            
            if (typeof targetIndex !== 'undefined' && tabs[targetIndex]) {
                e.preventDefault();
                tabs[targetIndex].focus();
                tabs[targetIndex].click();
            }
        });
    });
})();
</script>
