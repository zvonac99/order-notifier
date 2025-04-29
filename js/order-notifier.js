/*!
 * Order Notifier for WooCommerce Admin
 * Version: 1.5
 * Updated: 2025-04-28
 * Author: zvonac99
 */

(function ($) {
    const initialOrderId = parseInt(OrderNotifierData.first_order_id, 10) || 0;
    let lastCheck = new Date().toISOString();
    let storedOrderId = null;
    let dismissedOrderId = null;

    let currentInterval = parseInt(OrderNotifierData.interval, 10) * 1000;
    const adaptiveStep = parseInt(OrderNotifierData.adaptive_step, 10) * 1000 || 60000;
    const maxInterval = 10 * 60 * 1000;
    let idleCount = 0;
    let checkTimer = null;

    const orderBadgeSelector = '#toplevel_page_woocommerce ul.wp-submenu .order-badge';

    const CookieManager = {
        set(name, value, options = {}) {
            let expires = '';
            if (options.minutes) {
                expires = new Date(Date.now() + options.minutes * 60 * 1000).toUTCString();
            } else if (options.days) {
                expires = new Date(Date.now() + options.days * 24 * 60 * 60 * 1000).toUTCString();
            }
            document.cookie = `${name}=${encodeURIComponent(value)}; path=/${expires ? '; expires=' + expires : ''}`;
        },
        get(name) {
            return document.cookie.split('; ').reduce((acc, cookie) => {
                const [k, v] = cookie.split('=');
                return k === name ? decodeURIComponent(v) : acc;
            }, null);
        },
        delete(name) {
            document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/`;
        }
    };

    const getDismissedCookieName = () => `dismissed_order_id_${OrderNotifierData.user_hash}`;
    const getGlobalDismissCookieName = () => `global_dismiss_status_${OrderNotifierData.user_hash}`;

    function updateStoredOrder(latestId) {
        CookieManager.set('last_order_id', latestId, { days: 30 });
        storedOrderId = latestId;
        CookieManager.delete(getDismissedCookieName());
        CookieManager.delete(getGlobalDismissCookieName());
    }

    function shouldReloadAndNotify(latestId) {
        storedOrderId = CookieManager.get('last_order_id');

        if (!storedOrderId || parseInt(storedOrderId, 10) < initialOrderId) {
            CookieManager.set('last_order_id', initialOrderId, { days: 30 });
            storedOrderId = initialOrderId;
        }

        if (parseInt(latestId, 10) < initialOrderId) return false;

        if (parseInt(storedOrderId, 10) !== parseInt(latestId, 10)) {
            updateStoredOrder(latestId);
            return true;
        }

        return false;
    }

    function showNotification(latestId) {
        dismissedOrderId = CookieManager.get(getDismissedCookieName());
        const globalDismiss = CookieManager.get(getGlobalDismissCookieName()) || '';

        if (globalDismiss === 'dismissed' || parseInt(dismissedOrderId, 10) === parseInt(latestId, 10)) {
            return false;
        }

        toastr.options = {
            positionClass: "toast-top-right",
            timeOut: 0,
            extendedTimeOut: 0,
            preventDuplicates: true,
            progressBar: true,
            closeButton: true,
            onCloseClick: () => {
                CookieManager.set(getDismissedCookieName(), latestId);
                CookieManager.set(getGlobalDismissCookieName(), 'dismissed', { days: 7 });
                removeOrderBadge();
            }
        };

        toastr.info('Nova narudžba je stigla!', 'WooCommerce');
        addOrderBadge();
        return true;
    }

    function addOrderBadge() {
        const menuLink = $('#toplevel_page_woocommerce ul.wp-submenu a[href*="edit.php?post_type=shop_order"]');
        if (menuLink.length && !$(orderBadgeSelector).length) {
            menuLink.append('<span class="order-badge">●</span>');
        }
    }

    function removeOrderBadge() {
        $(orderBadgeSelector).remove();
    }

    function resetIdleCount() {
        idleCount = 0;
        currentInterval = parseInt(OrderNotifierData.interval, 10) * 1000;
    }

    function restartCheckTimer() {
        if (checkTimer) clearInterval(checkTimer);
        checkTimer = setInterval(adaptiveCheck, currentInterval);
    }

    function adjustCheckInterval() {
        idleCount++;
        if (idleCount >= parseInt(OrderNotifierData.adaptive_attempts, 10) && currentInterval < maxInterval) {
            currentInterval += adaptiveStep;
            restartCheckTimer();
            idleCount = 0;
        }
    }

    function handlePageReload(latestId) {
        if (OrderNotifierData.reload_table === 'yes' && location.href.includes('edit.php?post_type=shop_order')) {
            if (shouldReloadAndNotify(latestId)) {
                location.reload();
            }
        }
    }

    function adaptiveCheck() {
        $.post(OrderNotifierData.ajax_url, {
            action: 'check_new_orders',
            last_check: lastCheck,
            statuses: OrderNotifierData.statuses,
            nonce: OrderNotifierData.nonce
        }, (response) => {
            if (response.success) {
                const { latest_id: latestId, latest_time: latestTime, new_order } = response.data;
                if (new_order || shouldReloadAndNotify(latestId)) {
                    lastCheck = latestTime;
                    const prikazana = showNotification(latestId);
                    if (prikazana) resetIdleCount();
                    handlePageReload(latestId);
                }
            }
            adjustCheckInterval();
        });
    }

    $(document).ready(() => {
        const isReload = performance.getEntriesByType("navigation")[0]?.type === "reload";
        resetIdleCount();

        if (!CookieManager.get('last_order_id')) {
            CookieManager.set('last_order_id', initialOrderId, { days: 30 });
            storedOrderId = initialOrderId;
        }
        
        if (isReload && location.href.includes('edit.php?post_type=shop_order')) {
            restartCheckTimer();
        } else {
            restartCheckTimer();
        }
    });

})(jQuery);
