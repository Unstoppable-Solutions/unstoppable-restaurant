/**
 * Unstoppable Restaurant Admin JavaScript
 */

(function($) {
    'use strict';

    const config = window.urAdminConfig || {};

    /**
     * Modifier Groups Editor
     * Uses underscore.js templates that come with WordPress
     */
    const ModifierEditor = {
        groups: [],
        $container: null,
        $groupsContainer: null,
        $jsonInput: null,

        init() {
            this.$container = $('#urst-modifier-editor');
            if (!this.$container.length) return;

            console.log('[URST] ModifierEditor initializing...');

            this.$groupsContainer = $('#urst-modifier-groups');
            this.$jsonInput = $('#urst-groups-json');

            // Load existing groups
            try {
                this.groups = JSON.parse(this.$jsonInput.val() || '[]');
                console.log('[URST] Loaded groups:', this.groups);
            } catch (e) {
                console.error('[URST] Failed to parse groups JSON:', e);
                this.groups = [];
            }

            this.render();
            this.bindEvents();
            console.log('[URST] ModifierEditor ready');
        },

        bindEvents() {
            // Add group
            $('#urst-add-group').on('click', () => this.addGroup());

            // Remove group
            this.$groupsContainer.on('click', '.urst-modifier-group__remove', (e) => {
                const index = $(e.currentTarget).closest('.urst-modifier-group').data('group-index');
                this.removeGroup(index);
            });

            // Group field changes - listen to both input and change events
            this.$groupsContainer.on('input change', '[data-field]', (e) => {
                this.handleFieldChange(e);
            });

            // Group type change - show/hide min/max
            this.$groupsContainer.on('change', '[data-field="type"]', (e) => {
                const $group = $(e.currentTarget).closest('.urst-modifier-group');
                const type = e.currentTarget.value;
                $group.find('.urst-modifier-group__minmax').toggle(type === 'multiple');
            });

            // Add option
            this.$groupsContainer.on('click', '.urst-add-option', (e) => {
                const groupIndex = $(e.currentTarget).closest('.urst-modifier-group').data('group-index');
                this.addOption(groupIndex);
            });

            // Remove option
            this.$groupsContainer.on('click', '.urst-remove-option', (e) => {
                const $row = $(e.currentTarget).closest('.urst-modifier-option');
                const groupIndex = $row.closest('.urst-modifier-group').data('group-index');
                const optionIndex = $row.data('option-index');
                this.removeOption(groupIndex, optionIndex);
            });

            // Form submission - update JSON (standard form submit)
            this.$container.closest('form').on('submit', () => {
                this.updateJSON();
            });

            // WordPress post save hooks - update JSON before AJAX/Gutenberg saves
            // Hook into the publish/update button click
            $('#publish, #save-post, .editor-post-publish-button').on('click', () => {
                this.updateJSON();
            });

            // Also update on any form submission in the post edit screen
            $('#post').on('submit', () => {
                this.updateJSON();
            });
        },

        render() {
            if (this.groups.length === 0) {
                this.$groupsContainer.html(`
                    <div class="urst-modifier-empty">
                        <p>No modifier groups yet. Click "Add Group" to create your first step.</p>
                    </div>
                `);
                return;
            }

            let html = '';
            this.groups.forEach((group, index) => {
                html += this.renderGroup(group, index);
            });
            this.$groupsContainer.html(html);

            // Initialize sortable if available
            this.initSortable();
        },

        renderGroup(group, index) {
            const isMultiple = group.type === 'multiple';
            let optionsHtml = '';

            if (group.options && group.options.length) {
                group.options.forEach((option, optIndex) => {
                    optionsHtml += this.renderOption(option, optIndex);
                });
            }

            return `
                <div class="urst-modifier-group" data-group-index="${index}">
                    <div class="urst-modifier-group__header">
                        <span class="urst-modifier-group__drag dashicons dashicons-menu"></span>
                        <input 
                            type="text" 
                            class="urst-modifier-group__title" 
                            placeholder="Group title (e.g., Choose your size)"
                            value="${this.escapeHtml(group.label || '')}"
                            data-field="label"
                        />
                        <button type="button" class="urst-modifier-group__remove" title="Remove group">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                    <div class="urst-modifier-group__settings">
                        <label>
                            <select data-field="type">
                                <option value="single" ${group.type === 'single' ? 'selected' : ''}>Single selection (radio)</option>
                                <option value="multiple" ${group.type === 'multiple' ? 'selected' : ''}>Multiple selection (checkbox)</option>
                            </select>
                        </label>
                        <label class="urst-checkbox-label">
                            <input type="checkbox" data-field="required" ${group.required ? 'checked' : ''} />
                            Required
                        </label>
                        <div class="urst-modifier-group__minmax" ${!isMultiple ? 'style="display:none"' : ''}>
                            <label>
                                Min:
                                <input type="number" min="0" max="99" data-field="min_select" value="${group.min_select || 0}" style="width:60px" />
                            </label>
                            <label>
                                Max:
                                <input type="number" min="0" max="99" data-field="max_select" value="${group.max_select || 0}" style="width:60px" />
                            </label>
                        </div>
                    </div>
                    <div class="urst-modifier-options">
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th style="width:30px"></th>
                                    <th>Option Label</th>
                                    <th style="width:100px">Price +/-</th>
                                    <th style="width:120px">Kitchen Label</th>
                                    <th style="width:60px">Default</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody class="urst-options-list">
                                ${optionsHtml}
                            </tbody>
                        </table>
                        <button type="button" class="button urst-add-option">+ Add Option</button>
                    </div>
                </div>
            `;
        },

        renderOption(option, index) {
            return `
                <tr class="urst-modifier-option" data-option-index="${index}">
                    <td class="urst-option-drag">
                        <span class="dashicons dashicons-menu"></span>
                    </td>
                    <td>
                        <input type="text" data-field="label" value="${this.escapeHtml(option.label || '')}" placeholder="Option name" />
                    </td>
                    <td>
                        <input type="number" step="0.01" data-field="price" value="${option.price || 0}" />
                    </td>
                    <td>
                        <input type="text" data-field="kitchen_label" value="${this.escapeHtml(option.kitchen_label || '')}" placeholder="Short text" />
                    </td>
                    <td style="text-align:center">
                        <input type="checkbox" data-field="is_default" ${option.is_default ? 'checked' : ''} />
                    </td>
                    <td>
                        <button type="button" class="button-link urst-remove-option" title="Remove">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </td>
                </tr>
            `;
        },

        addGroup() {
            console.log('[URST] addGroup() called');
            const newGroup = {
                id: 'grp_' + Date.now(),
                label: '',
                type: 'single',
                required: false,
                min_select: 0,
                max_select: 0,
                sort_order: this.groups.length,
                options: []
            };

            this.groups.push(newGroup);
            console.log('[URST] Groups after push:', this.groups);
            this.render();
            console.log('[URST] Rendered');
            this.updateJSON();

            // Focus the new group's title input
            this.$groupsContainer.find('.urst-modifier-group:last .urst-modifier-group__title').focus();
        },

        removeGroup(index) {
            if (!confirm('Remove this modifier group?')) return;
            this.groups.splice(index, 1);
            this.render();
            this.updateJSON();
        },

        addOption(groupIndex) {
            if (!this.groups[groupIndex].options) {
                this.groups[groupIndex].options = [];
            }

            const newOption = {
                id: 'opt_' + Date.now(),
                label: '',
                price: 0,
                kitchen_label: '',
                is_default: false,
                sort_order: this.groups[groupIndex].options.length
            };

            this.groups[groupIndex].options.push(newOption);
            this.render();
            this.updateJSON();
        },

        removeOption(groupIndex, optionIndex) {
            this.groups[groupIndex].options.splice(optionIndex, 1);
            this.render();
            this.updateJSON();
        },

        handleFieldChange(e) {
            const $field = $(e.currentTarget);
            const field = $field.data('field');
            const value = $field.is(':checkbox') ? $field.is(':checked') : $field.val();

            const $group = $field.closest('.urst-modifier-group');
            const groupIndex = $group.data('group-index');

            const $option = $field.closest('.urst-modifier-option');

            if ($option.length) {
                // Option field
                const optionIndex = $option.data('option-index');
                if (this.groups[groupIndex] && this.groups[groupIndex].options[optionIndex]) {
                    if (field === 'price') {
                        this.groups[groupIndex].options[optionIndex][field] = parseFloat(value) || 0;
                    } else {
                        this.groups[groupIndex].options[optionIndex][field] = value;
                    }
                }
            } else {
                // Group field
                if (this.groups[groupIndex]) {
                    if (field === 'min_select' || field === 'max_select') {
                        this.groups[groupIndex][field] = parseInt(value, 10) || 0;
                    } else {
                        this.groups[groupIndex][field] = value;
                    }
                }
            }

            this.updateJSON();
        },

        updateJSON() {
            const json = JSON.stringify(this.groups);
            this.$jsonInput.val(json);
            console.log('[URST] Updated JSON:', json);
        },

        initSortable() {
            if (!$.fn.sortable) return;

            // Groups sortable
            this.$groupsContainer.sortable({
                handle: '.urst-modifier-group__drag',
                placeholder: 'urst-modifier-group-placeholder',
                update: () => {
                    this.reorderGroups();
                }
            });

            // Options sortable
            this.$groupsContainer.find('.urst-options-list').sortable({
                handle: '.urst-option-drag',
                placeholder: 'urst-option-placeholder',
                update: (e, ui) => {
                    const groupIndex = $(e.target).closest('.urst-modifier-group').data('group-index');
                    this.reorderOptions(groupIndex);
                }
            });
        },

        reorderGroups() {
            const newOrder = [];
            this.$groupsContainer.find('.urst-modifier-group').each((index, el) => {
                const oldIndex = $(el).data('group-index');
                if (this.groups[oldIndex]) {
                    newOrder.push(this.groups[oldIndex]);
                }
            });
            this.groups = newOrder;
            this.render();
            this.updateJSON();
        },

        reorderOptions(groupIndex) {
            const $group = this.$groupsContainer.find(`[data-group-index="${groupIndex}"]`);
            const newOrder = [];
            $group.find('.urst-modifier-option').each((index, el) => {
                const oldIndex = $(el).data('option-index');
                if (this.groups[groupIndex].options[oldIndex]) {
                    newOrder.push(this.groups[groupIndex].options[oldIndex]);
                }
            });
            this.groups[groupIndex].options = newOrder;
            this.render();
            this.updateJSON();
        },

        escapeHtml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };

    /**
     * Settings Page
     */
    const SettingsPage = {
        init() {
            this.initColorPickers();
            this.bindEvents();
        },

        initColorPickers() {
            if ($.fn.wpColorPicker) {
                $('.ur-color-picker').wpColorPicker();
            }
        },

        bindEvents() {
            // Service type checkboxes
            $('input[name="ur_settings[service_types][]"]').on('change', function() {
                const checked = $('input[name="ur_settings[service_types][]"]:checked');
                if (checked.length === 0) {
                    $(this).prop('checked', true);
                    alert('At least one service type must be enabled.');
                }
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(function() {
        // Initialize Modifier Editor if on that page
        if ($('#urst-modifier-editor').length) {
            ModifierEditor.init();
        }

        // Initialize Settings Page
        if ($('.ur-settings').length) {
            SettingsPage.init();
        }

        // Days Picker - toggle selected class (for browsers without :has() support)
        $(document).on('change', '.urst-day-option input[type="checkbox"]', function() {
            const $label = $(this).closest('.urst-day-option');
            if (this.checked) {
                $label.addClass('selected');
            } else {
                $label.removeClass('selected');
            }
        });

        // Initialize selected state on page load
        $('.urst-day-option input[type="checkbox"]:checked').each(function() {
            $(this).closest('.urst-day-option').addClass('selected');
        });
    });

})(jQuery);
