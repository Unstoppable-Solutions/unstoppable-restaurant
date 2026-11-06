<?php
/**
 * Layouts Module.
 *
 * Handles custom CSS output for layouts.
 * Rendering is delegated to Core\LayoutRenderer.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Modules\Layouts;

use Unstoppable\Restaurant\Core\ModuleInterface;
use Unstoppable\Restaurant\Admin\Settings;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Layouts class.
 *
 * @since 1.0.0
 */
class Layouts implements ModuleInterface {

    /**
     * Get module ID.
     *
     * @return string
     */
    public function get_id() {
        return 'layouts';
    }

    /**
     * Get module name.
     *
     * @return string
     */
    public function get_name() {
        return __( 'Layouts', 'unstoppable-restaurant' );
    }

    /**
     * Check requirements.
     *
     * @return bool
     */
    public function meets_requirements() {
        return true;
    }

    /**
     * Initialize the module.
     */
    public function init() {
        // Output custom CSS variables.
        add_action( 'wp_head', array( $this, 'output_custom_css' ), 100 );
    }

    /**
     * Output custom CSS variables from settings.
     */
    public function output_custom_css() {
        $settings = Settings::instance();
        $primary  = $settings->get( 'primary_color', '#2563eb' );
        $accent   = $settings->get( 'accent_color', '#059669' );

        ?>
        <style id="urst-custom-css">
            :root {
                --ur-color-primary: <?php echo esc_attr( $primary ); ?>;
                --ur-color-primary-hover: <?php echo esc_attr( $this->adjust_brightness( $primary, -15 ) ); ?>;
                --ur-color-primary-light: <?php echo esc_attr( $this->hex_to_rgba( $primary, 0.1 ) ); ?>;
                --ur-color-accent: <?php echo esc_attr( $accent ); ?>;
                --ur-color-accent-hover: <?php echo esc_attr( $this->adjust_brightness( $accent, -15 ) ); ?>;
            }
        </style>
        <?php
    }

    /**
     * Adjust color brightness.
     *
     * @param string $hex    Hex color.
     * @param int    $amount Amount to adjust (-100 to 100).
     * @return string
     */
    private function adjust_brightness( $hex, $amount ) {
        $hex = ltrim( $hex, '#' );

        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = max( 0, min( 255, hexdec( substr( $hex, 0, 2 ) ) + $amount ) );
        $g = max( 0, min( 255, hexdec( substr( $hex, 2, 2 ) ) + $amount ) );
        $b = max( 0, min( 255, hexdec( substr( $hex, 4, 2 ) ) + $amount ) );

        return '#' . sprintf( '%02x%02x%02x', $r, $g, $b );
    }

    /**
     * Convert hex to rgba.
     *
     * @param string $hex   Hex color.
     * @param float  $alpha Alpha value (0-1).
     * @return string
     */
    private function hex_to_rgba( $hex, $alpha = 1 ) {
        $hex = ltrim( $hex, '#' );

        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );

        return sprintf( 'rgba(%d, %d, %d, %s)', $r, $g, $b, $alpha );
    }
}
