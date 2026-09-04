<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Core\Validator;
use App\Core\View;

final class UserController
{
    public function index(): void
    {
        View::render('admin/users', [
            'title'  => 'Usuaris del panell',
            'users'  => Db::all('SELECT `id`, `name`, `email`, `role`, `active`, `last_login_at`, `created_at` FROM `admins` ORDER BY `id`'),
            'audit'  => Db::all('SELECT * FROM `audit_log` ORDER BY `id` DESC LIMIT 40'),
            'errors' => Flash::errors(),
        ], 'layouts/admin');
        Flash::clearOld();
    }

    public function store(): void
    {
        if (!Auth::is('owner', 'admin')) {
            Flash::error('No teniu permisos per crear usuaris.');
            Response::redirect(Url::to('/admin/usuaris'));
        }

        $email = mb_strtolower(trim((string) Request::post('email', '')));

        $validator = Validator::make($_POST)
            ->required('name', 'Cal indicar el nom.')
            ->required('email', 'Cal indicar el correu.')
            ->email('email', 'El correu no és vàlid.')
            ->required('password', 'Cal indicar una contrasenya.')
            ->minLen('password', 10, 'La contrasenya ha de tenir com a mínim 10 caràcters.')
            ->check('email_unique', Db::value('SELECT 1 FROM `admins` WHERE `email` = :e', ['e' => $email]) === false, 'Ja existeix un usuari amb aquest correu.');

        if ($validator->fails()) {
            Flash::setErrors($validator->errors());
            Flash::setOld($_POST);
            Flash::error($validator->firstError() ?? 'Reviseu les dades.');
            Response::redirect(Url::to('/admin/usuaris'));
        }

        $role = (string) Request::post('role', 'staff');
        Db::insert('admins', [
            'name'          => (string) Request::post('name'),
            'email'         => $email,
            'password_hash' => password_hash((string) Request::post('password'), PASSWORD_DEFAULT),
            'role'          => in_array($role, ['admin', 'staff'], true) ? $role : 'staff',
        ]);

        Logger::audit('crea_usuari', $email);
        Flash::success('Usuari creat.');
        Response::redirect(Url::to('/admin/usuaris'));
    }

    public function destroy(string $id): void
    {
        $id = (int) $id;

        if (!Auth::is('owner', 'admin')) {
            Flash::error('No teniu permisos per eliminar usuaris.');
            Response::redirect(Url::to('/admin/usuaris'));
        }
        if ($id === Auth::id()) {
            Flash::error('No podeu eliminar el vostre propi usuari.');
            Response::redirect(Url::to('/admin/usuaris'));
        }

        $user = Db::first('SELECT * FROM `admins` WHERE `id` = :id', ['id' => $id]);
        if ($user === null) {
            Response::redirect(Url::to('/admin/usuaris'));
        }
        if ($user['role'] === 'owner') {
            Flash::error('No es pot eliminar el propietari de la instal·lació.');
            Response::redirect(Url::to('/admin/usuaris'));
        }

        Db::run('DELETE FROM `admins` WHERE `id` = :id', ['id' => $id]);
        Logger::audit('elimina_usuari', (string) $user['email']);
        Flash::success('Usuari eliminat.');
        Response::redirect(Url::to('/admin/usuaris'));
    }

    public function changePassword(): void
    {
        $user = Auth::user();
        if ($user === null) {
            Response::redirect(Url::to('/admin/login'));
        }

        $current = (string) Request::post('current_password', '');
        $new = (string) Request::post('new_password', '');

        if (!password_verify($current, (string) $user['password_hash'])) {
            Flash::error('La contrasenya actual no és correcta.');
            Response::redirect(Url::to('/admin/usuaris'));
        }
        if (mb_strlen($new) < 10) {
            Flash::error('La contrasenya nova ha de tenir com a mínim 10 caràcters.');
            Response::redirect(Url::to('/admin/usuaris'));
        }
        if ($new !== (string) Request::post('new_password_confirm', '')) {
            Flash::error('Les dues contrasenyes noves no coincideixen.');
            Response::redirect(Url::to('/admin/usuaris'));
        }

        Db::update('admins', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], '`id` = :id', ['id' => $user['id']]);
        Logger::audit('canvia_contrasenya');
        Flash::success('Contrasenya actualitzada.');
        Response::redirect(Url::to('/admin/usuaris'));
    }
}
