/* Control d'accés: validació d'entrades sense recarregar la pàgina. */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('scan-form');
        if (!form) { return; }

        var input = document.getElementById('scan-code');
        var result = document.getElementById('scan-result');
        var counter = document.getElementById('scan-counter');

        var icons = {
            valida: '✅', repetida: '⚠️', anullada: '⛔',
            impagada: '⛔', desconeguda: '❓', buit: '❓', error: '⚠️'
        };

        window.pdshMostraResultat = function (data, ok) {
            var state = data.estat || 'error';
            result.className = 'scan-result ' + (ok ? 'is-ok' : (state === 'repetida' ? 'is-warning' : 'is-error'));
            result.hidden = false;

            var parts = [];
            parts.push('<span class="scan-result__icon">' + (icons[state] || '⚠️') + '</span>');
            parts.push('<p class="scan-result__title">' + escapeHtml(data.missatge || 'Error') + '</p>');

            if (data.assistent) {
                parts.push('<p class="scan-result__meta"><strong>' + escapeHtml(data.assistent) + '</strong><br>'
                    + escapeHtml(data.tipus || '') + ' · ' + escapeHtml(data.codi || '') + '</p>');
            }
            result.innerHTML = parts.join('');

            if (ok && counter) {
                counter.textContent = String((parseInt(counter.textContent, 10) || 0) + 1);
            }
        };

        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var raw = input.value.trim();
            if (!raw) { return; }

            // El camp accepta tant el codi com l'URL completa que porta el QR.
            var match = raw.match(/([A-Za-z0-9]{6,12})\s*$/);
            var code = match ? match[1] : raw;

            var body = new FormData();
            body.append('code', code);
            body.append('_token', form.querySelector('[name="_token"]').value);

            fetch(form.action, {
                method: 'POST',
                body: body,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { data: data, ok: response.ok && data.ok };
                    });
                })
                .then(function (payload) { window.pdshMostraResultat(payload.data, payload.ok); })
                .catch(function () {
                    window.pdshMostraResultat({ estat: 'error', missatge: 'No s\'ha pogut connectar amb el servidor.' }, false);
                })
                .finally(function () {
                    input.value = '';
                    if (window.matchMedia('(min-width: 700px)').matches) {
                        input.focus();
                    }
                });
        });

        // Al mòbil, enfocar el camp obre el teclat i tapa mitja pantalla:
        // només ho fem en pantalles grans.
        if (window.matchMedia('(min-width: 700px)').matches) {
            input.focus();
        }
    });
})();
