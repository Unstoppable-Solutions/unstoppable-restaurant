<?php
/**
 * Layout: Classic Menu
 *
 * Categories/Sections listed vertically with products grouped.
 * Desktop: Sidebar navigation + product grid.
 * Mobile: Dropdown category selector + product grid.
 *
 * Phase 7.3: This template receives either categories OR sections via
 * the $categories variable (from get_display_groups()). The data structure
 * is identical - both have: id, slug, name, description, products[].
 *
 * This template can be overridden by copying it to:
 * yourtheme/unstoppable-restaurant/layouts/classic.php
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
    echo '<div class="ur-wrap ur-menu ur-layout-classic ur-empty"><p>' . esc_html__( 'No menu items available.', 'unstoppable-restaurant' ) . '</p></div>';
    return;
}
?>

<div class="ur-wrap ur-menu ur-layout-classic" data-layout="classic">
    
    <?php // Mobile Category Dropdown ?>
    <div class="ur-mobile-nav">
        <label for="ur-category-select" class="ur-sr-only">
            <?php esc_html_e( 'Jump to category', 'unstoppable-restaurant' ); ?>
        </label>
        <select id="ur-category-select" class="ur-mobile-nav__select" aria-label="<?php esc_attr_e( 'Select category', 'unstoppable-restaurant' ); ?>">
            <option value=""><?php esc_html_e( 'Jump to category...', 'unstoppable-restaurant' ); ?></option>
            <?php foreach ( $categories as $category ) : ?>
                <option value="<?php echo esc_attr( $category['slug'] ); ?>">
                    <?php echo esc_html( $category['name'] ); ?> (<?php echo esc_html( count( $category['products'] ) ); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php // Desktop Sidebar Navigation ?>
    <aside class="ur-sidebar" role="navigation" aria-label="<?php esc_attr_e( 'Menu categories', 'unstoppable-restaurant' ); ?>">
        <nav>
            <ul class="ur-sidebar__nav">
                <?php foreach ( $categories as $index => $category ) : ?>
                    <li class="ur-sidebar__item">
                        <a href="#category-<?php echo esc_attr( $category['slug'] ?? 'cat-' . $index ); ?>" 
                           class="ur-sidebar__link<?php echo 0 === $index ? ' is-active' : ''; ?>"
                           data-category="<?php echo esc_attr( $category['slug'] ?? '' ); ?>">
                            <span class="ur-sidebar__text"><?php echo esc_html( $category['name'] ?? __( 'Category', 'unstoppable-restaurant' ) ); ?></span>
                            <span class="ur-sidebar__count"><?php echo esc_html( count( $category['products'] ?? array() ) ); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </aside>

    <?php // Main Content ?>
    <main class="ur-main">
        <?php foreach ( $categories as $category ) : ?>
            <section 
                id="category-<?php echo esc_attr( $category['slug'] ); ?>" 
                class="ur-category"
                data-category-id="<?php echo esc_attr( $category['id'] ); ?>"
            >
                <header class="ur-category__header">
                    <h2 class="ur-category__title"><?php echo esc_html( $category['name'] ); ?></h2>
                    <?php if ( ! empty( $category['description'] ) ) : ?>
                        <p class="ur-category__description"><?php echo esc_html( $category['description'] ); ?></p>
                    <?php endif; ?>
                </header>

                <div class="ur-products" style="--ur-grid-columns: <?php echo esc_attr( $columns ); ?>">
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

<?php // Inline script for smooth scrolling and active state ?>
<script>
(function() {
    'use strict';
    
    const menu = document.querySelector('.ur-layout-classic');
    if (!menu) return;
    
    const select = menu.querySelector('.ur-mobile-nav__select');
    const links = menu.querySelectorAll('.ur-sidebar__link');
    const sections = menu.querySelectorAll('.ur-category');
    
    // Use instant scroll for better performance on large menus
    function scrollToCategory(slug) {
        const target = document.getElementById('category-' + slug);
        if (target) {
            // Use instant scroll - smooth causes lag with many images
            target.scrollIntoView({ behavior: 'auto', block: 'start' });
            // Update active state immediately
            links.forEach(function(link) {
                link.classList.toggle('is-active', link.dataset.category === slug);
            });
            if (select) {
                select.value = slug;
            }
        }
    }
    
    // Mobile dropdown navigation
    if (select) {
        select.addEventListener('change', function() {
            if (this.value) {
                scrollToCategory(this.value);
            }
        });
    }
    
    // Desktop sidebar click
    links.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const slug = this.dataset.category;
            if (slug) {
                scrollToCategory(slug);
            }
        });
    });
    
    // Throttled scroll observer for active state (only visual, no scroll)
    if (sections.length && links.length) {
        let ticking = false;
        
        const observer = new IntersectionObserver(function(entries) {
            if (ticking) return;
            ticking = true;
            
            requestAnimationFrame(function() {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const slug = entry.target.id.replace('category-', '');
                        links.forEach(function(link) {
                            link.classList.toggle('is-active', link.dataset.category === slug);
                        });
                        if (select) {
                            select.value = slug;
                        }
                    }
                });
                ticking = false;
            });
        }, {
            rootMargin: '-10% 0px -70% 0px',
            threshold: 0
        });
        
        sections.forEach(function(section) {
            observer.observe(section);
        });
    }
})();
</script>
