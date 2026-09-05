<?php
use App\Controllers\Admin\CampaignController;
use App\Core\Csrf;
use App\Core\Db;

$types = Db::all('SELECT `id`, `name` FROM `ticket_types` ORDER BY `sort_order`, `id`');
$errors = $errors ?? [];
?>

<p><a href="<?= e(url('/admin/comunicacions')) ?>">← Tornar als comunicats</a></p>

<form method="post" action="<?= e(url('/admin/comunicacions/nova')) ?>">
    <?= Csrf::field() ?>

    <div class="panel">
        <div class="panel__head">
            <div>
                <h2>Redactar el comunicat</h2>
                <p>Es desa com a esborrany; el podràs previsualitzar i provar abans d'enviar-lo.</p>
            </div>
        </div>
        <div class="panel__body">
            <div class="form-grid">
                <div class="field">
                    <label for="audiencia">A qui s'envia</label>
                    <select class="select" id="audiencia" name="audiencia">
                        <?php foreach ($recipients as $option): ?>
                            <option value="<?= e($option['value']) ?>"><?= e($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="tipus">Només d'un tipus concret</label>
                    <select class="select" id="tipus" name="tipus">
                        <option value="">Tots els tipus</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?= (int) $type['id'] ?>"><?= e($type['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="subject">Assumpte *</label>
                <input class="input <?= isset($errors['subject']) ? 'has-error' : '' ?>" type="text" id="subject" name="subject"
                       value="<?= old('subject') ?>" placeholder="Informació important sobre el sopar del dissabte" required>
                <?php if (isset($errors['subject'])): ?><span class="field__error"><?= e($errors['subject']) ?></span><?php endif; ?>
            </div>

            <div class="field">
                <label for="body">Missatge *</label>
                <textarea class="textarea <?= isset($errors['body']) ? 'has-error' : '' ?>" id="body" name="body" rows="14"
                          placeholder="Hola {{name}},&#10;&#10;T'escrivim per recordar-te que…" required><?= old('body') ?></textarea>
                <span class="field__hint">
                    Escriu en text pla: els paràgrafs i els salts de línia es respecten i els enllaços es converteixen automàticament.
                </span>
                <?php if (isset($errors['body'])): ?><span class="field__error"><?= e($errors['body']) ?></span><?php endif; ?>
            </div>

            <div class="alert alert--info">
                <span aria-hidden="true">ℹ️</span>
                <span>
                    <strong>Marcadors disponibles</strong> (se substitueixen per les dades de cada persona):<br>
                    <?php foreach (CampaignController::placeholders() as $tag => $description): ?>
                        <code><?= e($tag) ?></code> <?= e($description) ?><br>
                    <?php endforeach; ?>
                </span>
            </div>
        </div>
        <div class="panel__foot">
            <button type="submit" class="btn btn--primary">Desar l'esborrany</button>
            <a class="btn btn--light" href="<?= e(url('/admin/comunicacions')) ?>">Cancel·lar</a>
        </div>
    </div>
</form>
