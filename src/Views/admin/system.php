<div style="display:grid;gap:22px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));align-items:start;">

    <div class="panel">
        <div class="panel__head"><div><h2>Servidor</h2></div></div>
        <div class="panel__body">
            <dl class="kv">
                <dt>PHP</dt><dd><?= e($php) ?></dd>
                <dt>Servidor web</dt><dd><?= e($server) ?></dd>
                <dt>MySQL</dt><dd><?= e($db) ?></dd>
                <dt>Espai lliure</dt><dd><?= e($diskFree) ?></dd>
                <?php foreach ($limits as $key => $value): ?>
                    <dt><?= e($key) ?></dt><dd><?= e((string) $value) ?></dd>
                <?php endforeach; ?>
            </dl>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head"><div><h2>Extensions de PHP</h2></div></div>
        <div class="panel__body">
            <dl class="kv">
                <?php foreach ($extensions as $extension => $loaded): ?>
                    <dt><?= e($extension) ?></dt>
                    <dd style="color:<?= $loaded ? 'var(--pdsh-success)' : 'var(--pdsh-danger)' ?>;">
                        <?= $loaded ? '✓ disponible' : '✗ no instal·lada' ?>
                    </dd>
                <?php endforeach; ?>
            </dl>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head"><div><h2>Permisos dels directoris</h2></div></div>
        <div class="panel__body">
            <dl class="kv">
                <?php foreach ($paths as $path => $state): ?>
                    <dt class="mono" style="font-size:.83rem;"><?= e($path) ?></dt>
                    <dd style="color:<?= $state['writable'] ? 'var(--pdsh-success)' : 'var(--pdsh-danger)' ?>;">
                        <?= !$state['exists'] ? 'no existeix' : ($state['writable'] ? '✓ escriptura' : '✗ sense escriptura') ?>
                    </dd>
                <?php endforeach; ?>
            </dl>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head"><div><h2>Base de dades</h2></div></div>
        <div class="panel__body">
            <dl class="kv">
                <dt>Migracions aplicades</dt><dd><?= count($migrations['aplicades']) ?></dd>
                <dt>Pendents</dt>
                <dd style="color:<?= $migrations['pendents'] === [] ? 'var(--pdsh-success)' : 'var(--pdsh-danger)' ?>;">
                    <?= $migrations['pendents'] === [] ? 'cap' : e(implode(', ', $migrations['pendents'])) ?>
                </dd>
            </dl>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <div>
            <h2>Tasques programades</h2>
            <p>Envien la cua de correu, alliberen reserves caducades i comproven si hi ha actualitzacions.</p>
        </div>
    </div>
    <div class="panel__body">
        <p style="font-size:.92rem;">
            <strong>Opció recomanada</strong> — afegiu aquesta ordre al cron de CloudPanel (cada 5 minuts):
        </p>
        <pre class="code-block">*/5 * * * * /usr/bin/php <?= e(APP_ROOT) ?>/bin/cron.php >> <?= e(APP_ROOT) ?>/storage/logs/cron.log 2>&1</pre>

        <p style="font-size:.92rem;margin-top:18px;">
            <strong>Alternativa</strong> — si no teniu accés al cron, crideu aquesta URL des d'un servei extern:
        </p>
        <div class="copy-row">
            <input class="input" type="text" id="cron-url" value="<?= e($cronUrl) ?>" readonly>
            <button type="button" class="btn btn--light btn--sm" data-copy-target="#cron-url">Copiar</button>
        </div>

        <p style="margin:14px 0 0;font-size:.88rem;color:var(--pdsh-muted);">
            Última execució: <strong><?= dt($lastCron) ?></strong>
            <?php if (trim((string) $lastCron) === ''): ?>
                — encara no s'ha executat mai. Si no ho configureu, els comunicats només s'enviaran
                quan premeu «Enviar el lot següent» al panell.
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="panel">
    <div class="panel__head"><div><h2>Registre de l'aplicació</h2><p>Darreres línies de <code>storage/logs/</code>.</p></div></div>
    <div class="panel__body">
        <?php if ($logs === []): ?>
            <p style="margin:0;color:var(--pdsh-muted);">El registre d'aquest mes és buit. Bona senyal.</p>
        <?php else: ?>
            <pre class="code-block code-block--log"><?= e(implode("\n", $logs)) ?></pre>
        <?php endif; ?>
    </div>
</div>
