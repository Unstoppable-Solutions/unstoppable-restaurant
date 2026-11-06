<?php
/**
 * Admin Schedule Settings Page.
 *
 * @package Unstoppable_Restaurant
 *
 * @var array $services Enabled services.
 * @var array $schedule Schedule data.
 * @var array $closures Closures data.
 * @var array $days     Day names indexed 0-6.
 */

defined( 'ABSPATH' ) || exit;

use Unstoppable\Restaurant\Modules\Scheduling\ScheduleManager;
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Restaurant Schedule', 'unstoppable-restaurant' ); ?></h1>

    <?php settings_errors( 'urst_schedule' ); ?>

    <form method="post" action="options.php" id="urst-schedule-form">
        <?php settings_fields( 'urst_schedule' ); ?>

        <?php // Service Types ?>
        <div class="urst-settings-section">
            <h2><?php esc_html_e( 'Service Types', 'unstoppable-restaurant' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Select which ordering methods your restaurant offers.', 'unstoppable-restaurant' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enabled Services', 'unstoppable-restaurant' ); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( ScheduleManager::OPTION_SERVICE_TYPES ); ?>[]" value="pickup" <?php checked( in_array( 'pickup', $services, true ) ); ?> />
                                <?php esc_html_e( 'Pickup', 'unstoppable-restaurant' ); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( ScheduleManager::OPTION_SERVICE_TYPES ); ?>[]" value="delivery" <?php checked( in_array( 'delivery', $services, true ) ); ?> />
                                <?php esc_html_e( 'Delivery', 'unstoppable-restaurant' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'At least one service type must be enabled.', 'unstoppable-restaurant' ); ?></p>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>

        <?php // Operating Hours ?>
        <div class="urst-settings-section">
            <h2><?php esc_html_e( 'Operating Hours', 'unstoppable-restaurant' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Set when orders can be placed. Leave a day empty to mark it as closed.', 'unstoppable-restaurant' ); ?></p>

            <div class="urst-schedule-tabs">
                <div class="urst-schedule-tab-buttons">
                    <button type="button" class="urst-tab-btn is-active" data-tab="pickup">
                        <?php esc_html_e( 'Pickup Hours', 'unstoppable-restaurant' ); ?>
                    </button>
                    <button type="button" class="urst-tab-btn" data-tab="delivery">
                        <?php esc_html_e( 'Delivery Hours', 'unstoppable-restaurant' ); ?>
                    </button>
                </div>

                <?php foreach ( array( 'pickup', 'delivery' ) as $service ) : ?>
                    <div class="urst-schedule-tab-content <?php echo 'pickup' === $service ? 'is-active' : ''; ?>" data-tab-content="<?php echo esc_attr( $service ); ?>">
                        <table class="widefat urst-schedule-table">
                            <thead>
                                <tr>
                                    <th style="width:120px"><?php esc_html_e( 'Day', 'unstoppable-restaurant' ); ?></th>
                                    <th><?php esc_html_e( 'Hours', 'unstoppable-restaurant' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $days as $day_num => $day_name ) : ?>
                                    <?php
                                    $day_windows = $schedule[ $service ][ $day_num ] ?? array();
                                    if ( empty( $day_windows ) ) {
                                        $day_windows = array( array( 'start' => '', 'end' => '' ) );
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html( $day_name ); ?></strong>
                                        </td>
                                        <td>
                                            <div class="urst-day-windows" data-service="<?php echo esc_attr( $service ); ?>" data-day="<?php echo esc_attr( $day_num ); ?>">
                                                <?php foreach ( $day_windows as $idx => $window ) : ?>
                                                    <div class="urst-time-window">
                                                        <input 
                                                            type="time" 
                                                            name="<?php echo esc_attr( ScheduleManager::OPTION_SCHEDULE ); ?>[<?php echo esc_attr( $service ); ?>][<?php echo esc_attr( $day_num ); ?>][<?php echo esc_attr( $idx ); ?>][start]"
                                                            value="<?php echo esc_attr( $window['start'] ?? '' ); ?>"
                                                            placeholder="<?php esc_attr_e( 'Open', 'unstoppable-restaurant' ); ?>"
                                                        />
                                                        <span class="urst-time-separator">–</span>
                                                        <input 
                                                            type="time" 
                                                            name="<?php echo esc_attr( ScheduleManager::OPTION_SCHEDULE ); ?>[<?php echo esc_attr( $service ); ?>][<?php echo esc_attr( $day_num ); ?>][<?php echo esc_attr( $idx ); ?>][end]"
                                                            value="<?php echo esc_attr( $window['end'] ?? '' ); ?>"
                                                            placeholder="<?php esc_attr_e( 'Close', 'unstoppable-restaurant' ); ?>"
                                                        />
                                                        <?php if ( $idx > 0 ) : ?>
                                                            <button type="button" class="button-link urst-remove-window" title="<?php esc_attr_e( 'Remove', 'unstoppable-restaurant' ); ?>">
                                                                <span class="dashicons dashicons-no-alt"></span>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <button type="button" class="button-link urst-add-window" data-service="<?php echo esc_attr( $service ); ?>" data-day="<?php echo esc_attr( $day_num ); ?>">
                                                <?php esc_html_e( '+ Add time window', 'unstoppable-restaurant' ); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php // Closures ?>
        <div class="urst-settings-section">
            <h2><?php esc_html_e( 'Closures & Holidays', 'unstoppable-restaurant' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Add dates when your restaurant will be closed. Orders will be blocked on these days.', 'unstoppable-restaurant' ); ?></p>

            <div id="urst-closures-list">
                <?php if ( empty( $closures ) ) : ?>
                    <p class="urst-no-closures"><?php esc_html_e( 'No closures scheduled.', 'unstoppable-restaurant' ); ?></p>
                <?php else : ?>
                    <?php foreach ( $closures as $idx => $closure ) : ?>
                        <div class="urst-closure-item">
                            <?php if ( isset( $closure['date'] ) ) : ?>
                                <input type="hidden" name="<?php echo esc_attr( ScheduleManager::OPTION_CLOSURES ); ?>[<?php echo esc_attr( $idx ); ?>][date]" value="<?php echo esc_attr( $closure['date'] ); ?>" />
                                <span class="urst-closure-date"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $closure['date'] ) ) ); ?></span>
                            <?php else : ?>
                                <input type="hidden" name="<?php echo esc_attr( ScheduleManager::OPTION_CLOSURES ); ?>[<?php echo esc_attr( $idx ); ?>][start]" value="<?php echo esc_attr( $closure['start'] ); ?>" />
                                <input type="hidden" name="<?php echo esc_attr( ScheduleManager::OPTION_CLOSURES ); ?>[<?php echo esc_attr( $idx ); ?>][end]" value="<?php echo esc_attr( $closure['end'] ); ?>" />
                                <span class="urst-closure-date">
                                    <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $closure['start'] ) ) ); ?>
                                    –
                                    <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $closure['end'] ) ) ); ?>
                                </span>
                            <?php endif; ?>
                            <input 
                                type="text" 
                                name="<?php echo esc_attr( ScheduleManager::OPTION_CLOSURES ); ?>[<?php echo esc_attr( $idx ); ?>][reason]" 
                                value="<?php echo esc_attr( $closure['reason'] ?? '' ); ?>"
                                placeholder="<?php esc_attr_e( 'Reason (optional)', 'unstoppable-restaurant' ); ?>"
                                class="regular-text"
                            />
                            <button type="button" class="button-link urst-remove-closure">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="urst-add-closure">
                <h4><?php esc_html_e( 'Add Closure', 'unstoppable-restaurant' ); ?></h4>
                <div class="urst-add-closure-form">
                    <label>
                        <input type="radio" name="urst_closure_type" value="single" checked />
                        <?php esc_html_e( 'Single date', 'unstoppable-restaurant' ); ?>
                    </label>
                    <label>
                        <input type="radio" name="urst_closure_type" value="range" />
                        <?php esc_html_e( 'Date range', 'unstoppable-restaurant' ); ?>
                    </label>

                    <div class="urst-closure-inputs">
                        <div class="urst-closure-single">
                            <input type="date" id="urst-closure-date" min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" />
                        </div>
                        <div class="urst-closure-range" style="display:none;">
                            <input type="date" id="urst-closure-start" min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" />
                            <span>–</span>
                            <input type="date" id="urst-closure-end" min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" />
                        </div>
                        <input type="text" id="urst-closure-reason" placeholder="<?php esc_attr_e( 'Reason (optional)', 'unstoppable-restaurant' ); ?>" class="regular-text" />
                        <button type="button" class="button" id="urst-add-closure-btn"><?php esc_html_e( 'Add Closure', 'unstoppable-restaurant' ); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <?php submit_button( __( 'Save Schedule', 'unstoppable-restaurant' ) ); ?>
    </form>
</div>

<style>
.urst-settings-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin: 20px 0;
}
.urst-settings-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.urst-schedule-tabs {
    margin-top: 15px;
}
.urst-schedule-tab-buttons {
    display: flex;
    gap: 0;
    border-bottom: 1px solid #ccd0d4;
}
.urst-tab-btn {
    background: #f0f0f0;
    border: 1px solid #ccd0d4;
    border-bottom: none;
    padding: 10px 20px;
    cursor: pointer;
    margin-bottom: -1px;
    font-size: 14px;
}
.urst-tab-btn.is-active {
    background: #fff;
    border-bottom: 1px solid #fff;
}
.urst-schedule-tab-content {
    display: none;
    padding: 15px 0;
}
.urst-schedule-tab-content.is-active {
    display: block;
}
.urst-schedule-table {
    margin-top: 0;
}
.urst-schedule-table td {
    vertical-align: top;
    padding: 12px;
}
.urst-time-window {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
.urst-time-window input[type="time"] {
    width: 120px;
}
.urst-time-separator {
    color: #666;
}
.urst-add-window {
    color: #2271b1;
    text-decoration: none;
}
.urst-remove-window {
    color: #a00;
}
.urst-closure-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #f9f9f9;
    margin-bottom: 10px;
    border-radius: 4px;
}
.urst-closure-date {
    min-width: 150px;
    font-weight: 500;
}
.urst-remove-closure {
    color: #a00;
}
.urst-add-closure {
    margin-top: 20px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
}
.urst-add-closure h4 {
    margin: 0 0 10px;
}
.urst-add-closure-form label {
    margin-right: 15px;
}
.urst-closure-inputs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
    align-items: center;
}
.urst-no-closures {
    color: #666;
    font-style: italic;
}
</style>

<script>
(function($) {
    // Tab switching
    $('.urst-tab-btn').on('click', function() {
        const tab = $(this).data('tab');
        $('.urst-tab-btn').removeClass('is-active');
        $(this).addClass('is-active');
        $('.urst-schedule-tab-content').removeClass('is-active');
        $('[data-tab-content="' + tab + '"]').addClass('is-active');
    });

    // Add time window
    $('.urst-add-window').on('click', function() {
        const service = $(this).data('service');
        const day = $(this).data('day');
        const container = $(this).prev('.urst-day-windows');
        const idx = container.find('.urst-time-window').length;
        
        const html = `
            <div class="urst-time-window">
                <input type="time" name="urst_schedule[${service}][${day}][${idx}][start]" />
                <span class="urst-time-separator">–</span>
                <input type="time" name="urst_schedule[${service}][${day}][${idx}][end]" />
                <button type="button" class="button-link urst-remove-window" title="Remove">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
        `;
        container.append(html);
    });

    // Remove time window
    $(document).on('click', '.urst-remove-window', function() {
        $(this).closest('.urst-time-window').remove();
    });

    // Closure type toggle
    $('input[name="urst_closure_type"]').on('change', function() {
        if ($(this).val() === 'single') {
            $('.urst-closure-single').show();
            $('.urst-closure-range').hide();
        } else {
            $('.urst-closure-single').hide();
            $('.urst-closure-range').show();
        }
    });

    // Add closure
    $('#urst-add-closure-btn').on('click', function() {
        const type = $('input[name="urst_closure_type"]:checked').val();
        const reason = $('#urst-closure-reason').val();
        const idx = $('.urst-closure-item').length;
        
        let dateDisplay, hiddenInputs;
        
        if (type === 'single') {
            const date = $('#urst-closure-date').val();
            if (!date) {
                alert('Please select a date.');
                return;
            }
            dateDisplay = new Date(date + 'T12:00:00').toLocaleDateString();
            hiddenInputs = `<input type="hidden" name="urst_closures[${idx}][date]" value="${date}" />`;
        } else {
            const start = $('#urst-closure-start').val();
            const end = $('#urst-closure-end').val();
            if (!start || !end) {
                alert('Please select both start and end dates.');
                return;
            }
            if (end < start) {
                alert('End date must be after start date.');
                return;
            }
            dateDisplay = new Date(start + 'T12:00:00').toLocaleDateString() + ' – ' + new Date(end + 'T12:00:00').toLocaleDateString();
            hiddenInputs = `<input type="hidden" name="urst_closures[${idx}][start]" value="${start}" /><input type="hidden" name="urst_closures[${idx}][end]" value="${end}" />`;
        }
        
        const html = `
            <div class="urst-closure-item">
                ${hiddenInputs}
                <span class="urst-closure-date">${dateDisplay}</span>
                <input type="text" name="urst_closures[${idx}][reason]" value="${reason}" placeholder="Reason (optional)" class="regular-text" />
                <button type="button" class="button-link urst-remove-closure">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        `;
        
        $('.urst-no-closures').remove();
        $('#urst-closures-list').append(html);
        
        // Clear inputs
        $('#urst-closure-date, #urst-closure-start, #urst-closure-end, #urst-closure-reason').val('');
    });

    // Remove closure
    $(document).on('click', '.urst-remove-closure', function() {
        $(this).closest('.urst-closure-item').remove();
        if ($('.urst-closure-item').length === 0) {
            $('#urst-closures-list').html('<p class="urst-no-closures">No closures scheduled.</p>');
        }
    });
})(jQuery);
</script>
