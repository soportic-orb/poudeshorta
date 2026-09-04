<?php
use App\Core\Csrf;
use App\Services\Updater;

$s = $settings;
$latest = trim((string) $s['ota_latest_version']);
$available = $latest !== '' && $latest !== $current && $latest !== ($commit ?? '');
?>

<div class="panel">
    <div class="panel__head">
        <div>
            <h2>Versió instal·lada</h2>
            <p>Estratègia d'actualització detectada: <strong><?= $strategy === 'git' ? 'git (clon del repositori)' : 'paquet ZIP de GitHub' ?></strong>.</p>
        </div>
        <span class="badge <?= $available ? 'badge--warn' : 'badge--ok' ?>">
            <?= $available ? 'Actualització disponible' : 'Al dia' ?>
        </span>
    </div>
    <div class="panel__body">
        <dl class="kv">
            <dt>Versió</dt><dd><?= e($current) ?></dd>
            <?php if ($commit): ?><dt>Revisió</dt><dd class="mono"><?= e($commit) ?></dd><?php endif; ?>
            <dt>Última comprovació</dt><dd><?= dt($s['ota_last_check']) ?></dd>
            <dt>Versió publicada</dt><dd><?= $latest !== '' ? e($latest) : '—' ?></dd>
            <?php if ($pendingMigrations !== []): ?>
                <dt>Migracions pendents</dt><dd style="color:var(--pdsh-danger);"><?= e(implode(', ', $pendingMigrations)) ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    <div class="panel__foot">
        <form method="post" action="<?= e(url('/admin/actualitzacions/comprovar')) ?>">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn--light" data-loading="Consultant GitHub…">Comprovar si hi ha novetats</button>
        </form>
    </div>
</div>

<?php if (!empty($check) && !empty($check['notes'])): ?>
    <div class="panel">
        <div class="panel__head"><div><h2>Novetats de la versió <?= e($check['version']) ?></h2></div></div>
        <div class="panel__body">
            <pre class="code-block"><?= e($check['notes']) ?></pre>
        </div>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel__head">
        <div>
            <h2>Aplicar l'actualització</h2>
            <p>Es fa una còpia de seguretat completa abans de tocar res i, si alguna cosa falla, es restaura sola.</p>
        </div>
    </div>
    <div class="panel__body">
        <div class="alert alert--warning">
            <span aria-hidden="true">⚠️</span>
            <span>
                Durant l'actualització el web públic mostra la pàgina de manteniment.
                <strong>No es toquen mai</strong> la configuració (<code>config/config.php</code>),
                les imatges pujades ni el directori <code>storage/</code>.
                Feu-ho preferentment fora de les hores de més venda.
            </span>
        </div>

        <form method="post" action="<?= e(url('/admin/actualitzacions/aplicar')) ?>"
              data-confirm="Actualitzar la plataforma ara?">
            <?= Csrf::field() ?>
            <div class="field" style="max-width:320px;">
                <label for="confirm">Escriviu <code>ACTUALITZA</code> per confirmar</label>
                <input class="input" type="text" id="confirm" name="confirm" autocomplete="off" placeholder="ACTUALITZA">
            </div>
            <button type="submit" class="btn btn--primary" data-loading="Actualitzant, no tanqueu la pàgina…">
                Actualitzar ara
            </button>
        </form>
    </div>
</div>

<?php if (!empty($result)): ?>
    <div class="panel">
        <div class="panel__head">
            <div><h2>Registre de la darrera actualització</h2></div>
            <span class="badge <?= $result['success'] ? 'badge--ok' : 'badge--danger' ?>">
                <?= $result['success'] ? 'Correcta' : 'Ha fallat' ?>
            </span>
        </div>
        <div class="panel__body">
            <pre class="code-block code-block--log"><?= e(implode("\n", $result['log'])) ?></pre>
        </div>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel__head"><div><h2>Origen de les actualitzacions</h2><p>Repositori de GitHub des d'on es baixa el codi.</p></div></div>
    <form method="post" action="<?= e(url('/admin/actualitzacions/configuracio')) ?>">
        <?= Csrf::field() ?>
        <div class="panel__body">
            <div class="form-grid">
                <div class="field">
                    <label for="ota_repo">Repositori</label>
                    <input class="input" type="text" id="ota_repo" name="ota_repo" value="<?= e($s['ota_repo']) ?>" placeholder="usuari/repositori">
                </div>
                <div class="field">
                    <label for="ota_branch">Branca</label>
                    <input class="input" type="text" id="ota_branch" name="ota_branch" value="<?= e($s['ota_branch']) ?>">
                </div>
                <div class="field">
                    <label for="ota_channel">Canal</label>
                    <select class="select" id="ota_channel" name="ota_channel">
                        <option value="branch"<?= selectedIf($s['ota_channel'] === 'branch') ?>>Últim canvi de la branca</option>
                        <option value="release"<?= selectedIf($s['ota_channel'] === 'release') ?>>Només versions publicades (releases)</option>
                    </select>
                </div>
                <div class="field">
                    <label for="ota_token">Token d'accés (repositoris privats)</label>
                    <input class="input" type="password" id="ota_token" name="ota_token"
                           placeholder="<?= $s['ota_token'] !== '' ? '••••••••••••' : 'ghp_…' ?>" autocomplete="off">
                    <span class="field__hint">Cal un token amb permís de lectura del repositori.</span>
                </div>
            </div>
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="ota_auto_check" value="1"<?= checkedIf($s['ota_auto_check'] === '1') ?>>
                    <span>Comprovar automàticament si hi ha versions noves (amb la tasca cron)</span>
                </label>
            </div>
        </div>
        <div class="panel__foot">
            <button type="submit" class="btn btn--primary">Desar</button>
        </div>
    </form>
</div>

<div style="display:grid;gap:22px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));align-items:start;">
    <div class="panel">
        <div class="panel__head"><div><h2>Còpies de seguretat</h2><p>Es conserven les 5 més recents.</p></div></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Fitxer</th><th>Data</th><th class="num">Mida</th></tr></thead>
                <tbody>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td class="mono" style="font-size:.8rem;"><?= e($backup['name']) ?></td>
                            <td><?= e($backup['date']) ?></td>
                            <td class="num"><?= e(Updater::humanBytes($backup['size'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($backups === []): ?>
                        <tr><td colspan="3" class="empty">Encara no s'ha fet cap còpia.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head"><div><h2>Historial d'actualitzacions</h2></div></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Data</th><th>De → a</th><th>Estat</th><th>Usuari</th></tr></thead>
                <tbody>
                    <?php foreach ($history as $entry): ?>
                        <tr>
                            <td style="white-space:nowrap;"><?= dt((string) $entry['created_at']) ?></td>
                            <td class="mono" style="font-size:.82rem;"><?= e($entry['from_version']) ?> → <?= e($entry['to_version'] ?: '—') ?></td>
                            <td>
                                <span class="badge <?= $entry['status'] === 'success' ? 'badge--ok' : 'badge--danger' ?>">
                                    <?= e($entry['status']) ?>
                                </span>
                            </td>
                            <td style="font-size:.83rem;"><?= e($entry['actor'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($history === []): ?>
                        <tr><td colspan="4" class="empty">Encara no s'ha aplicat cap actualització.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
