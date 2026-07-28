<?php

declare(strict_types=1);

require __DIR__ . '/common.php';

$username = requireLogin();
$users = loadUsers();

if (!isset($users[$username])) {
    unset($_SESSION['username']);
    respondError('ログインが必要です', 401);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    respond([
        'ok' => true,
        'highScore' => (int) ($users[$username]['highScore'] ?? 0)
    ]);
}

if ($method === 'POST') {
    $body = jsonBody();
    $incoming = (int) ($body['highScore'] ?? 0);

    if ($incoming < 0) {
        respondError('ハイスコアが不正です');
    }

    $current = (int) ($users[$username]['highScore'] ?? 0);
    $saved = max($current, $incoming);
    $users[$username]['highScore'] = $saved;
    saveUsers($users);

    respond([
        'ok' => true,
        'highScore' => $saved
    ]);
}

if ($method === 'DELETE') {
    $users[$username]['highScore'] = 0;
    saveUsers($users);

    respond([
        'ok' => true,
        'highScore' => 0
    ]);
}

respondError('許可されていないメソッドです', 405);
