/* Inscripció manual: files d'assistent que es poden afegir i treure.
 *
 * Els camps del model no porten «name» sinó «data-name», per no enviar-se
 * mai; en clonar la fila se'ls hi posa amb l'índex que els toca. */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var contenidor = document.getElementById('manual-rows');
        var model = document.getElementById('manual-row-model');
        if (!contenidor || !model) { return; }

        var total = document.getElementById('manual-total');
        var prefix = contenidor.getAttribute('data-prefix') || '';
        var sufix = contenidor.getAttribute('data-suffix') || '';

        function formata(cents) {
            var enter = Math.floor(Math.abs(cents) / 100);
            var decimals = String(Math.abs(cents) % 100).padStart(2, '0');
            var milers = String(enter).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return prefix + milers + ',' + decimals + sufix;
        }

        /* Renumera noms i etiquetes després de qualsevol canvi, de manera que
           els índexs quedin sempre seguits encara que se'n tregui una del mig. */
        function renumera() {
            var files = contenidor.querySelectorAll('.manual-row');
            var suma = 0;

            Array.prototype.forEach.call(files, function (fila, i) {
                fila.querySelector('.manual-row__num').textContent = 'Entrada ' + (i + 1);

                Array.prototype.forEach.call(fila.querySelectorAll('[data-name]'), function (camp) {
                    camp.name = 'rows[' + i + '][' + camp.getAttribute('data-name') + ']';
                });
                Array.prototype.forEach.call(fila.querySelectorAll('[data-extra]'), function (camp) {
                    camp.name = 'rows[' + i + '][extra][' + camp.getAttribute('data-extra') + ']';
                });

                var tria = fila.querySelector('[data-name="type_id"]');
                var opcio = tria.options[tria.selectedIndex];
                suma += parseInt(opcio.getAttribute('data-price'), 10) || 0;

                // Només es veuen (i s'envien) els camps del tipus triat.
                Array.prototype.forEach.call(fila.querySelectorAll('.manual-row__extra'), function (bloc) {
                    var seu = bloc.getAttribute('data-for-type') === tria.value;
                    bloc.hidden = !seu;
                    Array.prototype.forEach.call(bloc.querySelectorAll('[data-extra]'), function (camp) {
                        camp.disabled = !seu;
                    });
                });
            });

            // Amb una sola fila no té sentit poder treure-la.
            Array.prototype.forEach.call(contenidor.querySelectorAll('.manual-row__del'), function (boto) {
                boto.hidden = files.length < 2;
            });

            if (total) { total.textContent = files.length ? formata(suma) : '—'; }
        }

        function afegeix(valors) {
            var fila = model.content.firstElementChild.cloneNode(true);
            contenidor.appendChild(fila);

            if (valors) {
                var tria = fila.querySelector('[data-name="type_id"]');
                if (valors.type_id) { tria.value = valors.type_id; }
                if (valors.name) { fila.querySelector('[data-name="name"]').value = valors.name; }
                Object.keys(valors.extra || {}).forEach(function (slug) {
                    var camp = fila.querySelector('[data-extra="' + slug + '"]');
                    if (camp) { camp.value = valors.extra[slug]; }
                });
            }

            fila.querySelector('.manual-row__del').addEventListener('click', function () {
                fila.remove();
                renumera();
            });
            fila.querySelector('[data-name="type_id"]').addEventListener('change', renumera);

            renumera();
        }

        document.getElementById('manual-add').addEventListener('click', function () { afegeix(null); });

        // Si el formulari torna amb un error, recuperem les files que hi havia.
        var previes = [];
        try { previes = JSON.parse(contenidor.getAttribute('data-rows') || '[]'); } catch (e) { previes = []; }

        if (previes.length) {
            previes.forEach(afegeix);
        } else {
            afegeix(null);
        }
    });
})();
