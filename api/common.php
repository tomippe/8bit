<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

session_start();

const USERS_FILE = __DIR__ . '/../data/users.json';

function jsonBody(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function respondError(string $message, int $status = 400): void
{
    respond(['ok' => false, 'error' => $message], $status);
}

function loadUsers(): array
{
    if (!file_exists(USERS_FILE)) {
        return [];
    }

    $raw = file_get_contents(USERS_FILE);

    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function saveUsers(array $users): void
{
    $dir = dirname(USERS_FILE);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $json = json_encode(
        $users,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );

    if ($json === false) {
        respondError('データの保存に失敗しました', 500);
    }

    $result = file_put_contents(USERS_FILE, $json, LOCK_EX);

    if ($result === false) {
        respondError('データの保存に失敗しました', 500);
    }
}

function currentUsername(): ?string
{
    if (!isset($_SESSION['username']) || !is_string($_SESSION['username'])) {
        return null;
    }

    return $_SESSION['username'];
}

function requireLogin(): string
{
    $username = currentUsername();

    if ($username === null) {
        respondError('ログインが必要です', 401);
    }

    return $username;
}

function normalizeUsername(string $username): string
{
    return trim($username);
}

function isValidUsername(string $username): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_]{3,20}$/', $username);
}

function isValidPassword(string $password): bool
{
    $length = strlen($password);

    return $length >= 4 && $length <= 64;
}
