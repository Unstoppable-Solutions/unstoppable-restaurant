<?php
/**
 * Modifier Cart Data Structure.
 *
 * Normalizes modifier data for cart storage with versioning
 * for backward compatibility and safe migrations.
 *
 * @package Unstoppable_Restaurant
 */

namespace Unstoppable\Restaurant\Modules\Modifiers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ModifierCartData class.
 *
 * @since 2.3.1
 */
class ModifierCartData {

    /**
     * Data structure version.
     * Increment when structure changes.
     */
    const VERSION = '1.0';

    /**
     * Flow ID.
     *
     * @var int
     */
    public $flow_id = 0;

    /**
     * Flow name (for display if flow is deleted).
     *
     * @var string
     */
    public $flow_name = '';

    /**
     * Groups with selections.
     *
     * @var array
     */
    public $groups = array();

    /**
     * Special instructions.
     *
     * @var string
     */
    public $special_instructions = '';

    /**
     * Total modifier price.
     *
     * @var float
     */
    public $modifier_total = 0.0;

    /**
     * Timestamp when data was created.
     *
     * @var int
     */
    public $created_at = 0;

    /**
     * Create from ModifierEngine.
     *
     * @param ModifierEngine $engine Engine with selections.
     * @param int            $flow_id Flow post ID.
     * @param string         $flow_name Flow name.
     * @return self
     */
    public static function from_engine( ModifierEngine $engine, $flow_id = 0, $flow_name = '' ) {
        $instance = new self();

        $instance->flow_id   = absint( $flow_id );
        $instance->flow_name = sanitize_text_field( $flow_name );
        $instance->special_instructions = $engine->get_special_instructions();
        $instance->modifier_total = $engine->calculate_modifier_total();
        $instance->created_at = time();

        // Build normalized group data.
        foreach ( $engine->get_groups() as $group ) {
            $selections = $engine->get_selections( $group->id );

            if ( empty( $selections ) ) {
                continue; // Skip groups with no selections.
            }

            $group_data = array(
                'group_id' => $group->id,
                'label'    => $group->label,
                'type'     => $group->type,
                'options'  => array(),
            );

            foreach ( $group->options as $option ) {
                if ( in_array( $option->id, $selections, true ) ) {
                    $group_data['options'][] = array(
                        'id'            => $option->id,
                        'label'         => $option->label,
                        'price'         => $option->price,
                        'kitchen_label' => $option->kitchen_label ?: $option->label,
                        'qty'           => 1, // Reserved for future quantity-based modifiers.
                    );
                }
            }

            if ( ! empty( $group_data['options'] ) ) {
                $instance->groups[] = $group_data;
            }
        }

        return $instance;
    }

    /**
     * Create from stored array data.
     *
     * Handles backward compatibility with older data structures.
     *
     * @param array $data Stored data.
     * @return self
     */
    public static function from_array( array $data ) {
        $instance = new self();

        // Check for versioned structure.
        if ( isset( $data['version'] ) ) {
            // New versioned format.
            $instance->flow_id              = isset( $data['flow_id'] ) ? absint( $data['flow_id'] ) : 0;
            $instance->flow_name            = isset( $data['flow_name'] ) ? sanitize_text_field( $data['flow_name'] ) : '';
            $instance->groups               = isset( $data['groups'] ) && is_array( $data['groups'] ) ? $data['groups'] : array();
            $instance->special_instructions = isset( $data['special_instructions'] ) ? sanitize_textarea_field( $data['special_instructions'] ) : '';
            $instance->modifier_total       = isset( $data['modifier_total'] ) ? floatval( $data['modifier_total'] ) : 0.0;
            $instance->created_at           = isset( $data['created_at'] ) ? absint( $data['created_at'] ) : 0;
        } else {
            // Legacy format: { group_id: [option_ids], ... }
            $instance = self::from_legacy( $data );
        }

        return $instance;
    }

    /**
     * Convert legacy format to normalized structure.
     *
     * Legacy format was: { 'grp_xxx': ['opt_yyy', 'opt_zzz'], ... }
     *
     * @param array $legacy_data Legacy selections data.
     * @return self
     */
    private static function from_legacy( array $legacy_data ) {
        $instance = new self();
        $instance->created_at = time();

        // We can't fully reconstruct without the flow, but we store what we have.
        foreach ( $legacy_data as $group_id => $option_ids ) {
            if ( ! is_array( $option_ids ) ) {
                $option_ids = array( $option_ids );
            }

            $group_data = array(
                'group_id' => sanitize_key( $group_id ),
                'label'    => '', // Unknown in legacy.
                'type'     => 'single',
                'options'  => array(),
            );

            foreach ( $option_ids as $option_id ) {
                $group_data['options'][] = array(
                    'id'            => sanitize_key( $option_id ),
                    'label'         => '', // Unknown in legacy.
                    'price'         => 0,  // Unknown in legacy.
                    'kitchen_label' => '',
                    'qty'           => 1,
                );
            }

            if ( ! empty( $group_data['options'] ) ) {
                $instance->groups[] = $group_data;
            }
        }

        return $instance;
    }

    /**
     * Convert to array for storage.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'version'              => self::VERSION,
            'flow_id'              => $this->flow_id,
            'flow_name'            => $this->flow_name,
            'groups'               => $this->groups,
            'special_instructions' => $this->special_instructions,
            'modifier_total'       => $this->modifier_total,
            'created_at'           => $this->created_at,
        );
    }

    /**
     * Get kitchen-readable output.
     *
     * This works even if the modifier flow has been deleted.
     *
     * @return array Array of strings for kitchen ticket.
     */
    public function get_kitchen_output() {
        $lines = array();

        foreach ( $this->groups as $group ) {
            foreach ( $group['options'] as $option ) {
                $text = ! empty( $option['kitchen_label'] ) ? $option['kitchen_label'] : $option['label'];
                if ( ! empty( $text ) ) {
                    // Include quantity if > 1.
                    if ( isset( $option['qty'] ) && $option['qty'] > 1 ) {
                        $text = $option['qty'] . 'x ' . $text;
                    }
                    $lines[] = $text;
                }
            }
        }

        if ( ! empty( $this->special_instructions ) ) {
            $lines[] = __( 'Note:', 'unstoppable-restaurant' ) . ' ' . $this->special_instructions;
        }

        return $lines;
    }

    /**
     * Get display summary for cart/checkout.
     *
     * @return array Array of [ 'key' => group_label, 'value' => options_string ]
     */
    public function get_display_summary() {
        $summary = array();

        foreach ( $this->groups as $group ) {
            $labels = array();

            foreach ( $group['options'] as $option ) {
                $label = $option['label'];

                if ( ! empty( $option['price'] ) && abs( $option['price'] ) > 0.01 ) {
                    $prefix = $option['price'] > 0 ? '+' : '';
                    $label .= ' (' . $prefix . wc_price( $option['price'] ) . ')';
                }

                if ( isset( $option['qty'] ) && $option['qty'] > 1 ) {
                    $label = $option['qty'] . 'x ' . $label;
                }

                $labels[] = $label;
            }

            if ( ! empty( $labels ) ) {
                $summary[] = array(
                    'key'   => ! empty( $group['label'] ) ? $group['label'] : __( 'Options', 'unstoppable-restaurant' ),
                    'value' => implode( ', ', $labels ),
                );
            }
        }

        return $summary;
    }

    /**
     * Recalculate modifier total from stored options.
     *
     * Useful when prices may have changed.
     *
     * @return float
     */
    public function recalculate_total() {
        $total = 0.0;

        foreach ( $this->groups as $group ) {
            foreach ( $group['options'] as $option ) {
                $qty = isset( $option['qty'] ) ? $option['qty'] : 1;
                $total += floatval( $option['price'] ) * $qty;
            }
        }

        $this->modifier_total = $total;
        return $total;
    }

    /**
     * Check if this cart data is empty.
     *
     * @return bool
     */
    public function is_empty() {
        return empty( $this->groups );
    }

    /**
     * Check if data is from legacy format.
     *
     * Legacy data may be missing labels and prices.
     *
     * @return bool
     */
    public function is_legacy() {
        if ( empty( $this->groups ) ) {
            return false;
        }

        // Check if first option has a label.
        $first_group = $this->groups[0];
        if ( ! empty( $first_group['options'][0] ) ) {
            return empty( $first_group['options'][0]['label'] );
        }

        return false;
    }

    /**
     * Attempt to enrich legacy data with current flow definitions.
     *
     * @param int $product_id Product ID to look up flow.
     * @return bool True if enriched, false if flow not found.
     */
    public function enrich_from_product( $product_id ) {
        $flow = ModifierRepository::get_flow_for_product( $product_id );

        if ( ! $flow || empty( $flow['groups'] ) ) {
            return false;
        }

        $this->flow_id   = $flow['id'];
        $this->flow_name = $flow['name'];

        // Build lookup maps.
        $group_map = array();
        foreach ( $flow['groups'] as $group_data ) {
            $group_map[ $group_data['id'] ] = $group_data;
        }

        // Enrich our groups.
        foreach ( $this->groups as $key => $group ) {
            $flow_group = $group_map[ $group['group_id'] ] ?? null;

            if ( $flow_group ) {
                $this->groups[ $key ]['label'] = $flow_group['label'];
                $this->groups[ $key ]['type']  = $flow_group['type'];

                // Build option map.
                $option_map = array();
                foreach ( $flow_group['options'] as $opt ) {
                    $option_map[ $opt['id'] ] = $opt;
                }

                // Enrich options.
                foreach ( $this->groups[ $key ]['options'] as $opt_key => $option ) {
                    $flow_option = $option_map[ $option['id'] ] ?? null;

                    if ( $flow_option ) {
                        $this->groups[ $key ]['options'][ $opt_key ]['label']         = $flow_option['label'];
                        $this->groups[ $key ]['options'][ $opt_key ]['price']         = $flow_option['price'];
                        $this->groups[ $key ]['options'][ $opt_key ]['kitchen_label'] = $flow_option['kitchen_label'] ?: $flow_option['label'];
                    }
                }
            }
        }

        $this->recalculate_total();

        return true;
    }
}
