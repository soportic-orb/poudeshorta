<?php use App\Core\Csrf; ?>

<?php if (!$enabled): ?>
    <div class="alert alert--warning">
        <span aria-hidden="true">⚠️</span>
        <span>El control d'accés està desactivat a
            <a href="<?= e(url('/admin/configuracio')) ?>">Configuració → Esdeveniment</a>.
            Pots validar entrades igualment des d'aquí.</span>
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat stat--olive">
        <div class="stat__label">Validades</div>
        <div class="stat__value" id="scan-counter"><?= (int) $stats['validades'] ?></div>
        <div class="stat__hint">de <?= (int) $stats['total'] ?> entrades vàlides</div>
    </div>
    <div class="stat">
        <div class="stat__label">Pendents d'arribar</div>
        <div class="stat__value"><?= max(0, (int) $stats['total'] - (int) $stats['validades']) ?></div>
        <div class="stat__hint">segons les entrades venudes</div>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <div>
            <h2>Validar una entrada</h2>
            <p>Obre la càmera i apunta al codi QR de l'entrada: serveix igual el PDF imprès,
               la pantalla del mòbil o el passi del wallet. També pots escriure'n el codi a mà.</p>
        </div>
    </div>
    <div class="panel__body">
        <div class="scan-result" id="scan-result" hidden></div>

        <button type="button" class="btn btn--primary btn--block btn--lg" id="scanner-obre" hidden>
            📷 Escanejar amb la càmera
        </button>

        <p class="alert alert--info" id="scanner-no-disponible" hidden>
            <span aria-hidden="true">ℹ️</span>
            <span>Aquest navegador no pot obrir la càmera (cal una connexió segura amb HTTPS).
                  Pots validar les entrades escrivint-ne el codi aquí sota.</span>
        </p>

        <div class="scanner" id="scanner" hidden
             data-jsqr="<?= e(url('/assets/vendor/jsqr.min.js')) ?>">
            <div class="scanner__marc">
                <video id="scanner-video" playsinline muted></video>
                <div class="scanner__mira" aria-hidden="true"></div>
            </div>
            <p class="scanner__estat" id="scanner-estat"></p>
            <button type="button" class="btn btn--light btn--block" id="scanner-tanca">Tancar la càmera</button>
        </div>

        <form id="scan-form" method="post" action="<?= e(url('/admin/control-acces/validar')) ?>">
            <?= Csrf::field() ?>
            <div class="field">
                <label for="scan-code" style="font-size:.9rem;color:var(--pdsh-muted);font-weight:600;">
                    O escriu el codi de l'entrada
                </label>
                <input class="input" type="text" id="scan-code" name="code"
                       placeholder="ABCD1234" autocomplete="off" autocapitalize="characters"
                       spellcheck="false" maxlength="80">
            </div>
            <button type="submit" class="btn btn--primary btn--block btn--lg">Validar</button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel__head"><div><h2>Últimes validacions</h2></div></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Hora</th><th>Codi</th><th>Assistent</th><th>Tipus</th><th>Validada per</th></tr></thead>
            <tbody>
                <?php foreach ($recents as $entry): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= dt((string) $entry['checked_in_at'], 'd/m H:i') ?></td>
                        <td class="mono"><?= e($entry['code']) ?></td>
                        <td><?= e($entry['attendee_name'] ?: '—') ?></td>
                        <td><?= e($entry['type_name']) ?></td>
                        <td style="font-size:.85rem;color:var(--pdsh-muted);"><?= e($entry['checked_in_by'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recents === []): ?>
                    <tr><td colspan="5" class="empty">Encara no s'ha validat cap entrada.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
