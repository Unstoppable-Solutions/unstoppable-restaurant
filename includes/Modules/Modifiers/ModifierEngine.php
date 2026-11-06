<?php
/**
 * Modifier Engine.
 *
 * Pure logic class that manages the state of a modifier flow.
 * No rendering, no AJAX - just state transitions and validation.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Modules\Modifiers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ModifierEngine class.
 *
 * @since 2.3.0
 */
class ModifierEngine {

    /**
     * Flow state status constants.
     */
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETE    = 'complete';
    const STATUS_INVALID     = 'invalid';

    /**
     * Array of ModifierGroup objects.
     *
     * @var ModifierGroup[]
     */
    private $groups = array();

    /**
     * Current step index (0-based).
     *
     * @var int
     */
    private $current_step = 0;

    /**
     * Selections keyed by group ID.
     * Structure: [ 'group_id' => ['option_id', ...], ... ]
     *
     * @var array
     */
    private $selections = array();

    /**
     * Special instructions text.
     *
     * @var string
     */
    private $special_instructions = '';

    /**
     * Base product price.
     *
     * @var float
     */
    private $base_price = 0.0;

    /**
     * Constructor.
     *
     * @param array $groups Array of group data or ModifierGroup objects.
     * @param float $base_price Base product price.
     */
    public function __construct( array $groups = array(), float $base_price = 0.0 ) {
        $this->base_price = $base_price;

        foreach ( $groups as $group ) {
            if ( $group instanceof ModifierGroup ) {
                $this->groups[] = $group;
            } else {
                $this->groups[] = new ModifierGroup( $group );
            }
        }

        // Sort by sort_order.
        usort( $this->groups, function( $a, $b ) {
            return $a->sort_order - $b->sort_order;
        } );

        // Initialize selections with defaults.
        $this->initialize_defaults();
    }

    /**
     * Initialize default selections for each group.
     */
    private function initialize_defaults() {
        foreach ( $this->groups as $group ) {
            $this->selections[ $group->id ] = array();

            foreach ( $group->options as $option ) {
                if ( $option->is_default ) {
                    $this->selections[ $group->id ][] = $option->id;

                    // For single type, only one default allowed.
                    if ( ModifierGroup::TYPE_SINGLE === $group->type ) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * Get current step index.
     *
     * @return int
     */
    public function get_current_step() {
        return $this->current_step;
    }

    /**
     * Get total number of steps.
     *
     * @return int
     */
    public function get_total_steps() {
        return count( $this->groups );
    }

    /**
     * Get current group.
     *
     * @return ModifierGroup|null
     */
    public function get_current_group() {
        return $this->groups[ $this->current_step ] ?? null;
    }

    /**
     * Get group by index.
     *
     * @param int $index Step index.
     * @return ModifierGroup|null
     */
    public function get_group( $index ) {
        return $this->groups[ $index ] ?? null;
    }

    /**
     * Get all groups.
     *
     * @return ModifierGroup[]
     */
    public function get_groups() {
        return $this->groups;
    }

    /**
     * Get selections for a group.
     *
     * @param string $group_id Group ID.
     * @return array Option IDs.
     */
    public function get_selections( $group_id ) {
        return $this->selections[ $group_id ] ?? array();
    }

    /**
     * Get all selections.
     *
     * @return array
     */
    public function get_all_selections() {
        return $this->selections;
    }

    /**
     * Set selections for a group.
     *
     * @param string $group_id  Group ID.
     * @param array  $option_ids Array of selected option IDs.
     * @return array Validation result { valid: bool, error: string|null }
     */
    public function set_selections( $group_id, array $option_ids ) {
        $group = $this->find_group( $group_id );

        if ( ! $group ) {
            return array(
                'valid' => false,
                'error' => __( 'Invalid group.', 'unstoppable-restaurant' ),
            );
        }

        // Sanitize option IDs.
        $option_ids = array_map( 'sanitize_key', $option_ids );
        $option_ids = array_filter( $option_ids ); // Remove empty values.

        // Store selections.
        $this->selections[ $group_id ] = array_values( array_unique( $option_ids ) );

        // Validate.
        return $group->validate( $this->selections[ $group_id ] );
    }

    /**
     * Toggle an option (for multiple type groups).
     *
     * @param string $group_id  Group ID.
     * @param string $option_id Option ID.
     * @return array Validation result.
     */
    public function toggle_option( $group_id, $option_id ) {
        $group = $this->find_group( $group_id );

        if ( ! $group ) {
            return array(
                'valid' => false,
                'error' => __( 'Invalid group.', 'unstoppable-restaurant' ),
            );
        }

        $option_id = sanitize_key( $option_id );
        $current   = $this->selections[ $group_id ] ?? array();

        if ( in_array( $option_id, $current, true ) ) {
            // Remove.
            $current = array_diff( $current, array( $option_id ) );
        } else {
            // Add (checking max).
            if ( $group->max_select > 0 && count( $current ) >= $group->max_select ) {
                // At max - for single type, replace; for multiple, block.
                if ( ModifierGroup::TYPE_SINGLE === $group->type ) {
                    $current = array( $option_id );
                } else {
                    return array(
                        'valid' => false,
                        'error' => sprintf(
                            /* translators: %d: maximum selections */
                            __( 'Maximum %d selections allowed.', 'unstoppable-restaurant' ),
                            $group->max_select
                        ),
                    );
                }
            } else {
                $current[] = $option_id;
            }
        }

        $this->selections[ $group_id ] = array_values( $current );

        return $group->validate( $this->selections[ $group_id ] );
    }

    /**
     * Select a single option (for single type groups).
     *
     * @param string $group_id  Group ID.
     * @param string $option_id Option ID.
     * @return array Validation result.
     */
    public function select_option( $group_id, $option_id ) {
        return $this->set_selections( $group_id, array( $option_id ) );
    }

    /**
     * Can proceed to next step?
     *
     * @return bool
     */
    public function can_proceed() {
        $group = $this->get_current_group();
        if ( ! $group ) {
            return true; // No more groups = can proceed (to finalize).
        }

        $validation = $group->validate( $this->selections[ $group->id ] ?? array() );
        return $validation['valid'];
    }

    /**
     * Move to next step.
     *
     * @return array { success: bool, step: int, status: string, error: string|null }
     */
    public function next_step() {
        // Validate current step first.
        $group = $this->get_current_group();

        if ( $group ) {
            $validation = $group->validate( $this->selections[ $group->id ] ?? array() );
            if ( ! $validation['valid'] ) {
                return array(
                    'success' => false,
                    'step'    => $this->current_step,
                    'status'  => self::STATUS_INVALID,
                    'error'   => $validation['error'],
                );
            }
        }

        // Move to next.
        $this->current_step++;

        if ( $this->current_step >= count( $this->groups ) ) {
            return array(
                'success' => true,
                'step'    => $this->current_step,
                'status'  => self::STATUS_COMPLETE,
                'error'   => null,
            );
        }

        return array(
            'success' => true,
            'step'    => $this->current_step,
            'status'  => self::STATUS_IN_PROGRESS,
            'error'   => null,
        );
    }

    /**
     * Move to previous step.
     *
     * @return array { success: bool, step: int }
     */
    public function prev_step() {
        if ( $this->current_step > 0 ) {
            $this->current_step--;
        }

        return array(
            'success' => true,
            'step'    => $this->current_step,
        );
    }

    /**
     * Go to specific step.
     *
     * @param int $step Step index.
     * @return bool
     */
    public function go_to_step( $step ) {
        if ( $step >= 0 && $step < count( $this->groups ) ) {
            $this->current_step = $step;
            return true;
        }
        return false;
    }

    /**
     * Validate entire flow.
     *
     * @return array { valid: bool, errors: array }
     */
    public function validate() {
        $errors = array();

        foreach ( $this->groups as $group ) {
            $validation = $group->validate( $this->selections[ $group->id ] ?? array() );
            if ( ! $validation['valid'] ) {
                $errors[ $group->id ] = $validation['error'];
            }
        }

        return array(
            'valid'  => empty( $errors ),
            'errors' => $errors,
        );
    }

    /**
     * Is the flow complete (all steps validated)?
     *
     * @return bool
     */
    public function is_complete() {
        $validation = $this->validate();
        return $validation['valid'];
    }

    /**
     * Calculate total modifier price.
     *
     * @return float
     */
    public function calculate_modifier_total() {
        $total = 0.0;

        foreach ( $this->groups as $group ) {
            $selections = $this->selections[ $group->id ] ?? array();
            $total += $group->calculate_price( $selections );
        }

        return $total;
    }

    /**
     * Calculate final price (base + modifiers).
     *
     * @return float
     */
    public function calculate_total_price() {
        return $this->base_price + $this->calculate_modifier_total();
    }

    /**
     * Set special instructions.
     *
     * @param string $instructions Text.
     */
    public function set_special_instructions( $instructions ) {
        $this->special_instructions = sanitize_textarea_field( $instructions );
    }

    /**
     * Get special instructions.
     *
     * @return string
     */
    public function get_special_instructions() {
        return $this->special_instructions;
    }

    /**
     * Finalize and get cart item data.
     *
     * Returns normalized, versioned data structure for cart storage.
     *
     * @param int    $flow_id   Optional flow post ID.
     * @param string $flow_name Optional flow name.
     * @return array|false Cart item data or false if invalid.
     */
    public function finalize( $flow_id = 0, $flow_name = '' ) {
        if ( ! $this->is_complete() ) {
            return false;
        }

        // Create normalized cart data.
        $cart_data = ModifierCartData::from_engine( $this, $flow_id, $flow_name );

        return array(
            'ur_modifiers'            => $cart_data->to_array(),
            'ur_special_instructions' => $this->special_instructions,
        );
    }

    /**
     * Legacy finalize for backward compatibility.
     *
     * @deprecated 2.3.1 Use finalize() instead.
     * @return array|false
     */
    public function finalize_legacy() {
        if ( ! $this->is_complete() ) {
            return false;
        }

        return array(
            'ur_modifiers'            => $this->selections,
            'ur_modifier_total'       => $this->calculate_modifier_total(),
            'ur_special_instructions' => $this->special_instructions,
        );
    }

    /**
     * Get kitchen-readable output.
     *
     * @return array Array of strings for kitchen ticket.
     */
    public function get_kitchen_output() {
        $lines = array();

        foreach ( $this->groups as $group ) {
            $selections = $this->selections[ $group->id ] ?? array();
            $group_lines = $group->get_kitchen_text( $selections );
            $lines = array_merge( $lines, $group_lines );
        }

        if ( ! empty( $this->special_instructions ) ) {
            $lines[] = __( 'Note:', 'unstoppable-restaurant' ) . ' ' . $this->special_instructions;
        }

        return $lines;
    }

    /**
     * Get summary for cart display.
     *
     * @return array Array of { label: string, value: string, price: float }
     */
    public function get_summary() {
        $summary = array();

        foreach ( $this->groups as $group ) {
            $selections = $this->selections[ $group->id ] ?? array();
            if ( empty( $selections ) ) {
                continue;
            }

            $labels = array();
            $total_price = 0.0;

            foreach ( $group->options as $option ) {
                if ( in_array( $option->id, $selections, true ) ) {
                    $label = $option->label;
                    if ( abs( $option->price ) > 0.01 ) {
                        $label .= ' (' . $option->get_price_display() . ')';
                    }
                    $labels[] = $label;
                    $total_price += $option->price;
                }
            }

            $summary[] = array(
                'group_id'   => $group->id,
                'group_label' => $group->label,
                'value'      => implode( ', ', $labels ),
                'price'      => $total_price,
            );
        }

        return $summary;
    }

    /**
     * Restore state from stored data.
     *
     * @param array $state Stored state.
     */
    public function restore_state( array $state ) {
        if ( isset( $state['selections'] ) && is_array( $state['selections'] ) ) {
            foreach ( $state['selections'] as $group_id => $option_ids ) {
                if ( isset( $this->selections[ $group_id ] ) ) {
                    $this->selections[ $group_id ] = array_map( 'sanitize_key', (array) $option_ids );
                }
            }
        }

        if ( isset( $state['current_step'] ) ) {
            $this->current_step = absint( $state['current_step'] );
        }

        if ( isset( $state['special_instructions'] ) ) {
            $this->special_instructions = sanitize_textarea_field( $state['special_instructions'] );
        }
    }

    /**
     * Export current state.
     *
     * @return array
     */
    public function export_state() {
        return array(
            'current_step'         => $this->current_step,
            'selections'           => $this->selections,
            'special_instructions' => $this->special_instructions,
        );
    }

    /**
     * Find group by ID.
     *
     * @param string $group_id Group ID.
     * @return ModifierGroup|null
     */
    private function find_group( $group_id ) {
        foreach ( $this->groups as $group ) {
            if ( $group->id === $group_id ) {
                return $group;
            }
        }
        return null;
    }
}
