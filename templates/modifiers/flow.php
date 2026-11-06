<?php
/**
 * Template: Modifier Flow
 *
 * Step-by-step modifier selection inside the product modal.
 *
 * This template can be overridden by copying it to:
 * yourtheme/unstoppable-restaurant/modifiers/flow.php
 *
 * @package Unstoppable_Restaurant
 *
 * @var array $flow    Flow data with groups.
 * @var array $product Product data.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $flow['groups'] ) ) {
    return;
}

$total_steps = count( $flow['groups'] );

// Encode flow definition for JS to build normalized structure.
$flow_json = wp_json_encode( array(
    'id'     => $flow['id'],
    'name'   => $flow['name'] ?? '',
    'groups' => $flow['groups'],
) );
?>

<div 
    class="ur-modifier-flow" 
    data-ur-modifier-flow
    data-flow-id="<?php echo esc_attr( $flow['id'] ); ?>"
    data-flow-name="<?php echo esc_attr( $flow['name'] ?? '' ); ?>"
    data-product-id="<?php echo esc_attr( $product['id'] ); ?>"
    data-total-steps="<?php echo esc_attr( $total_steps ); ?>"
    data-flow-definition="<?php echo esc_attr( $flow_json ); ?>"
>
    <?php // Progress Indicator ?>
    <?php if ( $total_steps > 1 ) : ?>
        <div class="ur-modifier-flow__progress">
            <div class="ur-modifier-flow__progress-bar">
                <div 
                    class="ur-modifier-flow__progress-fill" 
                    data-ur-progress-fill
                    style="width: <?php echo esc_attr( ( 1 / $total_steps ) * 100 ); ?>%"
                ></div>
            </div>
            <span class="ur-modifier-flow__progress-text" data-ur-progress-text>
                <?php
                printf(
                    /* translators: 1: current step, 2: total steps */
                    esc_html__( 'Step %1$d of %2$d', 'unstoppable-restaurant' ),
                    1,
                    $total_steps
                );
                ?>
            </span>
        </div>
    <?php endif; ?>

    <?php // Step Container ?>
    <div class="ur-modifier-flow__steps" data-ur-steps-container>
        <?php foreach ( $flow['groups'] as $index => $group ) : ?>
            <div 
                class="ur-modifier-step<?php echo 0 === $index ? ' is-active' : ''; ?>" 
                data-ur-step="<?php echo esc_attr( $index ); ?>"
                data-group-id="<?php echo esc_attr( $group['id'] ); ?>"
                data-group-type="<?php echo esc_attr( $group['type'] ?? 'single' ); ?>"
                data-required="<?php echo ! empty( $group['required'] ) ? 'true' : 'false'; ?>"
                data-min-select="<?php echo esc_attr( $group['min_select'] ?? 0 ); ?>"
                data-max-select="<?php echo esc_attr( $group['max_select'] ?? 0 ); ?>"
                <?php echo 0 !== $index ? 'hidden' : ''; ?>
            >
                <?php // Group Label ?>
                <h3 class="ur-modifier-step__title">
                    <?php echo esc_html( $group['label'] ); ?>
                    <?php if ( ! empty( $group['required'] ) ) : ?>
                        <span class="ur-modifier-step__required">
                            <?php esc_html_e( 'Required', 'unstoppable-restaurant' ); ?>
                        </span>
                    <?php endif; ?>
                </h3>

                <?php // Selection Hint ?>
                <?php if ( 'multiple' === ( $group['type'] ?? 'single' ) ) : ?>
                    <p class="ur-modifier-step__hint">
                        <?php
                        $min = $group['min_select'] ?? 0;
                        $max = $group['max_select'] ?? 0;

                        if ( $min > 0 && $max > 0 ) {
                            printf(
                                /* translators: 1: min, 2: max */
                                esc_html__( 'Select %1$d to %2$d options', 'unstoppable-restaurant' ),
                                $min,
                                $max
                            );
                        } elseif ( $min > 0 ) {
                            printf(
                                /* translators: %d: minimum selections */
                                esc_html__( 'Select at least %d', 'unstoppable-restaurant' ),
                                $min
                            );
                        } elseif ( $max > 0 ) {
                            printf(
                                /* translators: %d: maximum selections */
                                esc_html__( 'Select up to %d', 'unstoppable-restaurant' ),
                                $max
                            );
                        } else {
                            esc_html_e( 'Select any that apply', 'unstoppable-restaurant' );
                        }
                        ?>
                    </p>
                <?php endif; ?>

                <?php // Options ?>
                <div class="ur-modifier-options">
                    <?php foreach ( $group['options'] ?? array() as $option ) : ?>
                        <?php
                        $input_type = ( 'single' === ( $group['type'] ?? 'single' ) ) ? 'radio' : 'checkbox';
                        $input_name = 'ur_modifier_' . $group['id'];
                        if ( 'checkbox' === $input_type ) {
                            $input_name .= '[]';
                        }
                        $is_default = ! empty( $option['is_default'] ) || ! empty( $option['default'] );
                        ?>
                        <label class="ur-modifier-option<?php echo $is_default ? ' is-selected' : ''; ?>">
                            <input 
                                type="<?php echo esc_attr( $input_type ); ?>"
                                name="<?php echo esc_attr( $input_name ); ?>"
                                value="<?php echo esc_attr( $option['id'] ); ?>"
                                data-price="<?php echo esc_attr( $option['price'] ?? 0 ); ?>"
                                <?php checked( $is_default ); ?>
                            />
                            <span class="ur-modifier-option__check">
                                <?php if ( 'radio' === $input_type ) : ?>
                                    <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20" aria-hidden="true">
                                        <circle cx="12" cy="12" r="8"/>
                                    </svg>
                                <?php else : ?>
                                    <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20" aria-hidden="true">
                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                    </svg>
                                <?php endif; ?>
                            </span>
                            <span class="ur-modifier-option__content">
                                <span class="ur-modifier-option__label">
                                    <?php echo esc_html( $option['label'] ); ?>
                                </span>
                                <?php if ( ! empty( $option['price'] ) && abs( $option['price'] ) > 0.01 ) : ?>
                                    <span class="ur-modifier-option__price">
                                        <?php echo $option['price'] > 0 ? '+' : ''; ?><?php echo wp_kses_post( wc_price( $option['price'] ) ); ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <?php // Error Message ?>
                <div class="ur-modifier-step__error" data-ur-step-error hidden></div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php // Navigation ?>
    <div class="ur-modifier-flow__nav" data-ur-flow-nav>
        <button 
            type="button" 
            class="ur-btn ur-btn--ghost"
            data-ur-flow-prev
            hidden
        >
            <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16" aria-hidden="true">
                <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd"/>
            </svg>
            <?php esc_html_e( 'Back', 'unstoppable-restaurant' ); ?>
        </button>

        <button 
            type="button" 
            class="ur-btn ur-btn--primary"
            data-ur-flow-next
        >
            <?php esc_html_e( 'Next', 'unstoppable-restaurant' ); ?>
            <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16" aria-hidden="true">
                <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd"/>
            </svg>
        </button>
    </div>

    <?php // Running Total Preview ?>
    <div class="ur-modifier-flow__summary" data-ur-flow-summary>
        <div class="ur-modifier-flow__summary-label">
            <?php esc_html_e( 'Your selections', 'unstoppable-restaurant' ); ?>
        </div>
        <div class="ur-modifier-flow__summary-items" data-ur-summary-items>
            <!-- Populated by JS -->
        </div>
    </div>

    <?php // Hidden inputs to store state ?>
    <input type="hidden" name="ur_flow_id" value="<?php echo esc_attr( $flow['id'] ); ?>" />
    <input type="hidden" name="ur_flow_state" value="" data-ur-flow-state />
</div>
