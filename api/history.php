<?php

declare(strict_types=1);

require __DIR__ . '/common.php';

$username = requireLogin();
$users = loadUsers();

if (!isset($users[$username])) {
    unset($_SESSION['username']);
    respondError('ログインが必要です', 401);
}

if (!isset($users[$username]['history']) || !is_array($users[$username]['history'])) {
    $users[$username]['history'] = [];
}

$method = $_SERVER['REQUEST_METHOD'];
const HISTORY_LIMIT = 100;

if ($method === 'GET') {
    $history = array_values($users[$username]['history']);
    usort($history, static function (array $a, array $b): int {
        return ((int) ($b['endedAt'] ?? 0)) <=> ((int) ($a['endedAt'] ?? 0));
    });

    respond([
        'ok' => true,
        'history' => $history
    ]);
}

if ($method === 'POST') {
    $body = jsonBody();
    $mode = ($body['mode'] ?? '') === '2p' ? '2p' : '1p';
    $difficultyRaw = (string) ($body['difficulty'] ?? 'normal');
    $difficulty = in_array($difficultyRaw, ['easy', 'normal', 'hard'], true)
        ? $difficultyRaw
        : 'normal';
    $twoPlayerRuleRaw = (string) ($body['twoPlayerRule'] ?? '');
    $twoPlayerRule = in_array($twoPlayerRuleRaw, ['coop', 'versus', 'versus2', 'hardcoop'], true)
        ? $twoPlayerRuleRaw
        : null;
    $score = (int) ($body['score'] ?? 0);
    $score2 = (int) ($body['score2'] ?? 0);
    $totalScore = (int) ($body['totalScore'] ?? ($score + $score2));
    $highScore = (int) ($body['highScore'] ?? 0);
    $durationMs = (int) ($body['durationMs'] ?? 0);
    $startedAt = (int) ($body['startedAt'] ?? 0);
    $endedAt = (int) ($body['endedAt'] ?? 0);

    if ($score < 0 || $score2 < 0 || $totalScore < 0 || $highScore < 0) {
        respondError('スコアが不正です');
    }

    if ($durationMs < 0 || $startedAt <= 0 || $endedAt <= 0) {
        respondError('時間が不正です');
    }

    $entry = [
        'id' => bin2hex(random_bytes(8)),
        'mode' => $mode,
        'difficulty' => $difficulty,
        'twoPlayerRule' => $mode === '2p' ? ($twoPlayerRule ?? 'coop') : null,
        'score' => $score,
        'score2' => $mode === '2p' ? $score2 : 0,
        'totalScore' => $mode === '2p' ? $totalScore : $score,
        'highScore' => $highScore,
        'durationMs' => $durationMs,
        'startedAt' => $startedAt,
        'endedAt' => $endedAt
    ];

    array_unshift($users[$username]['history'], $entry);
    $users[$username]['history'] = array_slice(
        $users[$username]['history'],
        0,
        HISTORY_LIMIT
    );
    saveUsers($users);

    respond([
        'ok' => true,
        'entry' => $entry,
        'history' => $users[$username]['history']
    ]);
}

if ($method === 'DELETE') {
    $body = jsonBody();
    $id = (string) ($body['id'] ?? '');

    if ($id === '') {
        respondError('削除対象が指定されていません');
    }

    $users[$username]['history'] = array_values(array_filter(
        $users[$username]['history'],
        static function ($entry) use ($id): bool {
            return !is_array($entry) || (($entry['id'] ?? '') !== $id);
        }
    ));
    saveUsers($users);

    respond([
        'ok' => true,
        'history' => $users[$username]['history']
    ]);
}

respondError('許可されていないメソッドです', 405);
