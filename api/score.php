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

function userScores(array $user): array
{
    return [
        'highScore' => (int) ($user['highScore'] ?? 0),
        'highScore2P' => (int) ($user['highScore2P'] ?? 0)
    ];
}

if ($method === 'GET') {
    respond(array_merge(['ok' => true], userScores($users[$username])));
}

if ($method === 'POST') {
    $body = jsonBody();
    $incoming1P = array_key_exists('highScore', $body)
        ? (int) $body['highScore']
        : null;
    $incoming2P = array_key_exists('highScore2P', $body)
        ? (int) $body['highScore2P']
        : null;

    if (
        ($incoming1P !== null && $incoming1P < 0) ||
        ($incoming2P !== null && $incoming2P < 0)
    ) {
        respondError('ハイスコアが不正です');
    }

    $scores = userScores($users[$username]);

    if ($incoming1P !== null) {
        $scores['highScore'] = max($scores['highScore'], $incoming1P);
        $users[$username]['highScore'] = $scores['highScore'];
    }

    if ($incoming2P !== null) {
        $scores['highScore2P'] = max($scores['highScore2P'], $incoming2P);
        $users[$username]['highScore2P'] = $scores['highScore2P'];
    }

    if (array_key_exists('rankingEnabled', $body)) {
        $users[$username]['rankingEnabled'] = (bool) $body['rankingEnabled'];
    }

    saveUsers($users);

    respond(array_merge(
        ['ok' => true, 'rankingEnabled' => ($users[$username]['rankingEnabled'] ?? true) !== false],
        $scores
    ));
}

if ($method === 'DELETE') {
    $users[$username]['highScore'] = 0;
    $users[$username]['highScore2P'] = 0;
    saveUsers($users);

    respond([
        'ok' => true,
        'highScore' => 0,
        'highScore2P' => 0
    ]);
}

respondError('許可されていないメソッドです', 405);
