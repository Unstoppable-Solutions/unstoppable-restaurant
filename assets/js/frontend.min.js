/**
 * Unstoppable Restaurant Frontend JavaScript
 *
 * Phase 3: Cart Drawer functionality
 * Handles cart drawer, product interactions, and AJAX cart operations.
 *
 * @package Unstoppable_Restaurant
 */

console.log('[URST] Frontend JS file loaded');

(function($) {
    'use strict';

    console.log('[URST] Inside IIFE, jQuery:', typeof $);
    
    if (typeof $ === 'undefined') {
        console.error('[URST] jQuery not available!');
        return;
    }

    // Global namespace
    window.UnstoppableRestaurant = window.UnstoppableRestaurant || {};

    /**
     * Configuration from wp_localize_script
     */
    const config = window.urstConfig || {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        nonce: '',
        i18n: {
            addToCart: 'Added to cart!',
            error: 'Something went wrong.',
            loading: 'Loading...'
        }
    };

    /**
     * Cart Drawer Module
     * Handles the slide-in/slide-up cart drawer UI
     */
    const CartDrawer = {
        drawer: null,
        overlay: null,
        isOpen: false,
        focusedElementBeforeOpen: null,

        init() {
            this.drawer = document.getElementById('ur-cart-drawer');
            this.overlay = document.querySelector('.ur-overlay');

            if (!this.drawer) return;

            this.bindEvents();
        },

        bindEvents() {
            // Open drawer triggers
            document.querySelectorAll('[data-ur-open-drawer]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.open();
                });
            });

            // Close drawer triggers
            document.querySelectorAll('[data-ur-close-drawer]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.close();
                });
            });

            // Close on overlay click
            if (this.overlay) {
                this.overlay.addEventListener('click', () => this.close());
            }

            // Close on escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });

            // Quantity controls - delegated events
            $(document).on('click', '.ur-quantity__btn', (e) => {
                e.preventDefault();
                console.log('[URST Cart] Quantity button clicked');
                
                const btn = e.currentTarget;
                if (btn.disabled || btn.classList.contains('is-loading')) {
                    console.log('[URST Cart] Button disabled or loading');
                    return;
                }
                
                const cartItemKey = btn.dataset.cartItemKey;
                const action = btn.dataset.action;
                console.log('[URST Cart] cartItemKey:', cartItemKey, 'action:', action);
                
                const quantityContainer = btn.closest('.ur-quantity');
                const quantityEl = quantityContainer.querySelector('.ur-quantity__value');
                let quantity = parseInt(quantityEl.textContent, 10);

                if (action === 'increase') {
                    quantity++;
                    Cart.updateQuantity(cartItemKey, quantity, btn);
                } else if (action === 'decrease') {
                    if (quantity > 1) {
                        quantity--;
                        Cart.updateQuantity(cartItemKey, quantity, btn);
                    } else {
                        // Remove item when quantity reaches 0
                        Cart.removeItem(cartItemKey, btn);
                    }
                }
            });

            // Remove cart item
            $(document).on('click', '[data-action="remove"]', (e) => {
                e.preventDefault();
                console.log('[URST Cart] Remove button clicked');
                
                const btn = e.currentTarget;
                const cartItemKey = btn.dataset.cartItemKey;
                console.log('[URST Cart] Removing cartItemKey:', cartItemKey);
                
                Cart.removeItem(cartItemKey, btn);
            });
        },

        open() {
            if (!this.drawer || this.isOpen) return;

            // Store current focus
            this.focusedElementBeforeOpen = document.activeElement;

            // Open drawer
            this.drawer.classList.add('is-open');
            this.drawer.setAttribute('aria-hidden', 'false');
            
            if (this.overlay) {
                this.overlay.classList.add('is-visible');
            }

            // Prevent body scroll
            document.body.style.overflow = 'hidden';
            this.isOpen = true;

            // Focus close button
            const closeBtn = this.drawer.querySelector('[data-ur-close-drawer]');
            if (closeBtn) {
                setTimeout(() => closeBtn.focus(), 100);
            }

            // Trap focus
            this.trapFocus();

            // Trigger event
            $(document.body).trigger('ur_drawer_opened');
        },

        close() {
            if (!this.drawer || !this.isOpen) return;

            // Close drawer
            this.drawer.classList.remove('is-open');
            this.drawer.setAttribute('aria-hidden', 'true');
            
            if (this.overlay) {
                this.overlay.classList.remove('is-visible');
            }

            // Restore body scroll
            document.body.style.overflow = '';
            this.isOpen = false;

            // Return focus
            if (this.focusedElementBeforeOpen) {
                this.focusedElementBeforeOpen.focus();
            }

            // Trigger event
            $(document.body).trigger('ur_drawer_closed');
        },

        trapFocus() {
            const focusableSelectors = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
            const focusableElements = this.drawer.querySelectorAll(focusableSelectors);
            
            if (focusableElements.length === 0) return;
            
            const firstFocusable = focusableElements[0];
            const lastFocusable = focusableElements[focusableElements.length - 1];

            const handleTab = (e) => {
                if (!this.isOpen || e.key !== 'Tab') return;

                if (e.shiftKey) {
                    if (document.activeElement === firstFocusable) {
                        lastFocusable.focus();
                        e.preventDefault();
                    }
                } else {
                    if (document.activeElement === lastFocusable) {
                        firstFocusable.focus();
                        e.preventDefault();
                    }
                }
            };

            // Remove old listener and add new
            if (this._tabHandler) {
                this.drawer.removeEventListener('keydown', this._tabHandler);
            }
            this._tabHandler = handleTab;
            this.drawer.addEventListener('keydown', this._tabHandler);
        },

        updateContent(html) {
            const contents = this.drawer?.querySelector('[data-ur-cart-contents]') || 
                           this.drawer?.querySelector('.ur-cart-contents');
            if (contents && html) {
                contents.innerHTML = html;
            }
        },

        updateCount(count) {
            document.querySelectorAll('[data-ur-cart-count]').forEach(el => {
                el.textContent = count;
            });

            // Update floating cart button
            const floatingCart = document.querySelector('.ur-floating-cart');
            if (floatingCart) {
                let countBadge = floatingCart.querySelector('.ur-floating-cart__count');
                floatingCart.classList.toggle('has-items', count > 0);
                
                if (count > 0) {
                    if (!countBadge) {
                        countBadge = document.createElement('span');
                        countBadge.className = 'ur-floating-cart__count';
                        countBadge.setAttribute('data-ur-cart-count', '');
                        floatingCart.appendChild(countBadge);
                    }
                    countBadge.textContent = count;
                } else if (countBadge) {
                    countBadge.remove();
                }
            }
        },

        updateTotal(total) {
            document.querySelectorAll('[data-ur-cart-total]').forEach(el => {
                el.innerHTML = total;
            });
        }
    };

    /**
     * Cart Operations Module
     * Handles AJAX cart operations - reads directly from WooCommerce
     */
    const Cart = {
        isLoading: false,
        loadingTimeout: null,

        init() {
            this.bindProductCardEvents();
            
            // Debug: Log cart state
            console.log('[URST Cart] Initialized, isLoading:', this.isLoading);
        },

        /**
         * Reset loading state with timeout protection
         */
        setLoading(loading) {
            // Clear any existing timeout
            if (this.loadingTimeout) {
                clearTimeout(this.loadingTimeout);
                this.loadingTimeout = null;
            }
            
            this.isLoading = loading;
            
            // Auto-reset after 30 seconds to prevent permanent lock
            if (loading) {
                this.loadingTimeout = setTimeout(() => {
                    console.warn('[URST Cart] Loading timeout - auto-resetting isLoading');
                    this.isLoading = false;
                    this.loadingTimeout = null;
                }, 30000);
            }
        },

        bindProductCardEvents() {
            // Add to cart from product card button
            $(document).on('click', '.ur-product-card__add-btn', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('[URST Cart] Product card add button clicked');

                const btn = e.currentTarget;
                if (btn.disabled || btn.classList.contains('is-loading')) {
                    console.log('[URST Cart] Button disabled or has is-loading class');
                    return;
                }

                // Check scheduling
                if (!this.checkScheduling()) return;

                // Get product card (parent article)
                const card = btn.closest('.ur-product-card');
                if (!card) return;

                const productId = card.dataset.productId;
                
                // Phase 7.4: Build menu context from data attributes (canonical schema)
                const menuContext = {};
                if (card.dataset.menuId) menuContext.menu_id = card.dataset.menuId;
                if (card.dataset.menuSlug) menuContext.menu_slug = card.dataset.menuSlug;
                if (card.dataset.menuName) menuContext.menu_name = card.dataset.menuName;
                if (card.dataset.categoryId) menuContext.category_id = card.dataset.categoryId;
                if (card.dataset.categoryName) menuContext.category_name = card.dataset.categoryName;
                if (card.dataset.sectionId) menuContext.section_id = card.dataset.sectionId;
                // Station can be 'station' or 'stationHint' (backward compat)
                if (card.dataset.station) menuContext.station = card.dataset.station;
                else if (card.dataset.stationHint) menuContext.station = card.dataset.stationHint;
                if (card.dataset.channel) menuContext.channel = card.dataset.channel;
                
                // Pass menu context in cart item data
                const cartItemData = {};
                if (Object.keys(menuContext).length > 0) {
                    cartItemData.menu_context = menuContext;
                }
                
                this.addToCart(productId, 1, cartItemData, btn);
            });
        },

        /**
         * Check if ordering is currently allowed
         */
        checkScheduling() {
            // If scheduling data is available and we're closed, block
            if (config.scheduling && config.scheduling.is_open === false) {
                this.showNotification(config.scheduling.message || config.i18n.closed, 'error');
                return false;
            }
            return true;
        },

        /**
         * Add product to cart via AJAX
         */
        addToCart(productId, quantity = 1, cartItemData = {}, triggerBtn = null) {
            console.log('[URST Cart] addToCart called, isLoading:', this.isLoading, 'product:', productId);
            
            if (this.isLoading) {
                console.log('[URST Cart] Blocked - already loading');
                return;
            }

            // Check scheduling
            if (!this.checkScheduling()) return;

            this.setLoading(true);
            this.setButtonLoading(triggerBtn, true);

            // Build request data
            const requestData = {
                action: 'urst_add_to_cart',
                nonce: config.nonce,
                product_id: productId,
                quantity: quantity
            };
            
            // Add menu context if present
            if (cartItemData.menu_context) {
                requestData.menu_context = JSON.stringify(cartItemData.menu_context);
            }
            
            // Add modifiers if present
            if (cartItemData.modifiers) {
                requestData.modifiers = JSON.stringify(cartItemData.modifiers);
            }

            console.log('[URST Cart] Sending request:', requestData);

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                timeout: 15000, // 15 second timeout
                data: requestData,
                success: (response) => {
                    console.log('[URST Cart] addToCart response:', response);
                    if (response.success) {
                        CartDrawer.updateContent(response.data.html);
                        CartDrawer.updateCount(response.data.count);
                        CartDrawer.updateTotal(response.data.total);
                        CartDrawer.open();

                        this.showNotification(response.data.message || config.i18n.addToCart, 'success');

                        // Trigger WooCommerce and custom events
                        $(document.body).trigger('added_to_cart', [response.data.fragments, response.data.cart_hash, triggerBtn]);
                        $(document.body).trigger('ur_added_to_cart', [response.data]);
                        $(document.body).trigger('wc_fragment_refresh');
                    } else {
                        console.error('[URST Cart] Server returned error:', response);
                        this.showNotification(response.data?.message || config.i18n.error, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('[URST Cart] addToCart AJAX error:', status, error);
                    console.error('[URST Cart] XHR response:', xhr.responseText);
                    this.showNotification(config.i18n.error, 'error');
                },
                complete: () => {
                    console.log('[URST Cart] addToCart complete');
                    this.setLoading(false);
                    this.setButtonLoading(triggerBtn, false);
                }
            });
        },

        /**
         * Update cart item quantity
         */
        updateQuantity(cartItemKey, quantity, triggerBtn = null) {
            console.log('[URST Cart] updateQuantity called, isLoading:', this.isLoading, 'key:', cartItemKey, 'qty:', quantity);
            
            if (this.isLoading) {
                console.log('[URST Cart] Blocked - already loading');
                return;
            }

            this.setLoading(true);
            this.setButtonLoading(triggerBtn, true);

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                timeout: 30000, // 30 second timeout
                data: {
                    action: 'urst_update_cart_item',
                    nonce: config.nonce,
                    cart_item_key: cartItemKey,
                    quantity: quantity
                },
                success: (response) => {
                    console.log('[URST Cart] updateQuantity response:', response);
                    if (response.success) {
                        CartDrawer.updateContent(response.data.html);
                        CartDrawer.updateCount(response.data.count);
                        CartDrawer.updateTotal(response.data.total);

                        $(document.body).trigger('ur_cart_updated', [response.data]);
                        
                        // Log timing if available
                        if (response.data.debug_timings) {
                            console.log('[URST Cart] Timings:', response.data.debug_timings);
                        }
                    } else {
                        this.showNotification(response.data?.message || config.i18n.error, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('[URST Cart] updateQuantity error:', status, error);
                    this.showNotification(config.i18n.error, 'error');
                },
                complete: () => {
                    console.log('[URST Cart] updateQuantity complete');
                    this.setLoading(false);
                    this.setButtonLoading(triggerBtn, false);
                }
            });
        },

        /**
         * Remove item from cart
         */
        removeItem(cartItemKey, triggerBtn = null) {
            console.log('[URST Cart] removeItem called, isLoading:', this.isLoading, 'key:', cartItemKey);
            
            if (this.isLoading) {
                console.log('[URST Cart] Blocked - already loading');
                return;
            }

            this.setLoading(true);
            
            // Add loading state to the cart item
            const cartItem = document.querySelector(`[data-cart-item-key="${cartItemKey}"]`);
            if (cartItem) {
                cartItem.classList.add('is-removing');
            }

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                timeout: 30000, // 30 second timeout
                data: {
                    action: 'urst_remove_cart_item',
                    nonce: config.nonce,
                    cart_item_key: cartItemKey
                },
                success: (response) => {
                    console.log('[URST Cart] removeItem response:', response);
                    if (response.success) {
                        CartDrawer.updateContent(response.data.html);
                        CartDrawer.updateCount(response.data.count);
                        CartDrawer.updateTotal(response.data.total);

                        $(document.body).trigger('ur_cart_item_removed', [response.data]);
                        
                        // Log timing if available
                        if (response.data.debug_timings) {
                            console.log('[URST Cart] Timings:', response.data.debug_timings);
                        }
                    } else {
                        this.showNotification(response.data?.message || config.i18n.error, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('[URST Cart] removeItem error:', status, error);
                    this.showNotification(config.i18n.error, 'error');
                },
                complete: () => {
                    console.log('[URST Cart] removeItem complete');
                    this.setLoading(false);
                    if (cartItem) {
                        cartItem.classList.remove('is-removing');
                    }
                }
            });
        },

        /**
         * Set loading state on a button
         */
        setButtonLoading(btn, isLoading) {
            if (!btn) return;
            
            if (isLoading) {
                btn.classList.add('is-loading');
                btn.disabled = true;
            } else {
                btn.classList.remove('is-loading');
                btn.disabled = false;
            }
        },

        /**
         * Show notification toast
         */
        showNotification(message, type = 'success') {
            // Remove existing notifications
            document.querySelectorAll('.ur-notification').forEach(n => n.remove());

            const notification = document.createElement('div');
            notification.className = `ur-notification ur-notification--${type}`;
            notification.setAttribute('role', 'alert');
            notification.innerHTML = `
                <span class="ur-notification__message">${message}</span>
                <button type="button" class="ur-notification__close" aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            `;

            document.body.appendChild(notification);

            // Auto dismiss after 3 seconds
            const dismissTimeout = setTimeout(() => {
                notification.classList.add('ur-notification--hiding');
                setTimeout(() => notification.remove(), 300);
            }, 3000);

            // Manual close
            notification.querySelector('.ur-notification__close').addEventListener('click', () => {
                clearTimeout(dismissTimeout);
                notification.remove();
            });
        }
    };

    /**
     * Product Modal Module
     * Handles product detail modal with quantity selection
     */
    const ProductModal = {
        modal: null,
        content: null,
        backdrop: null,
        isOpen: false,
        isLoading: false,
        currentProductId: null,
        currentQuantity: 1,
        currentPrice: 0,
        currentMenuContext: null, // Phase 7.4: Store menu context when modal opens
        focusedElementBeforeOpen: null,

        init() {
            this.modal = document.getElementById('ur-product-modal');
            if (!this.modal) return;

            this.content = this.modal.querySelector('[data-ur-modal-content]');
            this.backdrop = this.modal.querySelector('.ur-modal__backdrop');

            this.bindEvents();
        },

        bindEvents() {
            // Open modal from product card click
            $(document).on('click', '.ur-product-card', (e) => {
                // Don't trigger if clicking the add button directly
                if (e.target.closest('.ur-product-card__add-btn')) return;
                
                const card = e.currentTarget;
                const productId = card.dataset.productId;
                if (productId) {
                    e.preventDefault();
                    // Phase 7.4: Capture menu context from card BEFORE opening modal
                    this.captureMenuContext(card);
                    this.open(productId);
                }
            });

            // Close modal triggers
            $(document).on('click', '[data-ur-close-modal]', (e) => {
                e.preventDefault();
                this.close();
            });

            // Close on backdrop click
            if (this.backdrop) {
                this.backdrop.addEventListener('click', () => this.close());
            }

            // Close on ESC key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });

            // Quantity controls inside modal
            $(document).on('click', '[data-action="modal-decrease"]', (e) => {
                e.preventDefault();
                this.updateQuantity(this.currentQuantity - 1);
            });

            $(document).on('click', '[data-action="modal-increase"]', (e) => {
                e.preventDefault();
                this.updateQuantity(this.currentQuantity + 1);
            });

            // Quantity input change
            $(document).on('change', '[data-ur-modal-quantity]', (e) => {
                const value = parseInt(e.target.value, 10) || 1;
                this.updateQuantity(value);
            });

            // Add to cart from modal
            $(document).on('click', '[data-ur-modal-add-to-cart]', (e) => {
                e.preventDefault();
                const btn = e.currentTarget;
                const productId = btn.dataset.productId;
                this.addToCart(productId);
            });
        },

        /**
         * Open modal and load product data
         */
        open(productId) {
            if (!this.modal || this.isLoading) return;

            this.currentProductId = productId;
            this.currentQuantity = 1;
            this.focusedElementBeforeOpen = document.activeElement;

            // Show loading state
            this.showLoading();
            this.modal.classList.add('is-open');
            this.modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            this.isOpen = true;

            // Load product data via AJAX
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'urst_get_product_modal',
                    nonce: config.nonce,
                    product_id: productId
                },
                success: (response) => {
                    if (response.success) {
                        this.content.innerHTML = response.data.html;
                        this.currentPrice = parseFloat(response.data.product.price) || 0;
                        
                        // Focus close button after content loads
                        setTimeout(() => {
                            const closeBtn = this.modal.querySelector('[data-ur-close-modal]');
                            if (closeBtn) closeBtn.focus();
                            this.trapFocus();
                        }, 100);

                        $(document.body).trigger('ur_modal_opened', [response.data.product]);
                    } else {
                        Cart.showNotification(response.data.message || config.i18n.error, 'error');
                        this.close();
                    }
                },
                error: () => {
                    Cart.showNotification(config.i18n.error, 'error');
                    this.close();
                },
                complete: () => {
                    this.isLoading = false;
                }
            });
        },

        /**
         * Phase 7.4: Capture menu context from product card.
         * Called BEFORE modal opens. Context is IMMUTABLE after capture.
         */
        captureMenuContext(card) {
            if (!card || !card.dataset) {
                this.currentMenuContext = null;
                return;
            }

            const context = {};
            
            // Map data attributes to context fields (canonical schema)
            if (card.dataset.menuId) context.menu_id = card.dataset.menuId;
            if (card.dataset.menuSlug) context.menu_slug = card.dataset.menuSlug;
            if (card.dataset.menuName) context.menu_name = card.dataset.menuName;
            if (card.dataset.categoryId) context.category_id = card.dataset.categoryId;
            if (card.dataset.categoryName) context.category_name = card.dataset.categoryName;
            if (card.dataset.sectionId) context.section_id = card.dataset.sectionId;
            // Station can come as 'station' or 'stationHint' (backward compat)
            if (card.dataset.station) context.station = card.dataset.station;
            else if (card.dataset.stationHint) context.station = card.dataset.stationHint;
            if (card.dataset.channel) context.channel = card.dataset.channel;

            this.currentMenuContext = Object.keys(context).length > 0 ? context : null;
        },

        /**
         * Get the currently stored menu context.
         * Used by ModifierFlow and other callers.
         */
        getMenuContext() {
            return this.currentMenuContext;
        },

        /**
         * Close modal
         */
        close() {
            if (!this.modal || !this.isOpen) return;

            this.modal.classList.remove('is-open');
            this.modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            this.isOpen = false;
            this.currentProductId = null;
            this.currentMenuContext = null; // Phase 7.4: Clear context on close

            // Clear content after animation
            setTimeout(() => {
                if (this.content) {
                    this.content.innerHTML = '';
                }
            }, 300);

            // Return focus
            if (this.focusedElementBeforeOpen) {
                this.focusedElementBeforeOpen.focus();
            }

            $(document.body).trigger('ur_modal_closed');
        },

        /**
         * Show loading spinner
         */
        showLoading() {
            this.isLoading = true;
            if (this.content) {
                this.content.innerHTML = `
                    <div class="ur-modal__content--loading">
                        <div class="ur-modal__spinner"></div>
                    </div>
                `;
            }
        },

        /**
         * Update quantity in modal
         */
        updateQuantity(newQuantity) {
            const input = this.modal?.querySelector('[data-ur-modal-quantity]');
            const max = input ? parseInt(input.max, 10) || 99 : 99;
            
            // Clamp value
            newQuantity = Math.max(1, Math.min(newQuantity, max));
            this.currentQuantity = newQuantity;

            // Update input
            if (input) {
                input.value = newQuantity;
            }

            // Update total price display
            this.updateTotalDisplay();
        },

        /**
         * Update the total price display
         */
        updateTotalDisplay() {
            const totalEl = this.modal?.querySelector('[data-ur-modal-total]');
            if (!totalEl || !this.currentPrice) return;

            const total = this.currentPrice * this.currentQuantity;
            
            // Format price using WooCommerce format from config
            const formatted = this.formatPrice(total);
            totalEl.textContent = formatted;
        },

        /**
         * Format price according to WooCommerce settings
         */
        formatPrice(price) {
            const { symbol, decimal, thousand, decimals, position } = config.currency || {};
            
            const formatted = price.toFixed(decimals || 2)
                .replace('.', decimal || '.')
                .replace(/\B(?=(\d{3})+(?!\d))/g, thousand || ',');

            switch (position) {
                case 'left_space':
                    return `${symbol} ${formatted}`;
                case 'right':
                    return `${formatted}${symbol}`;
                case 'right_space':
                    return `${formatted} ${symbol}`;
                default: // 'left'
                    return `${symbol}${formatted}`;
            }
        },

        /**
         * Add to cart from modal
         * Phase 7.4: Pass captured menu context to cart
         */
        addToCart(productId) {
            if (Cart.isLoading) return;

            const btn = this.modal?.querySelector('[data-ur-modal-add-to-cart]');
            
            // Phase 7.4: Include menu context captured when modal opened
            const cartItemData = {};
            if (this.currentMenuContext) {
                cartItemData.menu_context = this.currentMenuContext;
            }
            
            // Use Cart module's addToCart - it handles everything
            Cart.addToCart(productId, this.currentQuantity, cartItemData, btn);

            // Close modal after successful add
            $(document.body).one('ur_added_to_cart', () => {
                this.close();
            });
        },

        /**
         * Trap focus inside modal
         */
        trapFocus() {
            const focusableSelectors = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
            const focusableElements = this.modal.querySelectorAll(focusableSelectors);
            
            if (focusableElements.length === 0) return;
            
            const firstFocusable = focusableElements[0];
            const lastFocusable = focusableElements[focusableElements.length - 1];

            const handleTab = (e) => {
                if (!this.isOpen || e.key !== 'Tab') return;

                if (e.shiftKey) {
                    if (document.activeElement === firstFocusable) {
                        lastFocusable.focus();
                        e.preventDefault();
                    }
                } else {
                    if (document.activeElement === lastFocusable) {
                        firstFocusable.focus();
                        e.preventDefault();
                    }
                }
            };

            // Remove old listener and add new
            if (this._tabHandler) {
                this.modal.removeEventListener('keydown', this._tabHandler);
            }
            this._tabHandler = handleTab;
            this.modal.addEventListener('keydown', this._tabHandler);
        }
    };

    /**
     * Modifier Flow Module
     * Handles step-by-step modifier selection inside the product modal
     */
    const ModifierFlow = {
        container: null,
        currentStep: 0,
        totalSteps: 0,
        selections: {},
        basePrice: 0,
        flowDefinition: null, // Full flow definition for building normalized data

        /**
         * Initialize flow (called when modal content loads)
         */
        init(container, basePrice = 0) {
            this.container = container;
            if (!this.container) return false;

            this.basePrice = basePrice;
            this.totalSteps = parseInt(this.container.dataset.totalSteps, 10) || 0;
            this.currentStep = 0;
            this.selections = {};

            // Parse flow definition for normalized data building
            try {
                this.flowDefinition = JSON.parse(this.container.dataset.flowDefinition || '{}');
            } catch (e) {
                this.flowDefinition = { id: 0, name: '', groups: [] };
            }

            if (this.totalSteps === 0) return false;

            this.bindEvents();
            this.initializeDefaults();
            this.updateUI();

            return true;
        },

        bindEvents() {
            // Option selection
            $(this.container).on('change', 'input[type="radio"], input[type="checkbox"]', (e) => {
                this.handleOptionChange(e.target);
            });

            // Click on option label (visual feedback)
            $(this.container).on('click', '.ur-modifier-option', (e) => {
                if (e.target.tagName === 'INPUT') return;
                const input = e.currentTarget.querySelector('input');
                if (input) {
                    input.checked = input.type === 'checkbox' ? !input.checked : true;
                    $(input).trigger('change');
                }
            });

            // Navigation
            $(this.container).on('click', '[data-ur-flow-next]', () => this.nextStep());
            $(this.container).on('click', '[data-ur-flow-prev]', () => this.prevStep());
        },

        initializeDefaults() {
            // Collect default selections from checked inputs
            this.container.querySelectorAll('.ur-modifier-step').forEach(step => {
                const groupId = step.dataset.groupId;
                this.selections[groupId] = [];

                step.querySelectorAll('input:checked').forEach(input => {
                    this.selections[groupId].push(input.value);
                });
            });
        },

        handleOptionChange(input) {
            const step = input.closest('.ur-modifier-step');
            const groupId = step.dataset.groupId;
            const groupType = step.dataset.groupType;

            if (groupType === 'single') {
                // Radio - single selection
                this.selections[groupId] = [input.value];
            } else {
                // Checkbox - multiple selection
                this.selections[groupId] = [];
                step.querySelectorAll('input:checked').forEach(checked => {
                    this.selections[groupId].push(checked.value);
                });
            }

            // Update visual state
            step.querySelectorAll('.ur-modifier-option').forEach(opt => {
                const optInput = opt.querySelector('input');
                opt.classList.toggle('is-selected', optInput?.checked);
            });

            // Clear error
            const errorEl = step.querySelector('[data-ur-step-error]');
            if (errorEl) {
                errorEl.hidden = true;
                errorEl.textContent = '';
            }

            this.updateSummary();
            this.updateTotal();
        },

        validateCurrentStep() {
            const step = this.container.querySelector(`[data-ur-step="${this.currentStep}"]`);
            if (!step) return { valid: true };

            const groupId = step.dataset.groupId;
            const required = step.dataset.required === 'true';
            const groupType = step.dataset.groupType;
            const minSelect = parseInt(step.dataset.minSelect, 10) || 0;
            const maxSelect = parseInt(step.dataset.maxSelect, 10) || 0;

            const selected = this.selections[groupId] || [];
            const count = selected.length;

            // Required check
            if (required && count === 0) {
                return { valid: false, error: config.i18n.modifierRequired || 'Please make a selection.' };
            }

            // Min/max for multiple
            if (groupType === 'multiple') {
                if (minSelect > 0 && count < minSelect) {
                    return { valid: false, error: `Please select at least ${minSelect} option(s).` };
                }
                if (maxSelect > 0 && count > maxSelect) {
                    return { valid: false, error: `Please select no more than ${maxSelect} option(s).` };
                }
            }

            return { valid: true };
        },

        nextStep() {
            const validation = this.validateCurrentStep();

            if (!validation.valid) {
                this.showStepError(validation.error);
                return;
            }

            if (this.currentStep < this.totalSteps - 1) {
                this.currentStep++;
                this.updateUI();
            } else {
                // Flow complete - trigger add to cart
                this.complete();
            }
        },

        prevStep() {
            if (this.currentStep > 0) {
                this.currentStep--;
                this.updateUI();
            }
        },

        showStepError(message) {
            const step = this.container.querySelector(`[data-ur-step="${this.currentStep}"]`);
            const errorEl = step?.querySelector('[data-ur-step-error]');
            if (errorEl) {
                errorEl.textContent = message;
                errorEl.hidden = false;
            }
        },

        updateUI() {
            // Update steps visibility
            this.container.querySelectorAll('.ur-modifier-step').forEach((step, index) => {
                step.classList.toggle('is-active', index === this.currentStep);
                step.hidden = index !== this.currentStep;
            });

            // Update progress
            const progressFill = this.container.querySelector('[data-ur-progress-fill]');
            const progressText = this.container.querySelector('[data-ur-progress-text]');

            if (progressFill) {
                const percent = ((this.currentStep + 1) / this.totalSteps) * 100;
                progressFill.style.width = `${percent}%`;
            }

            if (progressText) {
                progressText.textContent = `Step ${this.currentStep + 1} of ${this.totalSteps}`;
            }

            // Update navigation buttons
            const prevBtn = this.container.querySelector('[data-ur-flow-prev]');
            const nextBtn = this.container.querySelector('[data-ur-flow-next]');

            if (prevBtn) {
                prevBtn.hidden = this.currentStep === 0;
            }

            if (nextBtn) {
                const isLastStep = this.currentStep === this.totalSteps - 1;
                nextBtn.innerHTML = isLastStep 
                    ? `${config.i18n.addToCart || 'Add to Order'} <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/></svg>`
                    : `${config.i18n.next || 'Next'} <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd"/></svg>`;
            }

            this.updateSummary();
        },

        updateSummary() {
            const summaryItems = this.container.querySelector('[data-ur-summary-items]');
            if (!summaryItems) return;

            let html = '';

            Object.keys(this.selections).forEach(groupId => {
                const selections = this.selections[groupId];
                if (!selections.length) return;

                selections.forEach(optionId => {
                    const input = this.container.querySelector(`input[value="${optionId}"]`);
                    if (!input) return;

                    const option = input.closest('.ur-modifier-option');
                    const label = option?.querySelector('.ur-modifier-option__label')?.textContent || optionId;

                    html += `<span class="ur-modifier-flow__summary-item">${label}</span>`;
                });
            });

            summaryItems.innerHTML = html;
        },

        updateTotal() {
            let modifierTotal = 0;

            Object.keys(this.selections).forEach(groupId => {
                const selections = this.selections[groupId];
                selections.forEach(optionId => {
                    const input = this.container.querySelector(`input[value="${optionId}"]`);
                    if (input) {
                        modifierTotal += parseFloat(input.dataset.price) || 0;
                    }
                });
            });

            // Update ProductModal's price tracking
            const totalPrice = this.basePrice + modifierTotal;
            
            // Update the modal total display
            const totalEl = document.querySelector('[data-ur-modal-total]');
            if (totalEl) {
                totalEl.textContent = ProductModal.formatPrice(totalPrice * ProductModal.currentQuantity);
            }
        },

        complete() {
            // Validate all steps
            const originalStep = this.currentStep;
            let allValid = true;

            for (let i = 0; i < this.totalSteps; i++) {
                this.currentStep = i;
                const validation = this.validateCurrentStep();
                if (!validation.valid) {
                    allValid = false;
                    this.updateUI();
                    this.showStepError(validation.error);
                    break;
                }
            }

            if (!allValid) return;

            this.currentStep = originalStep;

            // Build normalized cart data structure
            const normalizedData = this.buildNormalizedData();

            // Prepare cart item data
            const cartItemData = {
                ur_modifiers: normalizedData
            };

            // Get special instructions if present
            const instructions = this.container.querySelector('[name="ur_special_instructions"]');
            if (instructions?.value) {
                cartItemData.ur_special_instructions = instructions.value;
            }

            // Phase 7.4: Include menu context from ProductModal
            const menuContext = ProductModal.getMenuContext();
            if (menuContext) {
                cartItemData.menu_context = menuContext;
            }

            // Trigger add to cart via ProductModal
            const productId = this.container.dataset.productId;
            Cart.addToCart(productId, ProductModal.currentQuantity, cartItemData);

            // Close modal on success
            $(document.body).one('ur_added_to_cart', () => {
                ProductModal.close();
            });
        },

        /**
         * Build normalized data structure for cart storage
         */
        buildNormalizedData() {
            const groups = [];
            let modifierTotal = 0;

            // Build groups from flow definition
            if (this.flowDefinition && this.flowDefinition.groups) {
                this.flowDefinition.groups.forEach(groupDef => {
                    const selectedIds = this.selections[groupDef.id] || [];
                    if (selectedIds.length === 0) return;

                    const groupData = {
                        group_id: groupDef.id,
                        label: groupDef.label || '',
                        type: groupDef.type || 'single',
                        options: []
                    };

                    // Find selected options
                    if (groupDef.options) {
                        groupDef.options.forEach(optDef => {
                            if (selectedIds.includes(optDef.id)) {
                                const price = parseFloat(optDef.price) || 0;
                                modifierTotal += price;

                                groupData.options.push({
                                    id: optDef.id,
                                    label: optDef.label || '',
                                    price: price,
                                    kitchen_label: optDef.kitchen_label || optDef.label || '',
                                    qty: 1
                                });
                            }
                        });
                    }

                    if (groupData.options.length > 0) {
                        groups.push(groupData);
                    }
                });
            }

            return {
                version: '1.0',
                flow_id: this.flowDefinition?.id || 0,
                flow_name: this.flowDefinition?.name || '',
                groups: groups,
                modifier_total: modifierTotal,
                created_at: Math.floor(Date.now() / 1000)
            };
        },

        /**
         * Get current selections
         */
        getSelections() {
            return this.selections;
        },

        /**
         * Reset flow
         */
        reset() {
            this.currentStep = 0;
            this.selections = {};
            this.initializeDefaults();
            this.updateUI();
        }
    };

    /**
     * Hook ModifierFlow into ProductModal
     */
    $(document.body).on('ur_modal_opened', function(e, product) {
        // Wait for DOM to update
        setTimeout(() => {
            const flowContainer = document.querySelector('[data-ur-modifier-flow]');
            if (flowContainer) {
                const basePrice = parseFloat(product?.price) || ProductModal.currentPrice || 0;
                ModifierFlow.init(flowContainer, basePrice);

                // Hide quantity selector when modifiers are present
                const quantityRegion = document.querySelector('[data-ur-quantity-region]');
                if (quantityRegion) {
                    quantityRegion.hidden = true;
                }
            }
        }, 50);
    });

    /**
     * Initialize on DOM ready
     */
    $(function() {
        console.log('[URST] DOM ready, initializing modules...');
        
        CartDrawer.init();
        console.log('[URST] CartDrawer initialized');
        
        Cart.init();
        console.log('[URST] Cart initialized');
        
        ProductModal.init();
        console.log('[URST] ProductModal initialized');

        // Expose to global namespace for programmatic access
        window.UnstoppableRestaurant = {
            CartDrawer,
            Cart,
            ProductModal,
            ModifierFlow,
            config
        };
        
        console.log('[URST] All modules initialized, config:', config);
    });

})(jQuery);

/**
 * Notification and Loading Styles (injected via JS)
 */
(function() {
    // Prevent duplicate style injection
    if (document.getElementById('ur-js-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'ur-js-styles';
    style.textContent = `
        /* Notification Toast */
        .ur-notification {
            position: fixed;
            bottom: var(--ur-space-6, 24px);
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: var(--ur-space-3, 12px);
            padding: var(--ur-space-3, 12px) var(--ur-space-4, 16px);
            background: var(--ur-color-gray-800, #1f2937);
            color: var(--ur-color-white, #fff);
            border-radius: var(--ur-radius-lg, 8px);
            box-shadow: var(--ur-shadow-lg);
            z-index: 9999;
            animation: ur-notification-in 0.3s ease;
            font-size: var(--ur-font-size-sm, 14px);
        }

        .ur-notification--success {
            background: var(--ur-color-success, #059669);
        }

        .ur-notification--error {
            background: var(--ur-color-error, #dc2626);
        }

        .ur-notification--hiding {
            animation: ur-notification-out 0.3s ease forwards;
        }

        .ur-notification__close {
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 0;
            opacity: 0.7;
            transition: opacity 0.15s;
        }

        .ur-notification__close:hover {
            opacity: 1;
        }

        @keyframes ur-notification-in {
            from {
                opacity: 0;
                transform: translate(-50%, 20px);
            }
            to {
                opacity: 1;
                transform: translate(-50%, 0);
            }
        }

        @keyframes ur-notification-out {
            from {
                opacity: 1;
                transform: translate(-50%, 0);
            }
            to {
                opacity: 0;
                transform: translate(-50%, 20px);
            }
        }

        /* Button Loading State */
        .ur-product-card__add-btn.is-loading,
        .ur-quantity__btn.is-loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .ur-product-card__add-btn.is-loading svg,
        .ur-quantity__btn.is-loading svg {
            animation: ur-spin 0.6s linear infinite;
        }

        @keyframes ur-spin {
            to { transform: rotate(360deg); }
        }

        /* Cart Item Removing State */
        .ur-cart-item.is-removing {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Mobile notification position */
        @media (max-width: 639px) {
            .ur-notification {
                bottom: calc(var(--ur-space-4, 16px) + 4rem);
                left: var(--ur-space-4, 16px);
                right: var(--ur-space-4, 16px);
                transform: none;
            }
            
            @keyframes ur-notification-in {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes ur-notification-out {
                from {
                    opacity: 1;
                    transform: translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateY(20px);
                }
            }
        }
    `;
    document.head.appendChild(style);
})();
