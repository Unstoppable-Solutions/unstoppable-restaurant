<?php
/**
 * Template Loader.
 *
 * Handles loading templates with theme override support.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TemplateLoader class.
 *
 * @since 1.0.0
 */
class TemplateLoader {

    /**
     * Instance.
     *
     * @var TemplateLoader|null
     */
    private static $instance = null;

    /**
     * Theme template directory.
     *
     * @var string
     */
    private $theme_template_dir = 'unstoppable-restaurant';

    /**
     * Get instance.
     *
     * @return TemplateLoader
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize template loader.
     */
    public function init() {
        // Nothing to hook yet, but available for future use.
    }

    /**
     * Locate a template file.
     *
     * Looks in:
     * 1. yourtheme/unstoppable-restaurant/
     * 2. plugin/templates/
     *
     * @param string $template_name Template name.
     * @return string Template path.
     */
    public function locate( $template_name ) {
        // Theme override path.
        $theme_template = locate_template( array(
            trailingslashit( $this->theme_template_dir ) . $template_name,
        ) );

        if ( $theme_template ) {
            /**
             * Filter located template path.
             *
             * @since 1.0.0
             * @param string $theme_template  Located template path.
             * @param string $template_name   Template name.
             */
            return apply_filters( 'urst_locate_template', $theme_template, $template_name );
        }

        // Plugin default path.
        $plugin_template = URST_PLUGIN_DIR . 'templates/' . $template_name;

        return apply_filters( 'urst_locate_template', $plugin_template, $template_name );
    }

    /**
     * Get a template.
     *
     * @param string $template_name Template name.
     * @param array  $args          Template arguments.
     * @param bool   $return        Whether to return or echo.
     * @return string|void
     */
    public function get( $template_name, $args = array(), $return = false ) {
        $template_path = $this->locate( $template_name );

        if ( ! file_exists( $template_path ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( sprintf( 'Unstoppable Restaurant: Template not found: %s', $template_name ) );
            }
            return $return ? '' : null;
        }

        /**
         * Fires before a template is loaded.
         *
         * @since 1.0.0
         * @param string $template_name Template name.
         * @param array  $args          Template arguments.
         */
        do_action( 'urst_before_template', $template_name, $args );

        if ( $return ) {
            ob_start();
        }

        // Extract args for template use.
        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract( $args, EXTR_SKIP );

        include $template_path;

        /**
         * Fires after a template is loaded.
         *
         * @since 1.0.0
         * @param string $template_name Template name.
         * @param array  $args          Template arguments.
         */
        do_action( 'urst_after_template', $template_name, $args );

        if ( $return ) {
            return ob_get_clean();
        }
    }

    /**
     * Get a template part.
     *
     * @param string $slug Template slug.
     * @param string $name Template name (optional).
     * @param array  $args Template arguments.
     */
    public function get_part( $slug, $name = '', $args = array() ) {
        $template_name = $slug;

        if ( $name ) {
            $template_name = "{$slug}-{$name}";
        }

        $template_name .= '.php';

        $this->get( $template_name, $args );
    }
}
