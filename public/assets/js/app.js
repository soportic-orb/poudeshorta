/* Interaccions del web públic: selector de quantitats i resum de la compra. */
(function () {
    'use strict';

    var euro = function (cents) {
        return (cents / 100).toFixed(2).replace('.', ',') + ' €';
    };

    function initQuantitySelectors() {
        var form = document.querySelector('[data-cart-form]');
        if (!form) { return; }

        var inputs = Array.prototype.slice.call(form.querySelectorAll('.qty-input'));
        var totalEl = form.querySelector('[data-cart-total]');
        var countEl = form.querySelector('[data-cart-count]');
        var submitEl = form.querySelector('[data-cart-submit]');
        var maxTotal = parseInt(form.getAttribute('data-max-total') || '0', 10);

        function clamp(input, value) {
            var min = parseInt(input.getAttribute('min') || '0', 10);
            var max = parseInt(input.getAttribute('max') || '99', 10);
            if (isNaN(value) || value < min) { value = min; }
            if (value > max) { value = max; }
            return value;
        }

        function refresh() {
            var total = 0;
            var count = 0;

            inputs.forEach(function (input) {
                var qty = clamp(input, parseInt(input.value, 10));
                input.value = String(qty);
                total += qty * parseInt(input.getAttribute('data-price') || '0', 10);
                count += qty;

                var card = input.closest('.ticket-card');
                if (card) { card.classList.toggle('is-selected', qty > 0); }

                var group = input.closest('.qty');
                if (group) {
                    var minus = group.querySelector('[data-qty="-1"]');
                    var plus = group.querySelector('[data-qty="1"]');
                    if (minus) { minus.disabled = qty <= parseInt(input.getAttribute('min') || '0', 10); }
                    if (plus) {
                        plus.disabled = qty >= parseInt(input.getAttribute('max') || '99', 10) ||
                            (maxTotal > 0 && count >= maxTotal);
                    }
                }
            });

            if (totalEl) { totalEl.textContent = euro(total); }
            if (countEl) {
                countEl.textContent = count === 0
                    ? 'Encara no has triat cap inscripció'
                    : (count === 1 ? '1 inscripció seleccionada' : count + ' inscripcions seleccionades');
            }
            if (submitEl) { submitEl.disabled = count === 0; }
        }

        form.addEventListener('click', function (event) {
            var button = event.target.closest('[data-qty]');
            if (!button) { return; }
            event.preventDefault();

            var input = button.parentNode.querySelector('.qty-input');
            if (!input) { return; }
            input.value = String(clamp(input, (parseInt(input.value, 10) || 0) + parseInt(button.getAttribute('data-qty'), 10)));
            refresh();
        });

        inputs.forEach(function (input) {
            input.addEventListener('input', refresh);
            input.addEventListener('blur', refresh);
        });

        refresh();
    }

    /* Camps de nom d'assistent: copia el nom del comprador al primer assistent. */
    function initAttendeeShortcut() {
        var source = document.querySelector('[data-buyer-name]');
        var target = document.querySelector('[data-first-attendee]');
        var toggle = document.querySelector('[data-copy-buyer]');
        if (!source || !target || !toggle) { return; }

        function sync() {
            if (!toggle.checked) { return; }
            var surname = document.querySelector('[data-buyer-surname]');
            target.value = (source.value + ' ' + (surname ? surname.value : '')).trim();
        }

        toggle.addEventListener('change', function () {
            target.readOnly = toggle.checked;
            if (toggle.checked) { sync(); } else { target.focus(); }
        });
        source.addEventListener('input', sync);

        var surname = document.querySelector('[data-buyer-surname]');
        if (surname) { surname.addEventListener('input', sync); }
    }

    /* Evita enviaments duplicats en formularis de pagament. */
    function initSubmitGuard() {
        document.querySelectorAll('form[data-guard]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('[type="submit"]');
                if (!button || button.disabled) { return; }
                button.disabled = true;
                button.dataset.label = button.textContent;
                button.textContent = button.getAttribute('data-loading') || 'Un moment…';
                // Si la navegació falla, tornem a habilitar el botó.
                setTimeout(function () {
                    button.disabled = false;
                    button.textContent = button.dataset.label;
                }, 15000);
            });
        });
    }

    /* Botons per copiar text (URL del webhook, testimoni del cron...). */
    function initCopyButtons() {
        document.querySelectorAll('[data-copy-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                var target = document.querySelector(button.getAttribute('data-copy-target'));
                if (!target) { return; }
                target.select();
                target.setSelectionRange(0, 99999);
                try {
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(target.value);
                    } else {
                        document.execCommand('copy');
                    }
                    var original = button.textContent;
                    button.textContent = 'Copiat!';
                    setTimeout(function () { button.textContent = original; }, 1600);
                } catch (e) { /* el navegador no ho permet */ }
            });
        });
    }

    function initConfirmForms() {
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.getAttribute('data-confirm'))) {
                    event.preventDefault();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initQuantitySelectors();
        initAttendeeShortcut();
        initSubmitGuard();
        initCopyButtons();
        initConfirmForms();
    });
})();
