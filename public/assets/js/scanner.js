/* Escàner de codis QR per al control d'accés.
 *
 * Fa servir l'API BarcodeDetector del navegador quan hi és (Chrome a Android),
 * que és la més ràpida i no descarrega res. Si no hi és (Safari a l'iPhone),
 * carrega jsQR només en aquell moment.
 *
 * Serveix igual per al PDF de l'entrada i per als passis de wallet: tots
 * porten el mateix codi QR. */
(function () {
    'use strict';

    var video, canvas, context, stream = null, detector = null, jsqrCarregat = false;
    var escanejant = false, ultimCodi = '', ultimMoment = 0;

    /* Del contingut del QR n'extraiem el codi de l'entrada. El QR porta
       l'adreça sencera, però acceptem que algú escrigui només el codi. */
    function extreuCodi(text) {
        var net = String(text || '').trim();
        var coincidencia = net.match(/\/e\/([A-Za-z0-9]+)/);
        if (coincidencia) { return coincidencia[1].toUpperCase(); }
        return net.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    }

    function senyal(correcte) {
        if (navigator.vibrate) {
            navigator.vibrate(correcte ? 60 : [70, 60, 70]);
        }
        try {
            var Audio = window.AudioContext || window.webkitAudioContext;
            if (!Audio) { return; }
            var ctx = new Audio();
            var osc = ctx.createOscillator();
            var vol = ctx.createGain();
            osc.frequency.value = correcte ? 880 : 220;
            vol.gain.value = 0.08;
            osc.connect(vol); vol.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.12);
            setTimeout(function () { ctx.close(); }, 400);
        } catch (e) { /* sense so, no passa res */ }
    }

    function carregaJsQR() {
        if (jsqrCarregat) { return Promise.resolve(); }
        return new Promise(function (resol, rebutja) {
            var s = document.createElement('script');
            s.src = document.getElementById('scanner').getAttribute('data-jsqr');
            s.onload = function () { jsqrCarregat = true; resol(); };
            s.onerror = function () { rebutja(new Error('No s\'ha pogut carregar el lector de codis.')); };
            document.head.appendChild(s);
        });
    }

    function mostraEstat(text, to) {
        var estat = document.getElementById('scanner-estat');
        estat.textContent = text;
        estat.className = 'scanner__estat' + (to ? ' is-' + to : '');
    }

    function valida(codi) {
        var ara = Date.now();
        // Evitem repetir la mateixa entrada mentre encara s'enfoca.
        if (codi === ultimCodi && ara - ultimMoment < 3000) { return; }
        ultimCodi = codi;
        ultimMoment = ara;

        var form = document.getElementById('scan-form');
        var body = new FormData();
        body.append('code', codi);
        body.append('_token', form.querySelector('[name="_token"]').value);

        mostraEstat('Comprovant ' + codi + '…', null);

        fetch(form.action, {
            method: 'POST',
            body: body,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json().then(function (d) { return { d: d, ok: r.ok && d.ok }; }); })
            .then(function (p) {
                window.pdshMostraResultat(p.d, p.ok);
                senyal(p.ok);
                mostraEstat(p.ok ? 'Validada. Apunteu la següent.' : 'Apunteu la següent entrada.', p.ok ? 'ok' : 'error');
                aVista();
            })
            .catch(function () {
                mostraEstat('No s\'ha pogut connectar amb el servidor.', 'error');
            });
    }

    /* El resultat surt just damunt de la càmera: al mòbil ens assegurem que
       es vegin totes dues coses alhora, sense haver de tocar res. */
    function aVista() {
        var resultat = document.getElementById('scan-result');
        if (!resultat || resultat.hidden || !escanejant) { return; }
        var dalt = resultat.getBoundingClientRect().top;
        var baix = document.getElementById('scanner').getBoundingClientRect().bottom;
        if (dalt >= 0 && baix <= window.innerHeight) { return; }
        // Si no hi caben tots dos, prioritzem el resultat.
        var objectiu = (baix - dalt <= window.innerHeight) ? baix - window.innerHeight : dalt;
        window.scrollBy({ top: objectiu, behavior: 'smooth' });
    }

    function cicle() {
        if (!escanejant) { return; }

        if (video.readyState !== video.HAVE_ENOUGH_DATA) {
            requestAnimationFrame(cicle);
            return;
        }

        if (detector) {
            detector.detect(video)
                .then(function (codis) {
                    if (codis.length) { valida(extreuCodi(codis[0].rawValue)); }
                })
                .catch(function () { /* fotograma perdut */ })
                .finally(function () { setTimeout(cicle, 200); });
            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        var imatge = context.getImageData(0, 0, canvas.width, canvas.height);
        var resultat = window.jsQR ? window.jsQR(imatge.data, imatge.width, imatge.height, { inversionAttempts: 'dontInvert' }) : null;
        if (resultat && resultat.data) { valida(extreuCodi(resultat.data)); }
        setTimeout(cicle, 200);
    }

    function atura() {
        escanejant = false;
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        document.getElementById('scanner').hidden = true;
        document.getElementById('scanner-obre').hidden = false;
    }

    function obre() {
        var caixa = document.getElementById('scanner');
        caixa.hidden = false;
        document.getElementById('scanner-obre').hidden = true;
        mostraEstat('Demanant permís per fer servir la càmera…', null);

        var preparaLector = ('BarcodeDetector' in window)
            ? window.BarcodeDetector.getSupportedFormats()
                .then(function (formats) {
                    if (formats.indexOf('qr_code') !== -1) {
                        detector = new window.BarcodeDetector({ formats: ['qr_code'] });
                    }
                    return detector ? null : carregaJsQR();
                })
                .catch(function () { return carregaJsQR(); })
            : carregaJsQR();

        preparaLector
            .then(function () {
                return navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } },
                    audio: false
                });
            })
            .then(function (s) {
                stream = s;
                video.srcObject = s;
                video.setAttribute('playsinline', 'true');
                return video.play();
            })
            .then(function () {
                escanejant = true;
                mostraEstat('Apunteu al codi QR de l\'entrada.', null);
                cicle();
            })
            .catch(function (e) {
                var missatge = 'No s\'ha pogut obrir la càmera.';
                if (e && (e.name === 'NotAllowedError' || e.name === 'SecurityError')) {
                    missatge = 'Heu denegat el permís de càmera. Autoritzeu-lo a la barra d\'adreces del navegador i torneu-ho a provar.';
                } else if (e && e.name === 'NotFoundError') {
                    missatge = 'Aquest dispositiu no té cap càmera disponible.';
                } else if (e && e.message) {
                    missatge = e.message;
                }
                mostraEstat(missatge, 'error');
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var boto = document.getElementById('scanner-obre');
        if (!boto) { return; }

        // Sense càmera o sense HTTPS no té sentit oferir-ho.
        var potEscanejar = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)
            && (window.isSecureContext || location.hostname === 'localhost');

        if (!potEscanejar) {
            boto.hidden = true;
            var avis = document.getElementById('scanner-no-disponible');
            if (avis) { avis.hidden = false; }
            return;
        }

        boto.hidden = false;
        video = document.getElementById('scanner-video');
        canvas = document.createElement('canvas');
        context = canvas.getContext('2d', { willReadFrequently: true });

        boto.addEventListener('click', obre);
        document.getElementById('scanner-tanca').addEventListener('click', atura);

        // No deixem la càmera oberta si es canvia de pestanya.
        document.addEventListener('visibilitychange', function () {
            if (document.hidden && escanejant) { atura(); }
        });
    });
})();
