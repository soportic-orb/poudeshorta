<?php
use App\Core\Csrf;
use App\Services\TicketService;

$import = 0;
$pagades = 0;
$validades = 0;
$comandes = [];

foreach ($rows as $row) {
    $comandes[(int) $row['order_id']] = true;
    if (in_array((string) $row['order_status'], ['paid', 'partially_refunded'], true)) {
        $pagades++;
        $import += (int) $row['price_cents'];
    }
    if (!empty($row['checked_in_at'])) {
        $validades++;
    }
}
?>

<div class="panel">
    <div class="panel__head">
        <div>
            <h2>Esborrar <?= count($rows) ?> <?= count($rows) === 1 ? 'entrada' : 'entrades' ?></h2>
            <p>Reviseu la llista abans de confirmar. Això no es pot desfer.</p>
        </div>
    </div>

    <div class="panel__body">
        <div class="alert alert--error">
            <span aria-hidden="true">⚠️</span>
            <span>
                <strong>L'esborrat és definitiu.</strong> Les entrades desapareixen del sistema i
                deixen de comptar a les estadístiques i a les exportacions. Els codis QR d'aquestes
                entrades deixaran de validar-se al control d'accés.
                <?php if ($import > 0): ?>
                    <br><br>
                    <strong>No es retorna cap diner.</strong> S'han cobrat
                    <strong><?= money($import) ?></strong> per
                    <?= $pagades ?> d'aquestes entrades.
                    Si el que voleu és
                    retornar els diners, sortiu d'aquí i feu servir <strong>Anul·lar</strong> des de
                    la fitxa de la inscripció: així es tramita la devolució a Stripe i en queda
                    constància.
                <?php endif; ?>
                <?php if ($validades > 0): ?>
                    <br><br>
                    <strong>Atenció:</strong> <?= $validades ?>
                    <?= $validades === 1 ? 'd\'aquestes entrades ja es va validar' : 'd\'aquestes entrades ja es van validar' ?>
                    al control d'accés.
                <?php endif; ?>
            </span>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Referència</th><th>Codi</th><th>Assistent</th>
                        <th>Contacte</th><th>Tipus</th><th>Estat</th><th class="num">Import</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="mono"><?= e($row['reference']) ?></td>
                            <td class="mono"><?= e($row['code']) ?></td>
                            <td><?= e($row['attendee_name'] ?: trim((string) $row['buyer_name'] . ' ' . (string) $row['buyer_surname'])) ?></td>
                            <td><?= e($row['email']) ?></td>
                            <td><?= e($row['type_name']) ?></td>
                            <td>
                                <span class="badge"><?= e(TicketService::statusLabel((string) $row['ticket_status'])) ?></span>
                                <?php if (!empty($row['checked_in_at'])): ?>
                                    <br><span style="font-size:.78rem;color:var(--pdsh-olive);">✓ validada</span>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= money((int) $row['price_cents']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p style="color:var(--pdsh-muted);font-size:.9rem;margin-top:16px;">
            Afecta <?= count($comandes) ?>
            <?= count($comandes) === 1 ? 'inscripció' : 'inscripcions' ?>.
            Si alguna es queda sense cap entrada, s'esborrarà també.
        </p>

        <form method="post" action="<?= e(url('/admin/inscripcions/esborrar')) ?>"
              style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">
            <?= Csrf::field() ?>
            <input type="hidden" name="confirmar" value="1">
            <?php foreach ($ids as $id): ?>
                <input type="hidden" name="tickets[]" value="<?= (int) $id ?>">
            <?php endforeach; ?>
            <button type="submit" class="btn btn--danger">
                Sí, esborrar <?= count($rows) ?> <?= count($rows) === 1 ? 'entrada' : 'entrades' ?>
            </button>
            <a class="btn btn--light" href="<?= e($retorn) ?>">Cancel·lar</a>
        </form>
    </div>
</div>
