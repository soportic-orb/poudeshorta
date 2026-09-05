<?php
use App\Core\Csrf;
use App\Core\Money;

$isNew = (int) ($type['id'] ?? 0) === 0;
$action = $isNew ? url('/admin/tipus-inscripcio/nou') : url('/admin/tipus-inscripcio/' . $type['id']);
$errors = $errors ?? [];
$fields = $fields ?? [];
$price = old('price', $isNew ? '' : Money::toDecimal((int) $type['price_cents']));
?>

<p><a href="<?= e(url('/admin/tipus-inscripcio')) ?>">← Tornar als tipus d'inscripció</a></p>

<form method="post" action="<?= e($action) ?>">
    <?= Csrf::field() ?>

    <div class="panel">
        <div class="panel__head"><div><h2>Dades bàsiques</h2></div></div>
        <div class="panel__body">
            <div class="field">
                <label for="name">Nom del tipus d'inscripció *</label>
                <input class="input <?= isset($errors['name']) ? 'has-error' : '' ?>" type="text" id="name" name="name"
                       value="<?= old('name', $type['name']) ?>" required placeholder="Sopar adult">
                <?php if (isset($errors['name'])): ?><span class="field__error"><?= e($errors['name']) ?></span><?php endif; ?>
            </div>

            <div class="field">
                <label for="description">Descripció breu</label>
                <textarea class="textarea" id="description" name="description" rows="2"
                          placeholder="Una frase que apareix sota el nom al web públic."><?= old('description', $type['description']) ?></textarea>
            </div>

            <div class="field">
                <label for="includes">Què inclou</label>
                <textarea class="textarea" id="includes" name="includes" rows="5"
                          placeholder="Una línia per concepte:&#10;Paella popular i pa&#10;Beguda&#10;Postres i cafè"><?= old('includes', $type['includes']) ?></textarea>
                <span class="field__hint">Cada línia es mostra com un punt de la llista, al web i a l'entrada en PDF.</span>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="price">Preu (€) *</label>
                    <input class="input <?= isset($errors['price']) ? 'has-error' : '' ?>" type="text" inputmode="decimal"
                           id="price" name="price" value="<?= e($price) ?>" placeholder="15,00">
                    <span class="field__hint">Posa 0 per a inscripcions gratuïtes (no passen per Stripe).</span>
                    <?php if (isset($errors['price'])): ?><span class="field__error"><?= e($errors['price']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="quota">Places disponibles</label>
                    <input class="input <?= isset($errors['quota']) ? 'has-error' : '' ?>" type="number" min="0"
                           id="quota" name="quota" value="<?= old('quota', $type['quota']) ?>" placeholder="Sense límit">
                    <span class="field__hint">Deixa-ho buit si no hi ha límit.<?= isset($sold) ? ' Ja se n\'han venut ' . (int) $sold . '.' : '' ?></span>
                    <?php if (isset($errors['quota'])): ?><span class="field__error"><?= e($errors['quota']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="max_per_order">Màxim per comanda</label>
                    <input class="input" type="number" min="1" id="max_per_order" name="max_per_order"
                           value="<?= old('max_per_order', $type['max_per_order']) ?>">
                </div>

                <div class="field">
                    <label for="min_per_order">Mínim per comanda</label>
                    <input class="input" type="number" min="0" id="min_per_order" name="min_per_order"
                           value="<?= old('min_per_order', $type['min_per_order']) ?>">
                    <span class="field__hint">0 = sense mínim.</span>
                </div>

                <div class="field">
                    <label for="sales_start">Inici de la venda</label>
                    <input class="input" type="datetime-local" id="sales_start" name="sales_start"
                           value="<?= e($type['sales_start'] ? date('Y-m-d\TH:i', strtotime((string) $type['sales_start'])) : '') ?>">
                </div>

                <div class="field">
                    <label for="sales_end">Fi de la venda</label>
                    <input class="input" type="datetime-local" id="sales_end" name="sales_end"
                           value="<?= e($type['sales_end'] ? date('Y-m-d\TH:i', strtotime((string) $type['sales_end'])) : '') ?>">
                </div>

                <div class="field">
                    <label for="sort_order">Ordre de visualització</label>
                    <input class="input" type="number" id="sort_order" name="sort_order"
                           value="<?= old('sort_order', $type['sort_order']) ?>">
                </div>
            </div>

            <div class="field">
                <label class="check">
                    <input type="checkbox" name="requires_attendee_name" value="1"<?= checkedIf((int) $type['requires_attendee_name'] === 1) ?>>
                    <span>Demanar obligatòriament el nom de cada assistent</span>
                </label>
            </div>
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="active" value="1"<?= checkedIf((int) $type['active'] === 1) ?>>
                    <span>Actiu (visible al web públic)</span>
                </label>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head">
            <div>
                <h2>Camps addicionals del formulari</h2>
                <p>Preguntes extra per a cada assistent d'aquest tipus (talla, al·lèrgies, taula…).</p>
            </div>
            <button type="button" class="btn btn--light btn--sm" id="add-field">+ Afegir camp</button>
        </div>
        <div class="panel__body">
            <div id="fields-repeater">
                <?php foreach ($fields as $index => $field): ?>
                    <div class="repeater__row">
                        <input type="hidden" name="field_id[<?= $index ?>]" value="<?= (int) $field['id'] ?>">
                        <div class="field" style="margin:0;">
                            <label class="field__label">Etiqueta</label>
                            <input class="input" type="text" name="field_label[<?= $index ?>]" value="<?= e($field['label']) ?>">
                        </div>
                        <div class="field" style="margin:0;">
                            <label class="field__label">Tipus</label>
                            <select class="select" name="field_type[<?= $index ?>]">
                                <?php foreach (['text' => 'Text', 'number' => 'Número', 'select' => 'Llista', 'checkbox' => 'Casella', 'textarea' => 'Text llarg'] as $value => $label): ?>
                                    <option value="<?= $value ?>"<?= selectedIf($field['type'] === $value) ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field" style="margin:0;">
                            <label class="field__label">Opcions (només llista)</label>
                            <input class="input" type="text" name="field_options[<?= $index ?>]" value="<?= e($field['options']) ?>"
                                   placeholder="Separades per comes">
                        </div>
                        <div class="field" style="margin:0;">
                            <label class="check" style="margin-top:24px;">
                                <input type="checkbox" name="field_required[<?= $index ?>]" value="1"<?= checkedIf((int) $field['required'] === 1) ?>>
                                <span>Obligatori</span>
                            </label>
                        </div>
                        <button type="button" class="repeater__remove" aria-label="Eliminar el camp">×</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <p style="color:var(--pdsh-muted);font-size:.86rem;margin:0;">
                Els camps que esborris d'aquesta llista deixaran d'aparèixer al formulari;
                les respostes ja recollides es conserven a cada entrada.
            </p>
        </div>
        <div class="panel__foot">
            <button type="submit" class="btn btn--primary"><?= $isNew ? 'Crear el tipus d\'inscripció' : 'Desar els canvis' ?></button>
            <a class="btn btn--light" href="<?= e(url('/admin/tipus-inscripcio')) ?>">Cancel·lar</a>
        </div>
    </div>
</form>

<template id="field-template">
    <div class="repeater__row">
        <input type="hidden" name="field_id[__i__]" value="0">
        <div class="field" style="margin:0;">
            <label class="field__label">Etiqueta</label>
            <input class="input" type="text" name="field_label[__i__]" placeholder="Talla de samarreta">
        </div>
        <div class="field" style="margin:0;">
            <label class="field__label">Tipus</label>
            <select class="select" name="field_type[__i__]">
                <option value="text">Text</option>
                <option value="number">Número</option>
                <option value="select">Llista</option>
                <option value="checkbox">Casella</option>
                <option value="textarea">Text llarg</option>
            </select>
        </div>
        <div class="field" style="margin:0;">
            <label class="field__label">Opcions (només llista)</label>
            <input class="input" type="text" name="field_options[__i__]" placeholder="S, M, L, XL">
        </div>
        <div class="field" style="margin:0;">
            <label class="check" style="margin-top:24px;">
                <input type="checkbox" name="field_required[__i__]" value="1">
                <span>Obligatori</span>
            </label>
        </div>
        <button type="button" class="repeater__remove" aria-label="Eliminar el camp">×</button>
    </div>
</template>

<script>
(function () {
    var repeater = document.getElementById('fields-repeater');
    var template = document.getElementById('field-template');
    var index = repeater.querySelectorAll('.repeater__row').length;

    document.getElementById('add-field').addEventListener('click', function () {
        var html = template.innerHTML.replace(/__i__/g, String(index++));
        var holder = document.createElement('div');
        holder.innerHTML = html.trim();
        repeater.appendChild(holder.firstChild);
    });

    repeater.addEventListener('click', function (event) {
        if (event.target.classList.contains('repeater__remove')) {
            event.target.closest('.repeater__row').remove();
        }
    });
})();
</script>
