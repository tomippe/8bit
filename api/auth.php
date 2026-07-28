<?php

declare(strict_types=1);

require __DIR__ . '/common.php';

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'me') {
    $username = currentUsername();

    if ($username === null) {
        respond([
            'ok' => true,
            'loggedIn' => false,
            'username' => null
        ]);
    }

    $users = loadUsers();

    if (!isset($users[$username])) {
        unset($_SESSION['username']);
        respond([
            'ok' => true,
            'loggedIn' => false,
            'username' => null
        ]);
    }

    respond([
        'ok' => true,
        'loggedIn' => true,
        'username' => $username,
        'highScore' => (int) ($users[$username]['highScore'] ?? 0)
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError('POST で送信してください', 405);
}

$body = jsonBody();
$username = normalizeUsername((string) ($body['username'] ?? ''));
$password = (string) ($body['password'] ?? '');

if ($action === 'register') {
    if (!isValidUsername($username)) {
        respondError('ユーザー名は半角英数と_で3〜20文字にしてください');
    }

    if (!isValidPassword($password)) {
        respondError('パスワードは4〜64文字にしてください');
    }

    $users = loadUsers();

    if (isset($users[$username])) {
        respondError('そのユーザー名は既に使われています');
    }

    $users[$username] = [
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'highScore' => 0
    ];

    saveUsers($users);
    $_SESSION['username'] = $username;

    respond([
        'ok' => true,
        'username' => $username,
        'highScore' => 0
    ]);
}

if ($action === 'login') {
    if ($username === '' || $password === '') {
        respondError('ユーザー名とパスワードを入力してください');
    }

    $users = loadUsers();

    if (
        !isset($users[$username]) ||
        !password_verify($password, (string) $users[$username]['passwordHash'])
    ) {
        respondError('ユーザー名またはパスワードが違います', 401);
    }

    $_SESSION['username'] = $username;

    respond([
        'ok' => true,
        'username' => $username,
        'highScore' => (int) ($users[$username]['highScore'] ?? 0)
    ]);
}

if ($action === 'logout') {
    unset($_SESSION['username']);
    respond(['ok' => true]);
}

respondError('不明な action です');
