<?php

declare(strict_types=1);

require __DIR__ . '/common.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    respondError('許可されていないメソッドです', 405);
}

$users = loadUsers();
$limit = 50;
$ranking1P = [];
$ranking2P = [];

foreach ($users as $username => $user) {
    if (!is_array($user) || !is_string($username) || $username === '') {
        continue;
    }

    if (($user['rankingEnabled'] ?? true) === false) {
        continue;
    }

    $highScore = (int) ($user['highScore'] ?? 0);
    $highScore2P = (int) ($user['highScore2P'] ?? 0);

    if ($highScore > 0) {
        $ranking1P[] = [
            'username' => $username,
            'highScore' => $highScore
        ];
    }

    if ($highScore2P > 0) {
        $ranking2P[] = [
            'username' => $username,
            'highScore' => $highScore2P
        ];
    }
}

usort($ranking1P, static function (array $a, array $b): int {
    return $b['highScore'] <=> $a['highScore']
        ?: strcmp($a['username'], $b['username']);
});

usort($ranking2P, static function (array $a, array $b): int {
    return $b['highScore'] <=> $a['highScore']
        ?: strcmp($a['username'], $b['username']);
});

respond([
    'ok' => true,
    'ranking1P' => array_slice($ranking1P, 0, $limit),
    'ranking2P' => array_slice($ranking2P, 0, $limit),
    'updatedAt' => time()
]);
