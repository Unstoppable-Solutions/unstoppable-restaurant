<?php
/**
 * Admin Template: Modifier Groups Editor
 *
 * @package Unstoppable_Restaurant
 *
 * @var array $groups Existing groups data.
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="urst-modifier-editor" id="urst-modifier-editor">
    <p class="description">
        <?php esc_html_e( 'Create modifier groups that will be shown step-by-step when customers order this product. Think of each group as a question: "Choose your size", "Add toppings", etc.', 'unstoppable-restaurant' ); ?>
    </p>

    <div class="urst-modifier-groups" id="urst-modifier-groups">
        <?php if ( empty( $groups ) ) : ?>
            <div class="urst-modifier-empty">
                <p><?php esc_html_e( 'No modifier groups yet. Click "Add Group" to create your first step.', 'unstoppable-restaurant' ); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <button type="button" class="button button-secondary" id="urst-add-group">
        <span class="dashicons dashicons-plus-alt2"></span>
        <?php esc_html_e( 'Add Group', 'unstoppable-restaurant' ); ?>
    </button>

    <input type="hidden" name="urst_groups" id="urst-groups-json" value="<?php echo esc_attr( wp_json_encode( $groups ) ); ?>" />
</div>

<script type="text/html" id="tmpl-urst-modifier-group">
<div class="urst-modifier-group" data-group-index="{{data.index}}">
    <div class="urst-modifier-group__header">
        <span class="urst-modifier-group__drag dashicons dashicons-menu"></span>
        <input 
            type="text" 
            class="urst-modifier-group__title" 
            placeholder="<?php esc_attr_e( 'Group title (e.g., Choose your size)', 'unstoppable-restaurant' ); ?>"
            value="{{data.label}}"
            data-field="label"
        />
        <button type="button" class="urst-modifier-group__remove" title="<?php esc_attr_e( 'Remove group', 'unstoppable-restaurant' ); ?>">
            <span class="dashicons dashicons-trash"></span>
        </button>
    </div>

    <div class="urst-modifier-group__settings">
        <label>
            <select data-field="type">
                <option value="single" <# if (data.type === 'single') { #>selected<# } #>>
                    <?php esc_html_e( 'Single selection (radio)', 'unstoppable-restaurant' ); ?>
                </option>
                <option value="multiple" <# if (data.type === 'multiple') { #>selected<# } #>>
                    <?php esc_html_e( 'Multiple selection (checkbox)', 'unstoppable-restaurant' ); ?>
                </option>
            </select>
        </label>

        <label class="urst-checkbox-label">
            <input type="checkbox" data-field="required" <# if (data.required) { #>checked<# } #> />
            <?php esc_html_e( 'Required', 'unstoppable-restaurant' ); ?>
        </label>

        <div class="urst-modifier-group__minmax" <# if (data.type !== 'multiple') { #>style="display:none"<# } #>>
            <label>
                <?php esc_html_e( 'Min:', 'unstoppable-restaurant' ); ?>
                <input type="number" min="0" max="99" data-field="min_select" value="{{data.min_select || 0}}" style="width:60px" />
            </label>
            <label>
                <?php esc_html_e( 'Max:', 'unstoppable-restaurant' ); ?>
                <input type="number" min="0" max="99" data-field="max_select" value="{{data.max_select || 0}}" style="width:60px" />
            </label>
        </div>
    </div>

    <div class="urst-modifier-options">
        <table class="widefat">
            <thead>
                <tr>
                    <th style="width:30px"></th>
                    <th><?php esc_html_e( 'Option Label', 'unstoppable-restaurant' ); ?></th>
                    <th style="width:100px"><?php esc_html_e( 'Price +/-', 'unstoppable-restaurant' ); ?></th>
                    <th style="width:120px"><?php esc_html_e( 'Kitchen Label', 'unstoppable-restaurant' ); ?></th>
                    <th style="width:60px"><?php esc_html_e( 'Default', 'unstoppable-restaurant' ); ?></th>
                    <th style="width:40px"></th>
                </tr>
            </thead>
            <tbody class="urst-options-list">
                <!-- Options rendered here -->
            </tbody>
        </table>
        <button type="button" class="button urst-add-option">
            <?php esc_html_e( '+ Add Option', 'unstoppable-restaurant' ); ?>
        </button>
    </div>
</div>
</script>

<script type="text/html" id="tmpl-urst-modifier-option">
<tr class="urst-modifier-option" data-option-index="{{data.index}}">
    <td class="urst-option-drag">
        <span class="dashicons dashicons-menu"></span>
    </td>
    <td>
        <input type="text" data-field="label" value="{{data.label}}" placeholder="<?php esc_attr_e( 'Option name', 'unstoppable-restaurant' ); ?>" />
    </td>
    <td>
        <input type="number" step="0.01" data-field="price" value="{{data.price || 0}}" />
    </td>
    <td>
        <input type="text" data-field="kitchen_label" value="{{data.kitchen_label}}" placeholder="<?php esc_attr_e( 'Short text', 'unstoppable-restaurant' ); ?>" />
    </td>
    <td style="text-align:center">
        <input type="checkbox" data-field="is_default" <# if (data.is_default) { #>checked<# } #> />
    </td>
    <td>
        <button type="button" class="button-link urst-remove-option" title="<?php esc_attr_e( 'Remove', 'unstoppable-restaurant' ); ?>">
            <span class="dashicons dashicons-no-alt"></span>
        </button>
    </td>
</tr>
</script>

<style>
.urst-modifier-editor { margin-top: 15px; }
.urst-modifier-groups { margin-bottom: 15px; }
.urst-modifier-empty { padding: 20px; background: #f9f9f9; text-align: center; border: 1px dashed #ddd; border-radius: 4px; }
.urst-modifier-group { background: #fff; border: 1px solid #ddd; margin-bottom: 15px; border-radius: 4px; }
.urst-modifier-group__header { display: flex; align-items: center; gap: 10px; padding: 10px; background: #f5f5f5; border-bottom: 1px solid #ddd; }
.urst-modifier-group__drag { cursor: move; color: #999; }
.urst-modifier-group__title { flex: 1; font-size: 14px; font-weight: 500; padding: 5px 10px; }
.urst-modifier-group__remove { background: none; border: none; color: #a00; cursor: pointer; padding: 5px; }
.urst-modifier-group__remove:hover { color: #dc3232; }
.urst-modifier-group__settings { display: flex; gap: 20px; padding: 10px 15px; background: #fafafa; border-bottom: 1px solid #eee; flex-wrap: wrap; align-items: center; }
.urst-modifier-group__minmax { display: flex; gap: 10px; }
.urst-checkbox-label { display: flex; align-items: center; gap: 5px; }
.urst-modifier-options { padding: 15px; }
.urst-modifier-options table input[type="text"],
.urst-modifier-options table input[type="number"] { width: 100%; }
.urst-options-list .urst-option-drag { cursor: move; color: #999; }
.urst-add-option { margin-top: 10px; }
.urst-remove-option { color: #a00 !important; }
.urst-remove-option:hover { color: #dc3232 !important; }
</style>
