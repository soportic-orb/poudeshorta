<?php
use App\Core\Csrf;
use App\Core\Money;
use App\Core\Settings;
use App\Services\TicketService;

/* Camps propis de cada tipus d'inscripció, per pintar-los a la fila que toqui. */
$fieldsByType = [];
foreach ($fields as $field) {
    $fieldsByType[(int) $field['ticket_type_id']][] = $field;
}

$actius = array_values(array_filter($types, static fn ($t) => (int) $t['active'] === 1));
$opcions = $actius === [] ? $types : $actius;
?>

<div class="panel">
    <div class="panel__head">
        <div>
            <h2>Inscriure algú a mà</h2>
            <p>Per a qui s'apunta en paper, per telèfon o pagant en efectiu. La inscripció
               queda igual que les fetes pel web: amb el seu codi QR, el seu PDF i els passis de wallet.</p>
        </div>
    </div>

    <?php if ($opcions === []): ?>
        <div class="panel__body">
            <div class="alert alert--warning" style="margin:0;">
                <span aria-hidden="true">⚠️</span>
                <span>Encara no hi ha cap tipus d'inscripció.
                    Crea'n un a <a href="<?= e(url('/admin/tipus-inscripcio')) ?>">Tipus d'inscripció</a>.</span>
            </div>
        </div>
    <?php else: ?>

    <form method="post" action="<?= e(url('/admin/inscripcions/nova')) ?>" id="manual-form">
        <?= Csrf::field() ?>

        <div class="panel__body">
            <h3 style="font-size:1rem;margin:0 0 4px;">Qui fa la inscripció</h3>
            <p style="color:var(--pdsh-muted);font-size:.9rem;margin:0 0 14px;">
                És a qui s'enviaran les entrades i qui les podrà gestionar des del web.
            </p>

            <div class="filters">
                <div class="field">
                    <label for="name">Nom *</label>
                    <input class="input" type="text" id="name" name="name" value="<?= old('name') ?>" required>
                </div>
                <div class="field">
                    <label for="surname">Cognoms</label>
                    <input class="input" type="text" id="surname" name="surname" value="<?= old('surname') ?>">
                </div>
                <div class="field">
                    <label for="email">Correu electrònic *</label>
                    <input class="input" type="email" id="email" name="email" value="<?= old('email') ?>" required>
                </div>
                <div class="field">
                    <label for="phone">Telèfon</label>
                    <input class="input" type="tel" id="phone" name="phone" value="<?= old('phone') ?>">
                </div>
            </div>
        </div>

        <div class="panel__body" style="border-top:1px solid var(--pdsh-line);">
            <h3 style="font-size:1rem;margin:0 0 4px;">Entrades</h3>
            <p style="color:var(--pdsh-muted);font-size:.9rem;margin:0 0 14px;">
                Una fila per assistent. El nom és opcional; si el deixes buit, l'entrada surt a nom de qui fa la inscripció.
            </p>

            <?php
            /* Si el formulari torna amb un error, el JS recupera les files. */
            $previes = [];
            foreach ((array) \App\Core\Flash::old('rows', []) as $row) {
                $previes[] = [
                    'type_id' => (string) ($row['type_id'] ?? ''),
                    'name'    => (string) ($row['name'] ?? ''),
                    'extra'   => array_map('strval', (array) ($row['extra'] ?? [])),
                ];
            }
            ?>
            <?php
            /* El símbol i la seva posició depenen de la divisa: els deduïm del
               mateix formatador que fa servir la resta de la plataforma. */
            $zero = Money::format(0);
            $costats = preg_split('/[\d.,]+/', $zero) ?: ['', ''];
            ?>
            <div id="manual-rows"
                 data-prefix="<?= e($costats[0] ?? '') ?>"
                 data-suffix="<?= e($costats[1] ?? '') ?>"
                 data-rows="<?= e(json_encode($previes, JSON_UNESCAPED_UNICODE)) ?>"></div>

            <button type="button" class="btn btn--light btn--sm" id="manual-add">+ Afegir una entrada</button>

            <p class="field__hint" style="margin-top:14px;">
                Total: <strong id="manual-total">—</strong>
            </p>
        </div>

        <div class="panel__body" style="border-top:1px solid var(--pdsh-line);">
            <h3 style="font-size:1rem;margin:0 0 12px;">Pagament</h3>

            <label class="check" style="margin-bottom:8px;">
                <input type="radio" name="payment" value="paid"<?= old('payment', 'paid') === 'paid' ? ' checked' : '' ?>>
                <span><strong>Ja està pagada</strong> — en efectiu, per transferència o és gratuïta</span>
            </label>
            <label class="check" style="margin-bottom:8px;">
                <input type="radio" name="payment" value="pending"<?= old('payment') === 'pending' ? ' checked' : '' ?>>
                <span><strong>Pendent de pagament</strong> — les entrades no seran vàlides fins que la marquis com a pagada</span>
            </label>

            <?php if ($smtp): ?>
                <label class="check" style="margin-top:14px;">
                    <input type="checkbox" name="send_email" value="1"<?= old('send_email', '1') === '1' ? ' checked' : '' ?>>
                    <span>Enviar-li les entrades per correu (només si la inscripció ja està pagada)</span>
                </label>
            <?php else: ?>
                <p class="field__hint" style="margin-top:14px;">
                    No pots enviar-li les entrades per correu perquè encara no has configurat el
                    <a href="<?= e(url('/admin/configuracio/correu')) ?>">servidor SMTP</a>.
                </p>
            <?php endif; ?>

            <div class="field" style="margin-top:18px;">
                <label for="notes">Notes internes</label>
                <textarea class="input" id="notes" name="notes" rows="2"
                          placeholder="Per exemple: paga en efectiu el dia del sopar."><?= old('notes') ?></textarea>
                <span class="field__hint">Només es veuen des del panell. S'hi afegirà qui ha fet la inscripció i quan.</span>
            </div>

            <label class="check" style="margin-top:14px;">
                <input type="checkbox" name="overbooking" value="1"<?= old('overbooking') === '1' ? ' checked' : '' ?>>
                <span>Permetre passar de les places disponibles</span>
            </label>
            <span class="field__hint">Marca-ho només si saps que hi ha lloc tot i que el comptador digui que no.</span>
        </div>

        <div class="panel__body" style="border-top:1px solid var(--pdsh-line);display:flex;gap:10px;flex-wrap:wrap;">
            <button type="submit" class="btn btn--primary" data-loading="Creant la inscripció…">Crear la inscripció</button>
            <a class="btn btn--light" href="<?= e(url('/admin/inscripcions')) ?>">Cancel·lar</a>
        </div>
    </form>

    <?php endif; ?>
</div>

<?php if ($opcions !== []): ?>
<template id="manual-row-model">
    <div class="manual-row">
        <div class="manual-row__head">
            <span class="manual-row__num"></span>
            <button type="button" class="manual-row__del" title="Treure aquesta entrada">&times;</button>
        </div>
        <div class="filters">
            <div class="field">
                <label>Tipus d'inscripció</label>
                <select class="select" data-name="type_id">
                    <?php foreach ($opcions as $type): ?>
                        <option value="<?= (int) $type['id'] ?>" data-price="<?= (int) $type['price_cents'] ?>">
                            <?= e($type['name']) ?> · <?= money((int) $type['price_cents']) ?>
                            <?php if ((int) $type['active'] !== 1): ?>(inactiu)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Nom de l'assistent</label>
                <input class="input" type="text" data-name="name" placeholder="Opcional">
            </div>
        </div>

        <?php foreach ($fieldsByType as $typeId => $typeFields): ?>
            <div class="filters manual-row__extra" data-for-type="<?= (int) $typeId ?>" hidden>
                <?php foreach ($typeFields as $field): ?>
                    <div class="field">
                        <label><?= e($field['label']) ?></label>
                        <input class="input" type="text" data-extra="<?= e($field['slug']) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</template>
<?php endif; ?>
