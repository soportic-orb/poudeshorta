<?php
use App\Core\Auth;
use App\Core\Csrf;

$isDraft = $campaign['status'] === 'draft';
$queue = $queue ?: ['total' => 0, 'enviats' => 0, 'fallits' => 0, 'pendents' => 0];
?>

<p><a href="<?= e(url('/admin/comunicacions')) ?>">← Tornar als comunicats</a></p>

<div style="display:grid;gap:22px;grid-template-columns:minmax(0,1.3fr) minmax(280px,.7fr);align-items:start;">

    <div class="panel">
        <div class="panel__head">
            <div>
                <h2><?= e($campaign['subject']) ?></h2>
                <p>Creat el <?= dt((string) $campaign['created_at']) ?><?= $campaign['created_by'] ? ' per ' . e($campaign['created_by']) : '' ?></p>
            </div>
        </div>
        <div class="panel__body">
            <p style="font-size:.85rem;color:var(--pdsh-muted);margin-bottom:10px;">Previsualització del correu:</p>
            <iframe title="Previsualització del comunicat"
                    style="width:100%;height:520px;border:1px solid var(--pdsh-line);border-radius:10px;background:#fff;"
                    srcdoc="<?= e($preview) ?>"></iframe>
        </div>
    </div>

    <div style="display:grid;gap:22px;">
        <div class="panel">
            <div class="panel__head"><div><h2>Estat</h2></div></div>
            <div class="panel__body">
                <dl class="kv">
                    <dt>Situació</dt><dd><?= e(ucfirst((string) $campaign['status'])) ?></dd>
                    <dt>Destinataris</dt><dd><?= $isDraft ? count($recipients) : (int) $campaign['total'] ?></dd>
                    <?php if (!$isDraft): ?>
                        <dt>Enviats</dt><dd><?= (int) $queue['enviats'] ?></dd>
                        <dt>Pendents</dt><dd><?= (int) $queue['pendents'] ?></dd>
                        <dt>Errors</dt><dd><?= (int) $queue['fallits'] ?></dd>
                        <dt>Inici</dt><dd><?= dt($campaign['started_at']) ?></dd>
                        <dt>Final</dt><dd><?= dt($campaign['finished_at']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="panel">
            <div class="panel__head"><div><h2>Accions</h2></div></div>
            <div class="panel__body" style="display:grid;gap:14px;">

                <form method="post" action="<?= e(url('/admin/comunicacions/' . $campaign['id'] . '/prova')) ?>">
                    <?= Csrf::field() ?>
                    <div class="field">
                        <label for="test_email">Enviar-me una prova</label>
                        <input class="input" type="email" id="test_email" name="email"
                               value="<?= e(Auth::user()['email'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn--light btn--block btn--sm">Enviar la prova</button>
                </form>

                <?php if ($isDraft): ?>
                    <form method="post" action="<?= e(url('/admin/comunicacions/' . $campaign['id'] . '/enviar')) ?>"
                          data-confirm="Enviar aquest comunicat a <?= count($recipients) ?> destinataris? Aquesta acció no es pot desfer.">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn--primary btn--block"<?= $smtpReady ? '' : ' disabled' ?>>
                            Enviar a <?= count($recipients) ?> destinataris
                        </button>
                    </form>
                    <?php if (!$smtpReady): ?>
                        <p style="font-size:.85rem;color:var(--pdsh-danger);margin:0;">
                            Configureu primer el <a href="<?= e(url('/admin/configuracio/correu')) ?>">servidor SMTP</a>.
                        </p>
                    <?php endif; ?>
                <?php elseif ((int) $queue['pendents'] > 0): ?>
                    <form method="post" action="<?= e(url('/admin/comunicacions/' . $campaign['id'] . '/processar')) ?>">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn--primary btn--block">Enviar el lot següent</button>
                    </form>
                    <p style="font-size:.85rem;color:var(--pdsh-muted);margin:0;">
                        Si teniu la tasca cron activada, la cua s'envia sola cada pocs minuts.
                    </p>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/admin/comunicacions/' . $campaign['id'] . '/eliminar')) ?>"
                      data-confirm="Eliminar aquest comunicat?">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn--danger btn--block btn--sm">Eliminar el comunicat</button>
                </form>
            </div>
        </div>

        <?php if ($isDraft && $recipients !== []): ?>
            <div class="panel">
                <div class="panel__head"><div><h2>Destinataris (<?= count($recipients) ?>)</h2></div></div>
                <div class="panel__body" style="max-height:280px;overflow-y:auto;font-size:.85rem;color:var(--pdsh-muted);">
                    <?php foreach (array_slice($recipients, 0, 200) as $recipient): ?>
                        <?= e($recipient['email']) ?><br>
                    <?php endforeach; ?>
                    <?php if (count($recipients) > 200): ?>
                        <em>… i <?= count($recipients) - 200 ?> més.</em>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
