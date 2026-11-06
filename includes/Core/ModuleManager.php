<?php
/**
 * Module Manager.
 *
 * Handles registration and initialization of plugin modules.
 * Phase 2: Only Layouts module is active.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ModuleManager class.
 *
 * @since 1.0.0
 */
class ModuleManager {

    /**
     * Registered modules.
     *
     * @var array
     */
    private $modules = array();

    /**
     * Module definitions.
     *
     * @var array
     */
    private $module_definitions = array();

    /**
     * Initialize the module manager.
     */
    public function init() {
        // Initialize core rendering components first.
        $this->init_core_components();

        // Define and load modules.
        $this->define_modules();
        $this->load_modules();

        /**
         * Hook for Pro/extension modules to register themselves.
         *
         * Pro modules should use this hook to register additional modules
         * that extend Free functionality. This keeps Free independent.
         *
         * @since 2.5.1
         */
        do_action( 'unstoppable_restaurant_register_pro_modules', $this );

        /**
         * Fires after all modules (Free + Pro) are loaded.
         *
         * @since 2.5.1
         */
        do_action( 'unstoppable_restaurant_modules_loaded' );
    }

    /**
     * Initialize core components needed for layouts.
     */
    private function init_core_components() {
        // Initialize Layout Registry (registers default layouts).
        LayoutRegistry::instance();

        // Initialize Layout Renderer (handles shortcodes and blocks).
        LayoutRenderer::instance()->init();
    }

    /**
     * Define available modules.
     *
     * PHASE 2: Only layouts-related functionality is enabled.
     * Other modules are defined but disabled until their phase.
     */
    private function define_modules() {
        $this->module_definitions = array(
            // PHASE 2: Layout Engine (ACTIVE).
            'layouts' => array(
                'class'    => '\\Unstoppable\\Restaurant\\Modules\\Layouts\\Layouts',
                'priority' => 10,
                'enabled'  => true,  // Phase 2: Active.
            ),

            // PHASE 3: Cart Drawer (ACTIVE).
            'cart-drawer' => array(
                'class'    => '\\Unstoppable\\Restaurant\\Modules\\CartDrawer\\CartDrawer',
                'priority' => 20,
                'enabled'  => true,  // Phase 3: Active.
            ),

            // PHASE 4: Product Modal (ACTIVE).
            'product-modal' => array(
                'class'    => '\\Unstoppable\\Restaurant\\Modules\\ProductModal\\ProductModal',
                'priority' => 30,
                'enabled'  => true,  // Phase 4: Active.
            ),

            // PHASE 5: Conversational Modifiers (ACTIVE).
            'modifiers' => array(
                'class'    => '\\Unstoppable\\Restaurant\\Modules\\Modifiers\\Modifiers',
                'priority' => 40,
                'enabled'  => true,  // Phase 5: Active.
            ),

            // PHASE 6: Basic Scheduling (ACTIVE).
            'scheduling' => array(
                'class'    => '\\Unstoppable\\Restaurant\\Modules\\Scheduling\\Scheduling',
                'priority' => 50,
                'enabled'  => true,  // Phase 6: Active.
            ),

            // PHASE 7: Product Labels (ACTIVE).
            'labels' => array(
                'class'    => '\\Unstoppable\\Restaurant\\Modules\\Labels\\Labels',
                'priority' => 60,
                'enabled'  => true,  // Phase 7: Active.
            ),

            // PHASE 7: Menu Sorting (ACTIVE).
            'sorting' => array(
                'class'    => '\\Unstoppable\\Restaurant\\Modules\\Sorting\\Sorting',
                'priority' => 70,
                'enabled'  => true,  // Phase 7: Active.
            ),
        );

        /**
         * Filter module definitions.
         *
         * @since 1.0.0
         * @param array $module_definitions Module definitions.
         */
        $this->module_definitions = apply_filters( 'urst_module_definitions', $this->module_definitions );
    }

    /**
     * Load and initialize modules.
     */
    private function load_modules() {
        // Sort by priority.
        uasort( $this->module_definitions, function( $a, $b ) {
            return ( $a['priority'] ?? 10 ) - ( $b['priority'] ?? 10 );
        } );

        foreach ( $this->module_definitions as $id => $definition ) {
            if ( empty( $definition['enabled'] ) ) {
                continue;
            }

            $class = $definition['class'];

            if ( ! class_exists( $class ) ) {
                $this->log_error( sprintf( 'Module class not found: %s', $class ) );
                continue;
            }

            try {
                $module = new $class();

                if ( ! $module instanceof ModuleInterface ) {
                    $this->log_error( sprintf( 'Module %s does not implement ModuleInterface', $class ) );
                    continue;
                }

                if ( ! $module->meets_requirements() ) {
                    continue;
                }

                $module->init();
                $this->modules[ $id ] = $module;

                /**
                 * Fires after a module is initialized.
                 *
                 * @since 1.0.0
                 * @param string          $id     Module ID.
                 * @param ModuleInterface $module Module instance.
                 */
                do_action( 'urst_module_initialized', $id, $module );

            } catch ( \Exception $e ) {
                $this->log_error( sprintf( 'Error initializing module %s: %s', $id, $e->getMessage() ) );

                /**
                 * Fires when a module error occurs.
                 *
                 * @since 1.0.0
                 * @param string     $id    Module ID.
                 * @param \Exception $e     Exception.
                 */
                do_action( 'urst_module_error', $id, $e );
            }
        }
    }

    /**
     * Get a specific module.
     *
     * @param string $id Module ID.
     * @return ModuleInterface|null
     */
    public function get( $id ) {
        return isset( $this->modules[ $id ] ) ? $this->modules[ $id ] : null;
    }

    /**
     * Check if a module is active.
     *
     * @param string $id Module ID.
     * @return bool
     */
    public function is_active( $id ) {
        return isset( $this->modules[ $id ] );
    }

    /**
     * Get all active modules.
     *
     * @return array
     */
    public function get_all() {
        return $this->modules;
    }

    /**
     * Register an external module (for Pro/extensions).
     *
     * This allows Pro plugins to register additional modules that
     * integrate with the Free plugin's module system.
     *
     * @since 2.5.1
     * @param string $id         Module ID.
     * @param string $class      Full class name.
     * @param int    $priority   Load priority (higher = later).
     * @return bool True if registered successfully.
     */
    public function register_module( $id, $class, $priority = 100 ) {
        if ( isset( $this->modules[ $id ] ) ) {
            return false; // Already loaded.
        }

        if ( ! class_exists( $class ) ) {
            $this->log_error( sprintf( 'Pro module class not found: %s', $class ) );
            return false;
        }

        try {
            $module = new $class();

            if ( ! $module instanceof ModuleInterface ) {
                $this->log_error( sprintf( 'Pro module %s does not implement ModuleInterface', $class ) );
                return false;
            }

            if ( ! $module->meets_requirements() ) {
                return false;
            }

            $module->init();
            $this->modules[ $id ] = $module;

            /**
             * Fires after a Pro module is registered.
             *
             * @since 2.5.1
             * @param string          $id     Module ID.
             * @param ModuleInterface $module Module instance.
             */
            do_action( 'urst_pro_module_registered', $id, $module );

            return true;

        } catch ( \Exception $e ) {
            $this->log_error( sprintf( 'Error initializing Pro module %s: %s', $id, $e->getMessage() ) );
            return false;
        }
    }

    /**
     * Log an error message.
     *
     * @param string $message Error message.
     */
    private function log_error( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'Unstoppable Restaurant: ' . $message );
        }
    }
}
