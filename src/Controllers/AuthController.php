<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Security\CsrfTokenManager;
use App\Services\AuthService;
use App\Support\Flash;

final class AuthController
{
    public function __construct(private AuthService $auth)
    {
    }

    public function showLogin(Request $request): void
    {
        if ($this->auth->check()) {
            Response::redirect('/');
        }

        $token = CsrfTokenManager::generateToken('login_form');

        Response::view('auth/login', [
            'csrf_token' => $token,
            'flash' => Flash::all(),
        ]);
    }

    public function login(Request $request): void
    {
        if (!CsrfTokenManager::validateToken('login_form', $request->input('_token'))) {
            Flash::push('error', '不正なリクエストです。ページを再読み込みしてください。');
            Response::redirect('/login');
        }

        $loginId = trim((string) $request->input('login_id'));
        $password = (string) $request->input('password');

        if ($loginId === '' || $password === '') {
            Flash::push('error', 'ログインIDとパスワードを入力してください。');
            Response::redirect('/login');
        }

        if (!$this->auth->attemptLogin($loginId, $password)) {
            Flash::push('error', 'ログインに失敗しました。IDまたはパスワードをご確認ください。');
            Response::redirect('/login');
        }

        Flash::push('success', 'ログインしました。');
        Response::redirect('/');
    }

    public function logout(Request $request): void
    {
        if (!CsrfTokenManager::validateToken('logout_form', $request->input('_token'))) {
            Flash::push('error', '不正なリクエストです。');
            Response::redirect('/');
        }

        $this->auth->logout();
        Flash::push('success', 'ログアウトしました。');
        Response::redirect('/login');
    }
}
