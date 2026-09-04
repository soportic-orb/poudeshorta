<?php
use App\Core\Csrf;
use App\Core\View;

$s = $settings;
?>

<?= View::partial('admin/_settings_tabs') ?>

<form method="post" action="<?= e(url('/admin/configuracio')) ?>">
    <?= Csrf::field() ?>

    <div class="panel">
        <div class="panel__head"><div><h2>Dades de l'esdeveniment</h2><p>Apareixen al web públic, als correus i a les entrades.</p></div></div>
        <div class="panel__body">
            <div class="field">
                <label for="event_name">Nom de l'esdeveniment</label>
                <input class="input" type="text" id="event_name" name="event_name" value="<?= e($s['event_name']) ?>">
            </div>

            <div class="field">
                <label for="event_tagline">Lema o subtítol</label>
                <input class="input" type="text" id="event_tagline" name="event_tagline" value="<?= e($s['event_tagline']) ?>">
            </div>

            <div class="field">
                <label for="event_description">Descripció</label>
                <textarea class="textarea" id="event_description" name="event_description" rows="4"><?= e($s['event_description']) ?></textarea>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="event_date_text">Data (text visible)</label>
                    <input class="input" type="text" id="event_date_text" name="event_date_text"
                           value="<?= e($s['event_date_text']) ?>" placeholder="26 de setembre, al vespre">
                </div>
                <div class="field">
                    <label for="event_date">Data i hora exactes</label>
                    <input class="input" type="datetime-local" id="event_date" name="event_date"
                           value="<?= e($s['event_date'] !== '' ? date('Y-m-d\TH:i', (int) strtotime((string) $s['event_date'])) : '') ?>">
                    <span class="field__hint">S'utilitza per calcular el termini d'anul·lació i per als passis de wallet.</span>
                </div>
                <div class="field">
                    <label for="event_location">Lloc</label>
                    <input class="input" type="text" id="event_location" name="event_location" value="<?= e($s['event_location']) ?>">
                </div>
                <div class="field">
                    <label for="event_city">Població</label>
                    <input class="input" type="text" id="event_city" name="event_city" value="<?= e($s['event_city']) ?>">
                </div>
                <div class="field">
                    <label for="event_map_url">Enllaç al mapa</label>
                    <input class="input" type="url" id="event_map_url" name="event_map_url" value="<?= e($s['event_map_url']) ?>"
                           placeholder="https://maps.google.com/?q=41.55,1.62">
                </div>
                <div class="field">
                    <label for="timezone">Zona horària</label>
                    <input class="input" type="text" id="timezone" name="timezone" value="<?= e($s['timezone']) ?>">
                </div>
            </div>

            <div class="field">
                <label for="event_highlights">Atractius de l'esdeveniment</label>
                <textarea class="textarea" id="event_highlights" name="event_highlights" rows="4"
                          placeholder="Una línia per atractiu:&#10;Gran paellada popular&#10;Música ambient&#10;Bingo"><?= e($s['event_highlights']) ?></textarea>
                <span class="field__hint">Es mostren com a targetes destacades a la pàgina d'inici.</span>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="event_organizer">Organitza</label>
                    <input class="input" type="text" id="event_organizer" name="event_organizer" value="<?= e($s['event_organizer']) ?>">
                </div>
                <div class="field">
                    <label for="event_contact_email">Correu de contacte</label>
                    <input class="input" type="email" id="event_contact_email" name="event_contact_email" value="<?= e($s['event_contact_email']) ?>">
                </div>
                <div class="field">
                    <label for="event_contact_phone">Telèfon de contacte</label>
                    <input class="input" type="tel" id="event_contact_phone" name="event_contact_phone" value="<?= e($s['event_contact_phone']) ?>">
                </div>
                <div class="field">
                    <label for="google_analytics">ID de Google Analytics</label>
                    <input class="input" type="text" id="google_analytics" name="google_analytics"
                           value="<?= e($s['google_analytics']) ?>" placeholder="G-XXXXXXXXXX">
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head"><div><h2>Inscripcions</h2></div></div>
        <div class="panel__body">
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="sales_open" value="1"<?= checkedIf($s['sales_open'] === '1') ?>>
                    <span><strong>Inscripcions obertes</strong> — el públic pot comprar entrades</span>
                </label>
            </div>

            <div class="field">
                <label for="sales_closed_message">Missatge quan estan tancades</label>
                <input class="input" type="text" id="sales_closed_message" name="sales_closed_message" value="<?= e($s['sales_closed_message']) ?>">
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="max_tickets_order">Màxim d'entrades per comanda</label>
                    <input class="input" type="number" min="1" id="max_tickets_order" name="max_tickets_order" value="<?= e($s['max_tickets_order']) ?>">
                </div>
                <div class="field">
                    <label for="terms_url">Enllaç a les condicions (opcional)</label>
                    <input class="input" type="url" id="terms_url" name="terms_url" value="<?= e($s['terms_url']) ?>">
                </div>
            </div>

            <div class="field">
                <label class="check">
                    <input type="checkbox" name="require_terms" value="1"<?= checkedIf($s['require_terms'] === '1') ?>>
                    <span>Exigir l'acceptació de les condicions al formulari</span>
                </label>
            </div>

            <div class="field">
                <label for="privacy_text">Text de protecció de dades</label>
                <textarea class="textarea" id="privacy_text" name="privacy_text" rows="5"><?= e($s['privacy_text']) ?></textarea>
                <span class="field__hint">Es publica a la pàgina d'informació.</span>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head"><div><h2>Operativa</h2></div></div>
        <div class="panel__body">
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="checkin_enabled" value="1"<?= checkedIf($s['checkin_enabled'] === '1') ?>>
                    <span>Control d'accés actiu (validació d'entrades amb QR)</span>
                </label>
            </div>
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="maintenance_mode" value="1"<?= checkedIf($s['maintenance_mode'] === '1') ?>>
                    <span><strong>Mode manteniment</strong> — el web públic queda inaccessible per als visitants</span>
                </label>
            </div>
        </div>
        <div class="panel__foot">
            <button type="submit" class="btn btn--primary">Desar els canvis</button>
        </div>
    </div>
</form>
