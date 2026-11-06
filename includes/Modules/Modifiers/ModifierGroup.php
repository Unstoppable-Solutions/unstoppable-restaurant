<?php
/**
 * Modifier Group Data Structure.
 *
 * Represents a single step in a modifier flow.
 * Example: "Choose your cheese" with options like American, Cheddar, Swiss.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Modules\Modifiers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ModifierGroup class.
 *
 * @since 2.3.0
 */
class ModifierGroup {

    /**
     * Group types.
     */
    const TYPE_SINGLE   = 'single';   // Radio - exactly one selection.
    const TYPE_MULTIPLE = 'multiple'; // Checkbox - zero or more selections.

    /**
     * Group ID.
     *
     * @var string
     */
    public $id;

    /**
     * Display label (e.g., "Choose your cheese").
     *
     * @var string
     */
    public $label;

    /**
     * Group type: 'single' or 'multiple'.
     *
     * @var string
     */
    public $type;

    /**
     * Whether selection is required to proceed.
     *
     * @var bool
     */
    public $required;

    /**
     * Minimum selections (for multiple type).
     *
     * @var int
     */
    public $min_select;

    /**
     * Maximum selections (for multiple type, 0 = unlimited).
     *
     * @var int
     */
    public $max_select;

    /**
     * Display order in the flow.
     *
     * @var int
     */
    public $sort_order;

    /**
     * Array of ModifierOption objects.
     *
     * @var ModifierOption[]
     */
    public $options = array();

    /**
     * Constructor.
     *
     * @param array $data Group data.
     */
    public function __construct( array $data = array() ) {
        $this->id         = isset( $data['id'] ) ? sanitize_key( $data['id'] ) : $this->generate_id();
        $this->label      = isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : '';
        $this->type       = isset( $data['type'] ) && in_array( $data['type'], array( self::TYPE_SINGLE, self::TYPE_MULTIPLE ), true )
                            ? $data['type'] : self::TYPE_SINGLE;
        $this->required   = ! empty( $data['required'] );
        $this->min_select = isset( $data['min_select'] ) ? absint( $data['min_select'] ) : 0;
        $this->max_select = isset( $data['max_select'] ) ? absint( $data['max_select'] ) : 0;
        $this->sort_order = isset( $data['sort_order'] ) ? absint( $data['sort_order'] ) : 0;

        // Build options.
        if ( ! empty( $data['options'] ) && is_array( $data['options'] ) ) {
            foreach ( $data['options'] as $option_data ) {
                $this->options[] = new ModifierOption( $option_data );
            }
        }
    }

    /**
     * Generate unique ID.
     *
     * @return string
     */
    private function generate_id() {
        return 'grp_' . substr( md5( uniqid( '', true ) ), 0, 8 );
    }

    /**
     * Validate selections against this group's rules.
     *
     * @param array $selections Selected option IDs.
     * @return array { valid: bool, error: string|null }
     */
    public function validate( array $selections ) {
        $count = count( $selections );

        // Required check.
        if ( $this->required && $count === 0 ) {
            return array(
                'valid' => false,
                'error' => sprintf(
                    /* translators: %s: group label */
                    __( 'Please make a selection for "%s".', 'unstoppable-restaurant' ),
                    $this->label
                ),
            );
        }

        // Single type: must have exactly 0 or 1 selection.
        if ( self::TYPE_SINGLE === $this->type && $count > 1 ) {
            return array(
                'valid' => false,
                'error' => sprintf(
                    /* translators: %s: group label */
                    __( 'Please select only one option for "%s".', 'unstoppable-restaurant' ),
                    $this->label
                ),
            );
        }

        // Multiple type: check min/max.
        if ( self::TYPE_MULTIPLE === $this->type ) {
            if ( $this->min_select > 0 && $count < $this->min_select ) {
                return array(
                    'valid' => false,
                    'error' => sprintf(
                        /* translators: 1: minimum count, 2: group label */
                        __( 'Please select at least %1$d option(s) for "%2$s".', 'unstoppable-restaurant' ),
                        $this->min_select,
                        $this->label
                    ),
                );
            }

            if ( $this->max_select > 0 && $count > $this->max_select ) {
                return array(
                    'valid' => false,
                    'error' => sprintf(
                        /* translators: 1: maximum count, 2: group label */
                        __( 'Please select no more than %1$d option(s) for "%2$s".', 'unstoppable-restaurant' ),
                        $this->max_select,
                        $this->label
                    ),
                );
            }
        }

        // Verify all selected IDs exist.
        $valid_ids = array_column( $this->options, 'id' );
        foreach ( $selections as $selection_id ) {
            if ( ! in_array( $selection_id, $valid_ids, true ) ) {
                return array(
                    'valid' => false,
                    'error' => __( 'Invalid selection.', 'unstoppable-restaurant' ),
                );
            }
        }

        return array( 'valid' => true, 'error' => null );
    }

    /**
     * Calculate total price for selections.
     *
     * @param array $selections Selected option IDs.
     * @return float
     */
    public function calculate_price( array $selections ) {
        $total = 0.0;

        foreach ( $this->options as $option ) {
            if ( in_array( $option->id, $selections, true ) ) {
                $total += $option->price;
            }
        }

        return $total;
    }

    /**
     * Get kitchen-readable text for selections.
     *
     * @param array $selections Selected option IDs.
     * @return array Array of strings for kitchen ticket.
     */
    public function get_kitchen_text( array $selections ) {
        $lines = array();

        foreach ( $this->options as $option ) {
            if ( in_array( $option->id, $selections, true ) ) {
                $text = $option->kitchen_label ?: $option->label;
                if ( ! empty( $text ) ) {
                    $lines[] = $text;
                }
            }
        }

        return $lines;
    }

    /**
     * Get option by ID.
     *
     * @param string $option_id Option ID.
     * @return ModifierOption|null
     */
    public function get_option( $option_id ) {
        foreach ( $this->options as $option ) {
            if ( $option->id === $option_id ) {
                return $option;
            }
        }
        return null;
    }

    /**
     * Convert to array for storage/JSON.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id'         => $this->id,
            'label'      => $this->label,
            'type'       => $this->type,
            'required'   => $this->required,
            'min_select' => $this->min_select,
            'max_select' => $this->max_select,
            'sort_order' => $this->sort_order,
            'options'    => array_map( function( $opt ) {
                return $opt->to_array();
            }, $this->options ),
        );
    }
}
