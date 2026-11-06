<?php
/**
 * Module Interface.
 *
 * Defines the contract all modules must implement.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Module Interface.
 *
 * @since 1.0.0
 */
interface ModuleInterface {

    /**
     * Get module ID.
     *
     * @return string
     */
    public function get_id();

    /**
     * Get module name.
     *
     * @return string
     */
    public function get_name();

    /**
     * Check if requirements are met.
     *
     * @return bool
     */
    public function meets_requirements();

    /**
     * Initialize the module.
     */
    public function init();
}
