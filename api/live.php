<?php

declare(strict_types=1);

require __DIR__ . '/common.php';

const LIVE_FILE = __DIR__ . '/../data/live.json';
const LIVE_TTL_SEC = 45;

function loadLive(): array
{
    if (!file_exists(LIVE_FILE)) {
        return [];
    }

    $raw = file_get_contents(LIVE_FILE);

    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function saveLive(array $live): void
{
    $dir = dirname(LIVE_FILE);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $json = json_encode(
        $live,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );

    if ($json === false) {
        respondError('ライブデータの保存に失敗しました', 500);
    }

    $result = file_put_contents(LIVE_FILE, $json, LOCK_EX);

    if ($result === false) {
        respondError('ライブデータの保存に失敗しました', 500);
    }
}

function pruneLive(array $live, int $now): array
{
    $kept = [];

    foreach ($live as $username => $entry) {
        if (!is_array($entry) || !is_string($username) || $username === '') {
            continue;
        }

        $updatedAt = (int) ($entry['updatedAt'] ?? 0);

        if ($now - $updatedAt <= LIVE_TTL_SEC) {
            $kept[$username] = $entry;
        }
    }

    return $kept;
}

function liveList(array $live): array
{
    $rows = [];

    foreach ($live as $username => $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $rows[] = [
            'username' => $username,
            'highScore' => (int) ($entry['highScore'] ?? 0),
            'currentScore' => (int) ($entry['currentScore'] ?? 0),
            'mode' => (($entry['mode'] ?? '') === '2p') ? '2p' : '1p',
            'updatedAt' => (int) ($entry['updatedAt'] ?? 0)
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        return $b['currentScore'] <=> $a['currentScore']
            ?: $b['highScore'] <=> $a['highScore']
            ?: strcmp($a['username'], $b['username']);
    });

    return $rows;
}

$method = $_SERVER['REQUEST_METHOD'];
$now = time();

if ($method === 'GET') {
    $live = pruneLive(loadLive(), $now);
    saveLive($live);

    respond([
        'ok' => true,
        'live' => liveList($live),
        'updatedAt' => $now
    ]);
}

if ($method === 'POST') {
    $username = requireLogin();
    $users = loadUsers();

    if (!isset($users[$username])) {
        unset($_SESSION['username']);
        respondError('ログインが必要です', 401);
    }

    $body = jsonBody();
    $currentScore = (int) ($body['currentScore'] ?? 0);
    $incoming1P = array_key_exists('highScore', $body)
        ? (int) $body['highScore']
        : null;
    $incoming2P = array_key_exists('highScore2P', $body)
        ? (int) $body['highScore2P']
        : null;
    $mode = (($body['mode'] ?? '') === '2p') ? '2p' : '1p';

    if ($currentScore < 0) {
        respondError('スコアが不正です');
    }

    if (
        ($incoming1P !== null && $incoming1P < 0) ||
        ($incoming2P !== null && $incoming2P < 0)
    ) {
        respondError('ハイスコアが不正です');
    }

    $highScore1P = (int) ($users[$username]['highScore'] ?? 0);
    $highScore2P = (int) ($users[$username]['highScore2P'] ?? 0);
    $changed = false;

    if ($incoming1P !== null && $incoming1P > $highScore1P) {
        $highScore1P = $incoming1P;
        $users[$username]['highScore'] = $highScore1P;
        $changed = true;
    }

    if ($incoming2P !== null && $incoming2P > $highScore2P) {
        $highScore2P = $incoming2P;
        $users[$username]['highScore2P'] = $highScore2P;
        $changed = true;
    }

    if ($changed) {
        saveUsers($users);
    }

    $displayHigh = $mode === '2p' ? $highScore2P : $highScore1P;
    $live = pruneLive(loadLive(), $now);
    $live[$username] = [
        'highScore' => $displayHigh,
        'currentScore' => $currentScore,
        'mode' => $mode,
        'updatedAt' => $now
    ];
    saveLive($live);

    respond([
        'ok' => true,
        'live' => liveList($live),
        'highScore' => $highScore1P,
        'highScore2P' => $highScore2P,
        'updatedAt' => $now
    ]);
}

if ($method === 'DELETE') {
    $username = requireLogin();
    $live = pruneLive(loadLive(), $now);
    unset($live[$username]);
    saveLive($live);

    respond([
        'ok' => true,
        'live' => liveList($live),
        'updatedAt' => $now
    ]);
}

respondError('許可されていないメソッドです', 405);
