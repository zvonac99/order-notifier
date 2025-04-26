(function ($) {
    let lastCheck = new Date().toISOString();
    let storedOrderId = null;
    let dismissedOrderId = null;

    // Adaptivne varijable
    let currentInterval = parseInt(OrderNotifierData.interval, 10) * 1000;
    let adaptiveStep = parseInt(OrderNotifierData.adaptive_step, 10) * 1000 || 60000;
    let maxInterval = 10 * 60 * 1000; // 10 min
    let idleCount = 0;
    let checkTimer = null;

    // Cookie funkcije
    function setCookie(name, value, minutes = 30) {
        const d = new Date();
        d.setTime(d.getTime() + (minutes * 60 * 1000));
        document.cookie = `${name}=${value};expires=${d.toUTCString()};path=/`;
    }

    function getCookie(name) {
        const cookies = document.cookie.split(';');
        for (let i = 0; i < cookies.length; i++) {
            const c = cookies[i].trim();
            if (c.startsWith(name + '=')) {
                return c.substring(name.length + 1);
            }
        }
        return null;
    }

    function deleteCookie(name) {
        document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`;
    }

    // Provjera ID-a narudžbe
    function shouldReloadAndNotify(latestId) {
        storedOrderId = getCookie('last_order_id');

        console.log('🔍 Provjeravam ID narudžbe:', latestId);

        if (!storedOrderId) {
            setCookie('last_order_id', latestId);
            storedOrderId = latestId;
            console.log('🚚 Spremam prvi ID narudžbe:', latestId);
            return false;
        }

        if (parseInt(storedOrderId, 10) !== parseInt(latestId, 10)) {
            setCookie('last_order_id', latestId);
            storedOrderId = latestId;
            deleteCookie('dismissed_order_id');
            console.log('🔄 ID narudžbe se promijenio – potrebno reloadati.');
            return true;
        }

        console.log('✅ ID narudžbe nije promijenjen.');
        return false;
    }

    // Prikaz obavijesti
    function showNotification(latestId) {
        dismissedOrderId = getCookie('dismissed_order_id');

        if (parseInt(dismissedOrderId, 10) === parseInt(latestId, 10)) {
            console.log('❌ Obavijest je već bila zatvorena, ne prikazujem ponovno.');
            return false;
        }

        console.log('🛎 Prikazujem novu obavijest...');

        toastr.options = {
            "positionClass": "toast-top-right",
            "timeOut": "0",
            "extendedTimeOut": "0",
            "preventDuplicates": true,
            "progressBar": true,
            "closeButton": true,
            "onCloseClick": function () {
                console.log('🗂 Zatvorena obavijest, spremam ID u cookie.');
                setCookie('dismissed_order_id', latestId);
            }
        };

        toastr.info('Nova narudžba je stigla!', 'WooCommerce');

        const menuLink = $('#toplevel_page_woocommerce ul.wp-submenu a[href*="edit.php?post_type=shop_order"]');
        if (menuLink.length && !menuLink.find('.order-badge').length) {
            menuLink.append('<span class="order-badge">●</span>');
        }
        return true;
    }

    // Resetiranje intervala
    function resetIdleCount() {
        idleCount = 0;
        currentInterval = parseInt(OrderNotifierData.interval, 10) * 1000;
    }

    // Prilagodba intervala kada nema novih narudžbi
    function adjustCheckInterval() {
        idleCount++;
        console.log(`Broj koraka ${idleCount}.`);
        if (idleCount >= parseInt(OrderNotifierData.adaptive_attempts, 10) && currentInterval < maxInterval) {
            currentInterval += adaptiveStep;
            console.log(`😴 Idle provjere: ${idleCount}. Novi interval: ${currentInterval / 1000}s (korak: ${adaptiveStep / 1000}s)`);

            clearInterval(checkTimer);
            checkTimer = setInterval(adaptiveCheck, currentInterval);
            idleCount = 0;
        }
    }

    // Provjera nakon reloadanja stranice
    function handlePageReload(latestId) {
        if (OrderNotifierData.reload_table === 'yes' && location.href.includes('edit.php?post_type=shop_order')) {
            console.log('🔁 Provjeravam je li nova narudžba prije reloadanja stranice...');

            if (shouldReloadAndNotify(latestId)) {
                console.log('🔁 Osvježavam stranicu...');
                location.reload();
            } else {
                console.log('❌ Nema nove narudžbe. Stranica neće biti reloadana.');
            }
        }
    }

    // Glavna funkcija za provjeru s adaptivnim intervalom
    function adaptiveCheck() {
        console.log('⏱ Adaptivna provjera narudžbi...');

        storedOrderId = getCookie('last_order_id');
        dismissedOrderId = getCookie('dismissed_order_id');
        console.log('📦 Učitani cookie podaci:', { storedOrderId, dismissedOrderId });

        $.post(OrderNotifierData.ajax_url, {
            action: 'check_new_orders',
            last_check: lastCheck,
            statuses: OrderNotifierData.statuses,
            nonce: OrderNotifierData.nonce
        }, function (response) {
            console.log('📬 Odgovor sa servera:', response);

            const latestId = response.data.latest_id;
            const latestTime = response.data.latest_time;

            if (response.success && (response.data.new_order || shouldReloadAndNotify(latestId))) {
                lastCheck = latestTime;

                const prikazana = showNotification(latestId);

                if (prikazana) {
                    resetIdleCount();
                }

                handlePageReload(latestId);
            }
            adjustCheckInterval();
        });
    }

    function resetAdaptiveInterval() {
        if (checkTimer) clearInterval(checkTimer);
        resetIdleCount();
        checkTimer = setInterval(adaptiveCheck, currentInterval);
    }

    $(document).ready(function () {
        console.log('🚀 Order notifier pokrenut.');

        const isReload = performance.getEntriesByType("navigation")[0]?.type === "reload";

        if (isReload && location.href.includes('edit.php?post_type=shop_order')) {
            console.log('♻️ Stranica narudžbi reloadana – resetiram interval.');
            resetAdaptiveInterval();
        } else {
            console.log('🟢 Pokrećem interval normalno.');
            resetAdaptiveInterval();
        }
    });
})(jQuery);
