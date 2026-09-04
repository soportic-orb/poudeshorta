/* Selecció múltiple al llistat d'inscripcions. */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('bulk-form');
        var barra = document.getElementById('bulk');
        if (!form || !barra) { return; }

        var compte = document.getElementById('bulk-compte');
        var totes = document.getElementById('tria-tot');
        var caselles = Array.prototype.slice.call(form.querySelectorAll('input[name="tickets[]"]'));

        function actualitza() {
            var triades = caselles.filter(function (c) { return c.checked; }).length;

            barra.hidden = triades === 0;
            compte.textContent = triades === 1
                ? '1 entrada seleccionada'
                : triades + ' entrades seleccionades';

            if (totes) {
                totes.checked = triades > 0 && triades === caselles.length;
                totes.indeterminate = triades > 0 && triades < caselles.length;
            }
        }

        caselles.forEach(function (c) { c.addEventListener('change', actualitza); });

        if (totes) {
            totes.addEventListener('change', function () {
                caselles.forEach(function (c) { c.checked = totes.checked; });
                actualitza();
            });
        }

        document.getElementById('bulk-neteja').addEventListener('click', function () {
            caselles.forEach(function (c) { c.checked = false; });
            actualitza();
        });

        actualitza();
    });
})();
