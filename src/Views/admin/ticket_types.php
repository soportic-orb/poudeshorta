<?php use App\Core\Csrf; ?>

<div class="panel">
    <div class="panel__head">
        <div>
            <h2>Tipus d'inscripció</h2>
            <p>Cada tipus és una opció de compra al web públic, amb el seu preu i el que inclou.</p>
        </div>
        <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/tipus-inscripcio/nou')) ?>">+ Nou tipus</a>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Nom</th><th class="num">Preu</th><th class="num">Venudes</th><th class="num">Queden</th><th>Venda</th><th>Estat</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($types as $type): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/admin/tipus-inscripcio/' . $type['id'])) ?>"><?= e($type['name']) ?></a>
                            <?php if (trim((string) $type['description']) !== ''): ?>
                                <br><span style="color:var(--pdsh-muted);font-size:.83rem;">
                                    <?= e(\App\Core\Str::limit((string) $type['description'], 70)) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= (int) $type['price_cents'] === 0 ? 'Gratuïta' : money((int) $type['price_cents']) ?></td>
                        <td class="num"><strong><?= (int) $type['sold'] ?></strong></td>
                        <td class="num"><?= $type['remaining'] === null ? '∞' : (int) $type['remaining'] ?></td>
                        <td style="font-size:.83rem;color:var(--pdsh-muted);">
                            <?= $type['sales_start'] ? 'Des del ' . dt((string) $type['sales_start'], 'd/m/y') : 'Sense data d\'inici' ?><br>
                            <?= $type['sales_end'] ? 'Fins al ' . dt((string) $type['sales_end'], 'd/m/y') : 'Sense data de fi' ?>
                        </td>
                        <td>
                            <span class="badge <?= (int) $type['active'] === 1 ? 'badge--ok' : 'badge--muted' ?>">
                                <?= (int) $type['active'] === 1 ? 'Actiu' : 'Ocult' ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;">
                                <form method="post" action="<?= e(url('/admin/tipus-inscripcio/' . $type['id'] . '/estat')) ?>">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="btn btn--light btn--sm">
                                        <?= (int) $type['active'] === 1 ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                                <?php if ((int) $type['sold'] === 0): ?>
                                    <form method="post" action="<?= e(url('/admin/tipus-inscripcio/' . $type['id'] . '/eliminar')) ?>"
                                          data-confirm="Eliminar «<?= e($type['name']) ?>»?">
                                        <?= Csrf::field() ?>
                                        <button type="submit" class="btn btn--danger btn--sm">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($types === []): ?>
                    <tr><td colspan="7" class="empty">
                        <span class="empty__icon" aria-hidden="true">🎟</span>
                        Encara no has creat cap tipus d'inscripció.<br>
                        <a class="btn btn--primary btn--sm" style="margin-top:14px;" href="<?= e(url('/admin/tipus-inscripcio/nou')) ?>">Crear-ne un</a>
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
