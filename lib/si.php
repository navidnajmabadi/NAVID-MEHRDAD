<?php
declare(strict_types=1);

require_once __DIR__ . '/openai/Client.php';

function rrp_si_toronto_day(): string
{
    $tz = new DateTimeZone('America/Toronto');
    return (new DateTimeImmutable('now', $tz))->format('Y-m-d');
}

function rrp_si_usage_row(int $ownerId, string $day): array
{
    try {
        $stmt = rrp_db()->prepare('SELECT * FROM si_usage_daily WHERE owner_id = ? AND usage_day = ? LIMIT 1');
        $stmt->execute([$ownerId, $day]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
        $ins = rrp_db()->prepare(
            'INSERT INTO si_usage_daily (owner_id, usage_day, message_count, token_count) VALUES (?, ?, 0, 0)'
        );
        $ins->execute([$ownerId, $day]);
        $stmt->execute([$ownerId, $day]);
        return $stmt->fetch() ?: [
            'owner_id' => $ownerId,
            'usage_day' => $day,
            'message_count' => 0,
            'token_count' => 0,
        ];
    } catch (Throwable $e) {
        return [
            'owner_id' => $ownerId,
            'usage_day' => $day,
            'message_count' => 0,
            'token_count' => 0,
        ];
    }
}

function rrp_si_bump_usage(int $ownerId, int $msgs = 1, int $tokens = 0): void
{
    $day = rrp_si_toronto_day();
    rrp_si_usage_row($ownerId, $day);
    try {
        $stmt = rrp_db()->prepare(
            'UPDATE si_usage_daily SET message_count = message_count + ?, token_count = token_count + ? WHERE owner_id = ? AND usage_day = ?'
        );
        $stmt->execute([$msgs, $tokens, $ownerId, $day]);
    } catch (Throwable $e) {
        // table may be missing until 006
    }
}

require_once __DIR__ . '/sipo/limits.php';
require_once __DIR__ . '/sipo/context.php';
require_once __DIR__ . '/sipo/memory.php';
require_once __DIR__ . '/sipo/prompt.php';
require_once __DIR__ . '/sipo/tools.php';
require_once __DIR__ . '/sipo/engine.php';
require_once __DIR__ . '/sipo/nudges.php';
require_once __DIR__ . '/care/catalog.php';
require_once __DIR__ . '/care/routines.php';
require_once __DIR__ . '/care/treatments.php';
require_once __DIR__ . '/care/labs.php';
require_once __DIR__ . '/care/food.php';
require_once __DIR__ . '/care/uploads.php';
require_once __DIR__ . '/care/daily.php';
require_once __DIR__ . '/lost/playbook.php';
require_once __DIR__ . '/naki/naki.php';

/**
 * Legacy entry points — SIPO logic now lives in lib/sipo/ (PR 1).
 * @return array{ok:bool, gate?:string, error?:string}
 */
function rrp_si_gate(array $owner, string $text = ''): array
{
    return rrp_sipo_gate($owner, $text);
}

function rrp_si_thread_for_pet(int $ownerId, int $petId): ?array
{
    return rrp_sipo_thread_for_pet($ownerId, $petId);
}

/**
 * @return array{ok:bool, error?:string, gate?:string, message?:array, assistant?:array}
 */
function rrp_si_send(int $ownerId, array $owner, int $petId, string $body, int $threadId = 0): array
{
    $thread = $threadId > 0
        ? rrp_sipo_thread_by_id($threadId, $ownerId)
        : ($petId > 0 ? rrp_sipo_thread_for_pet($ownerId, $petId) : null);
    if (!$thread) {
        return ['ok' => false, 'error' => 'Chat not found.'];
    }
    return rrp_sipo_send($owner, $thread, $body);
}

function rrp_si_maybe_encrypt_message(int $messageId, string $plaintext): void
{
    if ($messageId <= 0 || $plaintext === '' || !rrp_config_ready()) {
        return;
    }
    $cfg = rrp_load_config()['openai']['si_transcript'] ?? [];
    $master = (string) ($cfg['master_key'] ?? '');
    if ($master === '') {
        return;
    }
    $raw = base64_decode($master, true);
    if ($raw === false || strlen($raw) !== 32) {
        return;
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $raw, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        return;
    }
    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO si_message_secrets (message_id, ciphertext, iv, tag, sha256_hex)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE ciphertext = VALUES(ciphertext), iv = VALUES(iv), tag = VALUES(tag), sha256_hex = VALUES(sha256_hex)'
        );
        $stmt->execute([
            $messageId,
            $cipher,
            $iv,
            $tag,
            hash('sha256', $plaintext),
        ]);
    } catch (Throwable $e) {
        // 007 not imported
    }
}
