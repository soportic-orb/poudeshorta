/* Confeti de celebració per a la pantalla de compra realitzada.
   Sense dependències i respectant la preferència de moviment reduït. */
(function () {
    'use strict';

    function launch(options) {
        var settings = options || {};
        var duration = settings.duration || 4200;
        var colors = settings.colors || ['#8C1027', '#E8621E', '#F2A81D', '#6E8A4E', '#FBF4E6'];

        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        var canvas = document.getElementById('confetti-canvas');
        if (!canvas) {
            canvas = document.createElement('canvas');
            canvas.id = 'confetti-canvas';
            document.body.appendChild(canvas);
        }

        var context = canvas.getContext('2d');
        var ratio = window.devicePixelRatio || 1;
        var pieces = [];
        var running = true;
        var started = Date.now();

        function resize() {
            canvas.width = window.innerWidth * ratio;
            canvas.height = window.innerHeight * ratio;
            canvas.style.width = window.innerWidth + 'px';
            canvas.style.height = window.innerHeight + 'px';
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
        }

        function createPiece(fromTop) {
            return {
                x: Math.random() * window.innerWidth,
                y: fromTop ? -20 - Math.random() * window.innerHeight * 0.4 : Math.random() * window.innerHeight,
                w: 6 + Math.random() * 7,
                h: 9 + Math.random() * 9,
                color: colors[Math.floor(Math.random() * colors.length)],
                rotation: Math.random() * Math.PI * 2,
                spin: (Math.random() - 0.5) * 0.24,
                speed: 1.9 + Math.random() * 2.6,
                drift: (Math.random() - 0.5) * 1.5,
                wobble: Math.random() * Math.PI * 2
            };
        }

        resize();
        window.addEventListener('resize', resize);

        var count = Math.min(190, Math.round(window.innerWidth / 6));
        for (var i = 0; i < count; i++) {
            pieces.push(createPiece(true));
        }

        function frame() {
            if (!running) { return; }
            var elapsed = Date.now() - started;
            var fading = elapsed > duration;

            context.clearRect(0, 0, window.innerWidth, window.innerHeight);

            for (var i = pieces.length - 1; i >= 0; i--) {
                var p = pieces[i];
                p.y += p.speed;
                p.wobble += 0.05;
                p.x += p.drift + Math.sin(p.wobble) * 0.9;
                p.rotation += p.spin;

                if (p.y > window.innerHeight + 30) {
                    if (fading) {
                        pieces.splice(i, 1);
                        continue;
                    }
                    pieces[i] = createPiece(true);
                    continue;
                }

                context.save();
                context.translate(p.x, p.y);
                context.rotate(p.rotation);
                context.fillStyle = p.color;
                context.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                context.restore();
            }

            if (pieces.length === 0) {
                running = false;
                context.clearRect(0, 0, window.innerWidth, window.innerHeight);
                window.removeEventListener('resize', resize);
                if (canvas.parentNode) { canvas.parentNode.removeChild(canvas); }
                return;
            }

            window.requestAnimationFrame(frame);
        }

        window.requestAnimationFrame(frame);
    }

    window.pdshConfetti = launch;

    document.addEventListener('DOMContentLoaded', function () {
        if (document.querySelector('[data-confetti]')) {
            launch();
        }
    });
})();
