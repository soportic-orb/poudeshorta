<?php
use App\Controllers\Web\HomeController;
use App\Core\Csrf;
use App\Core\Settings;

$errors = $errors ?? [];
?>

<section class="section">
    <div class="wrap wrap--narrow">

        <div class="steps">
            <span class="is-done" data-step="1">Tria d'inscripcions</span><i>›</i>
            <span class="is-current" data-step="2">Les vostres dades</span><i>›</i>
            <span data-step="3">Pagament</span>
        </div>

        <h1>Dades de la inscripció</h1>
        <p style="color:var(--pdsh-muted);">
            Necessitem el vostre contacte per enviar-vos les entrades i el nom de cada assistent.
        </p>

        <form method="post" action="<?= e(url('/inscripcio/pagament')) ?>" data-guard novalidate>
            <?= Csrf::field() ?>

            <div class="card" style="margin-bottom:22px;">
                <div class="card__body">
                    <fieldset style="margin-bottom:0;">
                        <legend>Qui fa la inscripció</legend>
                        <p style="color:var(--pdsh-muted);font-size:.92rem;">
                            Enviarem les entrades i el comprovant a aquesta adreça electrònica.
                        </p>

                        <div class="field-row">
                            <div class="field">
                                <label for="name">Nom *</label>
                                <input class="input <?= isset($errors['name']) ? 'has-error' : '' ?>" type="text"
                                       id="name" name="name" value="<?= old('name') ?>"
                                       autocomplete="given-name" required data-buyer-name>
                                <?php if (isset($errors['name'])): ?><span class="field__error"><?= e($errors['name']) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="surname">Cognoms *</label>
                                <input class="input <?= isset($errors['surname']) ? 'has-error' : '' ?>" type="text"
                                       id="surname" name="surname" value="<?= old('surname') ?>"
                                       autocomplete="family-name" required data-buyer-surname>
                                <?php if (isset($errors['surname'])): ?><span class="field__error"><?= e($errors['surname']) ?></span><?php endif; ?>
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label for="email">Correu electrònic *</label>
                                <input class="input <?= isset($errors['email']) ? 'has-error' : '' ?>" type="email"
                                       id="email" name="email" value="<?= old('email') ?>"
                                       autocomplete="email" required>
                                <?php if (isset($errors['email'])): ?>
                                    <span class="field__error"><?= e($errors['email']) ?></span>
                                <?php else: ?>
                                    <span class="field__hint">Reviseu-la bé: hi enviarem les entrades.</span>
                                <?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="phone">Telèfon</label>
                                <input class="input" type="tel" id="phone" name="phone"
                                       value="<?= old('phone') ?>" autocomplete="tel">
                                <span class="field__hint">Opcional, per si hem de contactar-vos el mateix dia.</span>
                            </div>
                        </div>
                    </fieldset>
                </div>
            </div>

            <?php
            $globalIndex = 0;
            foreach ($cart['items'] as $item):
                $type = $item['type'];
                $typeId = (int) $type['id'];
                $fields = HomeController::fieldsForType($typeId);
                for ($i = 0; $i < (int) $item['quantity']; $i++):
                    $key = $typeId . '_' . $i;
                    $globalIndex++;
            ?>
                <div class="attendee-block">
                    <div class="attendee-block__head">
                        <strong>Assistent <?= $globalIndex ?> · <?= e($type['name']) ?></strong>
                        <span><?= money((int) $item['unit_cents']) ?></span>
                    </div>

                    <div class="field">
                        <label for="att_<?= e($key) ?>">
                            Nom i cognoms<?= (int) $type['requires_attendee_name'] === 1 ? ' *' : '' ?>
                        </label>
                        <input class="input <?= isset($errors['attendee_name_' . $key]) ? 'has-error' : '' ?>"
                               type="text" id="att_<?= e($key) ?>" name="attendee_name[<?= e($key) ?>]"
                               value="<?= old('attendee_name.' . $key) ?>"
                               <?= $globalIndex === 1 ? 'data-first-attendee' : '' ?>>
                        <?php if (isset($errors['attendee_name_' . $key])): ?>
                            <span class="field__error"><?= e($errors['attendee_name_' . $key]) ?></span>
                        <?php endif; ?>

                        <?php if ($globalIndex === 1): ?>
                            <label class="check" style="margin-top:9px;">
                                <input type="checkbox" data-copy-buyer>
                                <span>Sóc jo mateix/a</span>
                            </label>
                        <?php endif; ?>
                    </div>

                    <?php foreach ($fields as $field):
                        $fieldName = 'attendee_extra[' . $key . '][' . $field['slug'] . ']';
                        $fieldId = 'extra_' . $key . '_' . $field['slug'];
                        $hasError = isset($errors['attendee_extra_' . $key . '_' . $field['slug']]);
                        $options = array_values(array_filter(array_map('trim', preg_split('/\R|,/', (string) $field['options']) ?: [])));
                    ?>
                        <div class="field">
                            <?php if ($field['type'] === 'checkbox'): ?>
                                <label class="check">
                                    <input type="checkbox" name="<?= e($fieldName) ?>" value="Sí">
                                    <span><?= e($field['label']) ?><?= (int) $field['required'] === 1 ? ' *' : '' ?></span>
                                </label>
                            <?php else: ?>
                                <label for="<?= e($fieldId) ?>">
                                    <?= e($field['label']) ?><?= (int) $field['required'] === 1 ? ' *' : '' ?>
                                </label>
                                <?php if ($field['type'] === 'select'): ?>
                                    <select class="select <?= $hasError ? 'has-error' : '' ?>" id="<?= e($fieldId) ?>" name="<?= e($fieldName) ?>">
                                        <option value="">Trieu una opció…</option>
                                        <?php foreach ($options as $option): ?>
                                            <option value="<?= e($option) ?>"><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($field['type'] === 'textarea'): ?>
                                    <textarea class="textarea <?= $hasError ? 'has-error' : '' ?>" id="<?= e($fieldId) ?>"
                                              name="<?= e($fieldName) ?>" rows="3"></textarea>
                                <?php else: ?>
                                    <input class="input <?= $hasError ? 'has-error' : '' ?>"
                                           type="<?= $field['type'] === 'number' ? 'number' : 'text' ?>"
                                           id="<?= e($fieldId) ?>" name="<?= e($fieldName) ?>">
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($hasError): ?>
                                <span class="field__error"><?= e($errors['attendee_extra_' . $key . '_' . $field['slug']]) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endfor; endforeach; ?>

            <div class="card" style="margin:22px 0;">
                <div class="card__body">
                    <h3>Resum</h3>
                    <table class="summary-table">
                        <?php foreach ($cart['items'] as $item): ?>
                            <tr>
                                <th><?= (int) $item['quantity'] ?> × <?= e($item['type']['name']) ?></th>
                                <td style="text-align:right;"><?= money((int) $item['line_cents']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <th style="font-weight:800;color:var(--pdsh-ink);">Total</th>
                            <td class="is-total" style="text-align:right;"><?= money((int) $subtotal) ?></td>
                        </tr>
                    </table>

                    <?php if (Settings::bool('require_terms', true)): ?>
                        <div class="field" style="margin:18px 0 0;">
                            <label class="check">
                                <input type="checkbox" name="terms" value="1" required>
                                <span>
                                    Accepto les condicions de la inscripció i la
                                    <a href="<?= e(url('/informacio')) ?>" target="_blank" rel="noopener">política d'anul·lacions</a>. *
                                </span>
                            </label>
                            <?php if (isset($errors['terms'])): ?><span class="field__error"><?= e($errors['terms']) ?></span><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:space-between;align-items:center;">
                <a class="btn btn--light" href="<?= e(url('/')) ?>#inscripcions">← Canviar la selecció</a>
                <button type="submit" class="btn btn--primary btn--lg" data-loading="Connectant amb Stripe…">
                    <?= (int) $subtotal === 0 ? 'Confirmar la inscripció' : 'Pagar amb targeta →' ?>
                </button>
            </div>

            <p style="text-align:center;color:var(--pdsh-muted);font-size:.86rem;margin-top:18px;">
                🔒 El pagament es processa a la passarel·la segura de Stripe.
                Les dades de la vostra targeta no passen mai pel nostre servidor.
            </p>
        </form>
    </div>
</section>
