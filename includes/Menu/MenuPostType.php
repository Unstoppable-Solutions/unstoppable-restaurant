<?php
/**
 * Menu Post Type Registration.
 *
 * Registers the urst_menu custom post type.
 * Menus are stored as CPT for:
 * - WordPress-native admin integration
 * - Free REST API
 * - Standard data model
 * - Import/export compatibility
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
 * MenuPostType class.
 *
 * @since 2.11.0
 */
class MenuPostType {

    /**
     * Post type slug.
     */
    const POST_TYPE = 'urst_menu';

    /**
     * Singleton instance.
     *
     * @var MenuPostType|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return MenuPostType
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize.
     */
    public function init() {
        add_action( 'init', array( $this, 'register_post_type' ), 5 );
    }

    /**
     * Register the menu post type.
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x( 'Menus', 'Post type general name', 'unstoppable-restaurant' ),
            'singular_name'         => _x( 'Menu', 'Post type singular name', 'unstoppable-restaurant' ),
            'menu_name'             => _x( 'Menus', 'Admin Menu text', 'unstoppable-restaurant' ),
            'add_new'               => __( 'Add New', 'unstoppable-restaurant' ),
            'add_new_item'          => __( 'Add New Menu', 'unstoppable-restaurant' ),
            'edit_item'             => __( 'Edit Menu', 'unstoppable-restaurant' ),
            'new_item'              => __( 'New Menu', 'unstoppable-restaurant' ),
            'view_item'             => __( 'View Menu', 'unstoppable-restaurant' ),
            'search_items'          => __( 'Search Menus', 'unstoppable-restaurant' ),
            'not_found'             => __( 'No menus found', 'unstoppable-restaurant' ),
            'not_found_in_trash'    => __( 'No menus found in Trash', 'unstoppable-restaurant' ),
            'all_items'             => __( 'All Menus', 'unstoppable-restaurant' ),
            'archives'              => __( 'Menu Archives', 'unstoppable-restaurant' ),
            'attributes'            => __( 'Menu Attributes', 'unstoppable-restaurant' ),
            'insert_into_item'      => __( 'Insert into menu', 'unstoppable-restaurant' ),
            'uploaded_to_this_item' => __( 'Uploaded to this menu', 'unstoppable-restaurant' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => false, // We use our own admin page
            'show_in_menu'        => false,
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'supports'            => array( 'title' ),
            'show_in_rest'        => true, // Enable REST API
            'rest_base'           => 'urst-menus',
        );

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Get the post type slug.
     *
     * @return string
     */
    public static function get_post_type() {
        return self::POST_TYPE;
    }
}
