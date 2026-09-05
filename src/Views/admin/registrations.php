<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Services\TicketService;

$query = array_filter($filters, static fn ($v) => $v !== '');
$exportQuery = $query === [] ? '' : '?' . http_build_query($query);
?>

<div class="panel">
    <div class="panel__head">
        <div>
            <h2>Filtres</h2>
            <p>Combina'ls per acotar el llistat; les exportacions respecten el filtre actiu.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn btn--dark btn--sm" href="<?= e(url('/admin/inscripcions/pdf') . $exportQuery) ?>">Imprimir en PDF</a>
            <a class="btn btn--light btn--sm" href="<?= e(url('/admin/inscripcions/csv') . $exportQuery) ?>">Exportar CSV</a>
        </div>
    </div>
    <div class="panel__body">
        <form method="get" action="<?= e(url('/admin/inscripcions')) ?>">
            <div class="filters">
                <div class="field">
                    <label for="cerca">Cerca</label>
                    <input class="input" type="search" id="cerca" name="cerca" value="<?= e($filters['cerca']) ?>"
                           placeholder="Correu, nom, referència o codi">
                </div>
                <div class="field">
                    <label for="tipus">Tipus d'entrada</label>
                    <select class="select" id="tipus" name="tipus">
                        <option value="">Tots</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?= (int) $type['id'] ?>"<?= selectedIf($filters['tipus'] === (string) $type['id']) ?>>
                                <?= e($type['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="estat">Estat del pagament</label>
                    <select class="select" id="estat" name="estat">
                        <option value="">Tots</option>
                        <?php foreach (['paid', 'pending', 'partially_refunded', 'refunded', 'cancelled', 'failed'] as $status): ?>
                            <option value="<?= e($status) ?>"<?= selectedIf($filters['estat'] === $status) ?>>
                                <?= e(TicketService::statusLabel($status)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="entrada">Estat de l'entrada</label>
                    <select class="select" id="entrada" name="entrada">
                        <option value="">Totes</option>
                        <?php foreach (['valid', 'used', 'cancelled', 'refunded'] as $status): ?>
                            <option value="<?= e($status) ?>"<?= selectedIf($filters['entrada'] === $status) ?>>
                                <?= e(TicketService::statusLabel($status)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="des_de">Des del</label>
                    <input class="input" type="date" id="des_de" name="des_de" value="<?= e($filters['des_de']) ?>">
                </div>
                <div class="field">
                    <label for="fins_a">Fins al</label>
                    <input class="input" type="date" id="fins_a" name="fins_a" value="<?= e($filters['fins_a']) ?>">
                </div>
                <div class="field">
                    <label for="validada">Control d'accés</label>
                    <select class="select" id="validada" name="validada">
                        <option value="">Totes</option>
                        <option value="si"<?= selectedIf($filters['validada'] === 'si') ?>>Ja validades</option>
                        <option value="no"<?= selectedIf($filters['validada'] === 'no') ?>>Pendents de validar</option>
                    </select>
                </div>
                <div class="filters__actions">
                    <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                    <a class="btn btn--light btn--sm" href="<?= e(url('/admin/inscripcions')) ?>">Netejar</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <div>
            <h2><?= (int) $total ?> entrades</h2>
            <p>Import filtrat: <strong><?= money((int) $totals['import']) ?></strong></p>
        </div>
    </div>

    <?php if ($potEsborrar): ?>
        <div class="bulk" id="bulk" hidden>
            <span class="bulk__count" id="bulk-compte"></span>
            <button type="submit" form="bulk-form" class="btn btn--danger btn--sm">Esborrar les seleccionades</button>
            <button type="button" class="btn btn--light btn--sm" id="bulk-neteja">Desmarcar</button>
        </div>
    <?php endif; ?>

    <form id="bulk-form" method="post" action="<?= e(url('/admin/inscripcions/esborrar')) ?>">
        <?= Csrf::field() ?>
        <?php foreach ($filters as $nom => $valor): ?>
            <?php if ($valor !== ''): ?>
                <input type="hidden" name="<?= e($nom) ?>" value="<?= e($valor) ?>">
            <?php endif; ?>
        <?php endforeach; ?>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <?php if ($potEsborrar): ?>
                        <th class="tria">
                            <input type="checkbox" id="tria-tot" aria-label="Seleccionar totes les entrades d'aquesta pàgina">
                        </th>
                    <?php endif; ?>
                    <th>Data</th><th>Referència</th><th>Codi</th><th>Assistent</th>
                    <th>Contacte</th><th>Tipus</th><th>Estat</th><th class="num">Import</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row):
                    $ticketBadge = match ((string) $row['ticket_status']) {
                        'valid' => 'badge--ok',
                        'used' => 'badge--muted',
                        default => 'badge--danger',
                    };
                ?>
                    <tr>
                        <?php if ($potEsborrar): ?>
                            <td class="tria">
                                <input type="checkbox" name="tickets[]" value="<?= (int) $row['ticket_id'] ?>"
                                       aria-label="Seleccionar l'entrada <?= e($row['code']) ?>">
                            </td>
                        <?php endif; ?>
                        <td style="white-space:nowrap;"><?= dt((string) $row['created_at'], 'd/m/y H:i') ?></td>
                        <td class="mono">
                            <a href="<?= e(url('/admin/inscripcions/' . $row['order_id'])) ?>"><?= e($row['reference']) ?></a>
                        </td>
                        <td class="mono"><?= e($row['code']) ?></td>
                        <td><?= e($row['attendee_name'] ?: trim((string) $row['buyer_name'] . ' ' . (string) $row['buyer_surname'])) ?></td>
                        <td>
                            <?= e($row['email']) ?>
                            <?php if (!empty($row['phone'])): ?>
                                <br><span style="color:var(--pdsh-muted);font-size:.83rem;"><?= e($row['phone']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($row['type_name']) ?></td>
                        <td>
                            <span class="badge <?= $ticketBadge ?>"><?= e(TicketService::statusLabel((string) $row['ticket_status'])) ?></span>
                            <?php if ($row['order_status'] !== 'paid'): ?>
                                <br><span class="badge badge--warn" style="margin-top:4px;"><?= e(TicketService::statusLabel((string) $row['order_status'])) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($row['checked_in_at'])): ?>
                                <br><span style="font-size:.78rem;color:var(--pdsh-olive);">✓ <?= dt((string) $row['checked_in_at'], 'd/m H:i') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= money((int) $row['price_cents']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($rows === []): ?>
                    <tr><td colspan="<?= $potEsborrar ? 9 : 8 ?>" class="empty">
                        <span class="empty__icon" aria-hidden="true">🔍</span>
                        Cap inscripció coincideix amb aquests filtres.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </form>

    <?php if ($pages > 1): ?>
        <div class="pagination">
            <a class="<?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= e(Url::withQuery(['pagina' => max(1, $page - 1)])) ?>">←</a>
            <?php
            $from = max(1, $page - 2);
            $to = min($pages, $from + 4);
            for ($p = $from; $p <= $to; $p++):
            ?>
                <?php if ($p === $page): ?>
                    <span class="is-current"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= e(Url::withQuery(['pagina' => $p])) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <a class="<?= $page >= $pages ? 'is-disabled' : '' ?>" href="<?= e(Url::withQuery(['pagina' => min($pages, $page + 1)])) ?>">→</a>
        </div>
    <?php endif; ?>
</div>
