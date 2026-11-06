<?php
/**
 * Menu HTML Renderer.
 *
 * THE ONLY CLASS THAT RENDERS HTML FROM MENU DATA.
 *
 * This is the boundary between:
 * - Data (MenuPayloadBuilder)
 * - Presentation (templates)
 *
 * MenuPayloadBuilder builds payloads. This class renders them.
 * Nothing else should touch templates for menu output.
 *
 * @package Unstoppable_Restaurant
 * @since 2.10.0
 */

namespace Unstoppable\Restaurant\Menu;

use Unstoppable\Restaurant\Menu\Data\MenuPayload;
use Unstoppable\Restaurant\Core\LayoutRegistry;
use Unstoppable\Restaurant\Core\TemplateLoader;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MenuHtmlRenderer class.
 *
 * @since 2.10.0
 */
class MenuHtmlRenderer {

    /**
     * Render a menu to HTML.
     *
     * This is the single entry point for menu HTML output.
     *
     * @param MenuDefinition $menu    Menu definition.
     * @param string         $channel Channel: web | qr | kiosk | pos
     * @return string Rendered HTML.
     */
    public static function render( MenuDefinition $menu, $channel = 'web' ) {
        // Check visibility.
        if ( ! $menu->is_visible_to_current_user() ) {
            return '';
        }

        // Check rules engine (Phase 7.1).
        $rules_engine = MenuRulesEngine::instance();
        if ( ! $rules_engine->is_active( $menu, array( 'channel' => $channel ) ) ) {
            /**
             * Filter what to show when menu is inactive due to rules.
             *
             * @since 2.13.0
             * @param string         $output Empty by default.
             * @param MenuDefinition $menu   The inactive menu.
             * @param string         $reason Why it's inactive.
             */
            $reason = $rules_engine->get_inactive_reason( $menu );
            return apply_filters( 'urst_menu_inactive_output', '', $menu, $reason );
        }

        // Build data payload via MenuPayloadBuilder (THE BRAIN).
        $payload = MenuPayloadBuilder::build( $menu, $channel );

        // Handle empty state.
        if ( $payload->is_empty() ) {
            return self::render_empty_state( $menu );
        }

        // Get layout configuration.
        $layout_id = self::resolve_layout( $menu, $payload );
        $layout = LayoutRegistry::instance()->get( $layout_id );
        $template = $layout['template'];

        // Build template variables.
        $template_vars = self::build_template_vars( $menu, $payload, $layout_id );

        /**
         * Filter template variables before rendering.
         *
         * @since 2.10.0
         * @param array          $template_vars Template variables.
         * @param MenuDefinition $menu          Menu definition.
         * @param MenuPayload    $payload       Full data payload.
         */
        $template_vars = apply_filters( 'urst_menu_template_vars', $template_vars, $menu, $payload );

        // Render template.
        return TemplateLoader::instance()->get( $template, $template_vars, true );
    }

    /**
     * Render menu from a pre-built payload.
     *
     * Use this when you already have a MenuPayload (e.g., from cache or API).
     *
     * @param MenuPayload $payload   Menu payload.
     * @param string      $layout_id Layout ID override (optional).
     * @return string Rendered HTML.
     */
    public static function render_from_payload( MenuPayload $payload, $layout_id = '' ) {
        // Handle empty state.
        if ( $payload->is_empty() ) {
            return self::render_empty_payload();
        }

        // Resolve layout.
        if ( empty( $layout_id ) ) {
            $layout_id = $payload->get_layout();
        }

        $registry = LayoutRegistry::instance();
        if ( ! $registry->exists( $layout_id ) ) {
            $layout_id = $registry->get_default();
        }

        $layout = $registry->get( $layout_id );
        $template = $layout['template'];

        // Build template variables from payload.
        $template_vars = self::build_template_vars_from_payload( $payload, $layout_id );

        // Render template.
        return TemplateLoader::instance()->get( $template, $template_vars, true );
    }

    /**
     * Resolve which layout to use.
     *
     * Priority: global settings > payload settings > menu definition > fallback
     *
     * @param MenuDefinition $menu    Menu definition.
     * @param MenuPayload    $payload Menu payload.
     * @return string Layout ID.
     */
    private static function resolve_layout( MenuDefinition $menu, MenuPayload $payload = null ) {
        $registry = LayoutRegistry::instance();
        $settings = get_option( 'urst_settings', array() );

        // Start with menu's layout.
        $layout_id = $menu->get_layout();

        // Payload settings take precedence (already merged from global).
        if ( $payload ) {
            $payload_layout = $payload->get_setting( 'layout' );
            if ( $payload_layout && $registry->exists( $payload_layout ) ) {
                $layout_id = $payload_layout;
            }
        }

        // Validate layout exists.
        if ( ! $registry->exists( $layout_id ) ) {
            $layout_id = $registry->get_default();
        }

        return $layout_id;
    }

    /**
     * Build template variables from menu definition and payload.
     *
     * @param MenuDefinition $menu      Menu definition.
     * @param MenuPayload    $payload   Menu payload.
     * @param string         $layout_id Layout ID.
     * @return array Template variables.
     */
    private static function build_template_vars( MenuDefinition $menu, MenuPayload $payload, $layout_id ) {
        // Get settings from payload (already merged global + menu settings).
        $menu_settings = $payload->get_menu_settings();

        // Phase 7.3: Use display groups (sections if present, otherwise categories).
        // The 'categories' key is kept for backward compatibility with existing templates.
        $display_groups = $payload->get_display_groups();

        return array(
            'menu_id'           => $menu->get_id(),
            'menu_slug'         => $menu->get_slug(),
            'layout'            => $layout_id,
            'layout_id'         => $layout_id, // Alias for backward compat.
            'categories'        => $display_groups, // Phase 7.3: Now uses sections if present.
            'has_sections'      => $payload->has_sections(), // Phase 7.3: Flag for templates.
            'columns'           => $menu_settings['columns'] ?? 3,
            'show_images'       => $menu_settings['show_images'] ?? true,
            'show_prices'       => $menu_settings['show_prices'] ?? true,
            'show_descriptions' => $menu_settings['show_descriptions'] ?? true,
            'settings'          => $menu_settings,
            // Full payload for advanced templates.
            'payload'           => $payload,
        );
    }

    /**
     * Build template variables from payload only.
     *
     * Used when rendering from a pre-built payload without MenuDefinition.
     *
     * @param MenuPayload $payload   Menu payload.
     * @param string      $layout_id Layout ID.
     * @return array Template variables.
     */
    private static function build_template_vars_from_payload( MenuPayload $payload, $layout_id ) {
        $menu_settings = $payload->get_menu_settings();

        // Phase 7.3: Use display groups (sections if present, otherwise categories).
        $display_groups = $payload->get_display_groups();

        return array(
            'menu_id'           => $payload->get_menu_id(),
            'menu_slug'         => $payload->get_menu_slug(),
            'layout'            => $layout_id,
            'layout_id'         => $layout_id, // Alias.
            'categories'        => $display_groups, // Phase 7.3: Now uses sections if present.
            'has_sections'      => $payload->has_sections(), // Phase 7.3: Flag for templates.
            'columns'           => $menu_settings['columns'] ?? 3,
            'show_images'       => $menu_settings['show_images'] ?? true,
            'show_prices'       => $menu_settings['show_prices'] ?? true,
            'show_descriptions' => $menu_settings['show_descriptions'] ?? true,
            'settings'          => $menu_settings,
            // Full payload for advanced templates.
            'payload'           => $payload,
        );
    }

    /**
     * Render empty state.
     *
     * @param MenuDefinition $menu Menu definition.
     * @return string
     */
    private static function render_empty_state( MenuDefinition $menu ) {
        $template_vars = array(
            'menu_id'   => $menu->get_id(),
            'menu_slug' => $menu->get_slug(),
        );

        return TemplateLoader::instance()->get( 'layouts/empty.php', $template_vars, true );
    }

    /**
     * Render empty state from payload.
     *
     * @return string
     */
    private static function render_empty_payload() {
        return TemplateLoader::instance()->get( 'layouts/empty.php', array(), true );
    }
}
