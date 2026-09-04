<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\RateLimit;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;

final class AuthController
{
    public function form(): void
    {
        if (Auth::check()) {
            Response::redirect(Url::to('/admin'));
        }

        View::render('admin/login', [
            'title' => 'Panell de Gestió',
        ], 'layouts/bare');
    }

    public function login(): void
    {
        $email = mb_strtolower(trim((string) Request::post('email', '')));
        $key = 'admin-login:' . Request::ip();

        if (!RateLimit::attempt($key, 8, 900)) {
            $wait = (int) ceil(RateLimit::retryAfter($key, 900) / 60);
            Flash::error('Massa intents fallits. Torneu-ho a provar d\'aquí a ' . max(1, $wait) . ' minuts.');
            Response::redirect(Url::to('/admin/login'));
        }

        if (!Auth::attempt($email, (string) Request::post('password', ''))) {
            Logger::warn('Intent d\'accés fallit al panell', ['correu' => $email, 'ip' => Request::ip()]);
            Flash::error('Les credencials no són correctes.');
            Response::redirect(Url::to('/admin/login'));
        }

        RateLimit::clear($key);
        Logger::audit('inici_sessio');

        $intended = Session::pull('_intended');
        Response::redirect(Url::to(is_string($intended) && str_starts_with($intended, '/admin') ? $intended : '/admin'));
    }

    public function logout(): void
    {
        Logger::audit('tancament_sessio');
        Auth::logout();
        Flash::success('Heu tancat la sessió.');
        Response::redirect(Url::to('/admin/login'));
    }
}
