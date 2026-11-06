<?php
/**
 * Menu Repository.
 *
 * SINGLE SOURCE OF TRUTH for menu definitions.
 * Persists MenuDefinition objects as CPT (urst_menu).
 *
 * @package Unstoppable_Restaurant
 * @since 2.11.0
 */

namespace Unstoppable\Restaurant\Menu;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MenuRepository class.
 *
 * @since 2.11.0
 */
final class MenuRepository {

    /**
     * Post type slug.
     */
    const POST_TYPE = 'urst_menu';

    /**
     * Meta key for categories.
     */
    const META_CATEGORIES = '_urst_menu_categories';

    /**
     * Meta key for settings.
     */
    const META_SETTINGS = '_urst_menu_settings';

    /**
     * Legacy option key (for migration).
     */
    const LEGACY_OPTION_KEY = 'urst_menu_definitions';

    /**
     * Singleton instance.
     *
     * @var MenuRepository|null
     */
    private static $instance = null;

    /**
     * In-memory cache.
     *
     * @var array<int,MenuDefinition>
     */
    private $cache = array();

    /**
     * Get singleton instance.
     *
     * @return MenuRepository
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get menu by ID.
     *
     * @param int $id Menu post ID.
     * @return MenuDefinition|null
     */
    public function get_by_id( $id ) {
        $id = absint( $id );

        if ( isset( $this->cache[ $id ] ) ) {
            return $this->cache[ $id ];
        }

        $post = get_post( $id );

        if ( ! $post || self::POST_TYPE !== $post->post_type ) {
            return null;
        }

        $menu = $this->hydrate( $post );
        $this->cache[ $id ] = $menu;

        return $menu;
    }

    /**
     * Get menu by slug.
     *
     * @param string $slug Menu slug.
     * @return MenuDefinition|null
     */
    public function get_by_slug( $slug ) {
        $slug = sanitize_title( $slug );

        $posts = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'name'           => $slug,
            'post_status'    => array( 'publish', 'draft' ),
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ) );

        if ( empty( $posts ) ) {
            return null;
        }

        return $this->hydrate( $posts[0] );
    }

    /**
     * Get all menus.
     *
     * @param bool $published_only Only return published menus.
     * @return MenuDefinition[]
     */
    public function get_all( $published_only = true ) {
        $args = array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => $published_only ? 'publish' : array( 'publish', 'draft' ),
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        );

        $posts = get_posts( $args );

        return array_map( array( $this, 'hydrate' ), $posts );
    }

    /**
     * Get the default menu.
     *
     * @return MenuDefinition|null
     */
    public function get_default() {
        $menu = $this->get_by_slug( 'default-menu' );

        if ( ! $menu ) {
            $this->ensure_default_menu();
            $menu = $this->get_by_slug( 'default-menu' );
        }

        return $menu;
    }

    /**
     * Check if a menu exists by slug.
     *
     * @param string $slug Menu slug.
     * @return bool
     */
    public function exists( $slug ) {
        return null !== $this->get_by_slug( $slug );
    }

    /**
     * Save a menu (create or update).
     *
     * @param MenuDefinition $menu Menu to save.
     * @return int Post ID.
     * @throws \RuntimeException On save failure.
     */
    public function save( MenuDefinition $menu ) {
        $post_data = array(
            'post_type'    => self::POST_TYPE,
            'post_status'  => $menu->status,
            'post_title'   => $menu->name,
            'post_name'    => $menu->slug,
            'post_content' => $menu->description ?? '',
        );

        if ( $menu->id ) {
            $post_data['ID'] = $menu->id;
        }

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            throw new \RuntimeException( $post_id->get_error_message() );
        }

        // Save meta.
        update_post_meta( $post_id, self::META_CATEGORIES, $menu->category_ids );
        update_post_meta( $post_id, self::META_SETTINGS, $menu->settings );

        // Update menu object.
        $menu->id = $post_id;
        $menu->slug = get_post_field( 'post_name', $post_id );

        // Clear cache.
        unset( $this->cache[ $post_id ] );

        return $post_id;
    }

    /**
     * Create a new menu.
     *
     * @param array $data Menu data.
     * @return MenuDefinition
     */
    public function create( array $data ) {
        // Generate slug if not provided.
        if ( empty( $data['slug'] ) && ! empty( $data['name'] ) ) {
            $data['slug'] = $this->generate_unique_slug( $data['name'] );
        }

        $menu = new MenuDefinition( $data );
        $this->save( $menu );

        return $menu;
    }

    /**
     * Delete menu by ID.
     *
     * @param int $id Menu post ID.
     * @return bool
     */
    public function delete( $id ) {
        $id = absint( $id );

        $post = get_post( $id );
        if ( ! $post || self::POST_TYPE !== $post->post_type ) {
            return false;
        }

        $result = wp_delete_post( $id, true );

        if ( $result ) {
            unset( $this->cache[ $id ] );
        }

        return (bool) $result;
    }

    /**
     * Ensure default menu exists.
     */
    public function ensure_default_menu() {
        if ( $this->exists( 'default-menu' ) ) {
            return;
        }

        $this->create( array(
            'name'         => __( 'Default Menu', 'unstoppable-restaurant' ),
            'slug'         => 'default-menu',
            'description'  => __( 'The default restaurant menu showing all categories.', 'unstoppable-restaurant' ),
            'category_ids' => array(), // Empty = all categories.
            'status'       => 'publish',
            'settings'     => array(
                'layout' => 'classic',
            ),
        ) );
    }

    /**
     * Alias for ensure_default_menu (backward compat).
     *
     * @return MenuDefinition|null
     */
    public function create_default_menu() {
        $this->ensure_default_menu();
        return $this->get_by_slug( 'default-menu' );
    }

    /**
     * Get menu count.
     *
     * @return int
     */
    public function count() {
        $counts = wp_count_posts( self::POST_TYPE );
        return isset( $counts->publish ) ? (int) $counts->publish : 0;
    }

    /**
     * Clear the cache.
     */
    public function clear_cache() {
        $this->cache = array();
    }

    /**
     * Hydrate a MenuDefinition from a WP_Post.
     *
     * @param \WP_Post $post Post object.
     * @return MenuDefinition
     */
    private function hydrate( \WP_Post $post ) {
        $category_ids = get_post_meta( $post->ID, self::META_CATEGORIES, true );
        $settings = get_post_meta( $post->ID, self::META_SETTINGS, true );

        return new MenuDefinition( array(
            'id'           => $post->ID,
            'slug'         => $post->post_name,
            'name'         => $post->post_title,
            'description'  => $post->post_content,
            'status'       => $post->post_status,
            'category_ids' => is_array( $category_ids ) ? $category_ids : array(),
            'settings'     => is_array( $settings ) ? $settings : array(),
            'created_at'   => $post->post_date,
            'updated_at'   => $post->post_modified,
        ) );
    }

    /**
     * Generate a unique slug from name.
     *
     * @param string $name Menu name.
     * @return string
     */
    private function generate_unique_slug( $name ) {
        $base_slug = sanitize_title( $name );
        $slug = $base_slug;
        $counter = 1;

        while ( $this->exists( $slug ) ) {
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Migrate legacy menus from wp_options to CPT.
     *
     * Called on upgrade from older versions.
     *
     * @return int Number of menus migrated.
     */
    public function migrate_from_legacy() {
        $legacy_menus = get_option( self::LEGACY_OPTION_KEY, array() );

        if ( empty( $legacy_menus ) || ! is_array( $legacy_menus ) ) {
            return 0;
        }

        $migrated = 0;

        foreach ( $legacy_menus as $legacy ) {
            // Skip if already migrated (check by slug).
            if ( $this->exists( $legacy['slug'] ?? '' ) ) {
                continue;
            }

            // Convert old format to new.
            $category_ids = array();
            if ( ! empty( $legacy['categories'] ) ) {
                foreach ( $legacy['categories'] as $cat ) {
                    if ( is_numeric( $cat ) ) {
                        $category_ids[] = absint( $cat );
                    } elseif ( is_array( $cat ) && isset( $cat['term_id'] ) ) {
                        $category_ids[] = absint( $cat['term_id'] );
                    }
                }
            }

            // Build settings from old fields.
            $settings = array(
                'layout'     => $legacy['layout'] ?? 'classic',
                'filters'    => $legacy['filters'] ?? array(),
                'visibility' => $legacy['visibility'] ?? array( 'channels' => array( 'web' ) ),
                'sorting'    => $legacy['sorting'] ?? array(),
            );

            // Map old status.
            $status = 'publish';
            if ( isset( $legacy['status'] ) ) {
                $status = ( 'active' === $legacy['status'] ) ? 'publish' : 'draft';
            }

            try {
                $this->create( array(
                    'name'         => $legacy['name'] ?? 'Migrated Menu',
                    'slug'         => $legacy['slug'] ?? '',
                    'description'  => null,
                    'category_ids' => $category_ids,
                    'settings'     => $settings,
                    'status'       => $status,
                ) );
                $migrated++;
            } catch ( \Exception $e ) {
                // Log but continue.
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'URST: Menu migration failed: ' . $e->getMessage() );
                }
            }
        }

        // Mark migration complete (don't delete yet in case rollback needed).
        if ( $migrated > 0 ) {
            update_option( 'urst_menu_migration_complete', time() );
        }

        return $migrated;
    }
}
