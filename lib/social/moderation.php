<?php
declare(strict_types=1);

/**
 * Social moderation (brief §E + Navid 2026-10-28): AI removes porn, drugs, fraud and junk,
 * every decision is logged per user, and an account with MORE than 5 removals is suspended
 * until Support reviews it. Reports from 3 different people hide a post for admin review.
 */

const RRP_MOD_SUSPEND_AFTER = 5;   // suspend on the 6th removal
const RRP_MOD_REPORTS_TO_HIDE = 3;

/** Words that are never OK on a pet board (used when the AI is unavailable, and always as a backstop). */
const RRP_MOD_BLOCKLIST = [
    'porn', 'nude', 'nudes', 'onlyfans', 'xxx', 'sex cam',
    'cocaine', 'heroin', 'meth', 'mdma', 'weed for sale', 'buy weed', 'xanax',
    'bitcoin', 'crypto giveaway', 'double your money', 'western union', 'gift card', 'wire transfer',
    'puppies for sale', 'kittens for sale', 'deposit to reserve',
];

/**
 * Decide if content may be shown.
 * @return array{allow:bool, reason:?string, source:string, categories:array}
 */
function rrp_mod_check(string $text, ?string $imageJpeg = null): array
{
    $t = mb_strtolower($text);
    foreach (RRP_MOD_BLOCKLIST as $w) {
        if (str_contains($t, $w)) {
            return ['allow' => false, 'reason' => 'Not allowed on SIPAW boards (' . $w . ')', 'source' => 'filter', 'categories' => ['blocklist' => true]];
        }
    }
    // Junk: no letters, or one character repeated, or link spam.
    if ($text !== '' && (!preg_match('/\p{L}/u', $text) || preg_match('/(.)\1{9,}/u', $text) || preg_match_all('/https?:\/\//i', $text) > 2)) {
        return ['allow' => false, 'reason' => 'Looks like spam or junk', 'source' => 'filter', 'categories' => ['junk' => true]];
    }

    $client = RrpOpenAiClient::product();
    if (!$client->configured()) {
        return ['allow' => true, 'reason' => null, 'source' => 'filter', 'categories' => []];
    }
    $img = $imageJpeg !== null ? 'data:image/jpeg;base64,' . base64_encode($imageJpeg) : null;
    $mod = $client->moderate($text, $img);
    if (!empty($mod['ok']) && !empty($mod['flagged'])) {
        $cats = array_keys($mod['categories']);
        return ['allow' => false, 'reason' => 'Removed by SIPAW safety check: ' . implode(', ', array_slice($cats, 0, 3)), 'source' => 'ai', 'categories' => $mod['categories']];
    }

    // Fraud and junk aren't moderation categories — a short classifier covers them.
    if ($text !== '') {
        $res = $client->json([
            ['role' => 'system', 'content' =>
                'You review posts on a friendly pet-owner community. Return JSON only: '
                . '{"fraud":bool,"spam":bool,"off_topic_junk":bool,"reason":"max 8 words"}. '
                . 'fraud = scams, selling animals with deposits, fake giveaways, asking for money or personal data. '
                . 'spam = ads, links to shops, repeated promotion. off_topic_junk = meaningless text or nothing about pets/people. '
                . 'Normal questions, jokes, photos captions and pet stories are fine.'],
            ['role' => 'user', 'content' => mb_substr($text, 0, 600)],
        ]);
        $x = $res['data'] ?? [];
        if (!empty($res['ok']) && (!empty($x['fraud']) || !empty($x['spam']) || !empty($x['off_topic_junk']))) {
            $kind = !empty($x['fraud']) ? 'fraud' : (!empty($x['spam']) ? 'spam' : 'junk');
            return ['allow' => false, 'reason' => 'Removed as ' . $kind . (!empty($x['reason']) ? ': ' . mb_substr((string) $x['reason'], 0, 80) : ''), 'source' => 'ai', 'categories' => [$kind => true]];
        }
    }
    return ['allow' => true, 'reason' => null, 'source' => 'ai', 'categories' => []];
}

function rrp_mod_log(int $ownerId, string $type, int $id, string $action, string $source, string $reason, array $cats = [], ?string $excerpt = null, ?int $adminId = null): void
{
    try {
        rrp_db()->prepare(
            'INSERT INTO moderation_log (owner_id, target_type, target_id, action, source, reason, categories_json, excerpt, admin_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$ownerId, $type, $id, $action, $source, mb_substr($reason, 0, 160), $cats ? json_encode($cats) : null,
            $excerpt !== null ? mb_substr($excerpt, 0, 250) : null, $adminId]);
    } catch (Throwable $e) {
        error_log('rrp_mod_log: ' . $e->getMessage());
    }
}

/** Removals that still stand (removed minus later restored). */
function rrp_mod_removal_count(int $ownerId): int
{
    try {
        $st = rrp_db()->prepare(
            "SELECT COUNT(*) FROM moderation_log l WHERE l.owner_id = ? AND l.action = 'removed'
             AND NOT EXISTS (SELECT 1 FROM moderation_log r WHERE r.target_type = l.target_type AND r.target_id = l.target_id
                             AND r.action = 'restored' AND r.id > l.id)"
        );
        $st->execute([$ownerId]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Remove a post/comment, log it, tell the author, and suspend after too many removals.
 * @return array{removals:int, suspended:bool}
 */
function rrp_mod_remove(string $type, int $id, int $authorId, string $reason, string $source, array $cats = [], ?string $excerpt = null, ?int $adminId = null): array
{
    $table = $type === 'comment' ? 'forum_comments' : 'forum_posts';
    rrp_db()->prepare("UPDATE {$table} SET moderation_status = 'removed', ai_flagged = ?, removed_reason = ?, removed_at = UTC_TIMESTAMP() WHERE id = ?")
        ->execute([$source === 'ai' ? 1 : 0, mb_substr($reason, 0, 160), $id]);
    rrp_mod_log($authorId, $type, $id, 'removed', $source, $reason, $cats, $excerpt, $adminId);
    $n = rrp_mod_removal_count($authorId);
    rrp_notify_owner_inbox($authorId, 'moderation', 'Your ' . $type . ' was removed',
        $reason . "\n\nSIPAW boards are for friendly pet talk. Removals so far: {$n} of " . RRP_MOD_SUSPEND_AFTER . ' allowed.');
    $suspended = false;
    if ($n > RRP_MOD_SUSPEND_AFTER) {
        $suspended = rrp_mod_suspend($authorId, "More than " . RRP_MOD_SUSPEND_AFTER . " removed posts/comments");
    }
    return ['removals' => $n, 'suspended' => $suspended];
}

/** What the author sees after a removal. */
function rrp_mod_owner_message(string $reason, array $m): string
{
    if (!empty($m['suspended'])) {
        return $reason . '. Your account is now paused for review; Support will email you within 7 days.';
    }
    $left = RRP_MOD_SUSPEND_AFTER - (int) $m['removals'];
    return $reason . '. ' . ($left > 0 ? "Please keep it friendly: {$left} more removal" . ($left === 1 ? '' : 's') . ' and the account is paused for review.'
        : 'One more removal and the account is paused for review.');
}

/** Suspend + open a Support ticket so a human reviews it (Navid: contact Support to check manually). */
function rrp_mod_suspend(int $ownerId, string $reason): bool
{
    $st = rrp_db()->prepare("UPDATE owners SET status = 'suspended', suspended_reason = ?, suspended_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'active'");
    $st->execute([mb_substr($reason, 0, 160), $ownerId]);
    if ($st->rowCount() === 0) {
        return false;
    }
    rrp_auth_forget_all_devices($ownerId);
    rrp_ticket_create($ownerId, '[Auto] Account suspended for review', $reason . '. Please review the moderation log in Admin → Moderation and reply to the owner.');
    $email = rrp_owner_email($ownerId);
    if ($email) {
        rrp_send_mail($email, 'Your SIPAW account is paused for review',
            "Hi,\n\nYour SIPAW account was paused because several posts or comments were removed by our safety checks.\n"
            . "Our Support team will review it by hand and reply to this email within 7 days. Your pets' health data is safe.\n\nSIPAW");
    }
    return true;
}

function rrp_mod_unsuspend(int $ownerId, int $adminId): void
{
    rrp_db()->prepare("UPDATE owners SET status = 'active', suspended_reason = NULL, suspended_at = NULL WHERE id = ?")->execute([$ownerId]);
    rrp_admin_log($adminId, 'owner_unsuspend', 'owner', $ownerId, null);
}

/** Restore content an admin decides was fine. */
function rrp_mod_restore(string $type, int $id, int $adminId): void
{
    $table = $type === 'comment' ? 'forum_comments' : 'forum_posts';
    $row = rrp_db()->query("SELECT owner_id FROM {$table} WHERE id = " . (int) $id)->fetch();
    if (!$row) {
        return;
    }
    rrp_db()->prepare("UPDATE {$table} SET moderation_status = 'visible', removed_reason = NULL, removed_at = NULL WHERE id = ?")->execute([$id]);
    rrp_mod_log((int) $row['owner_id'], $type, $id, 'restored', 'admin', 'Restored by admin', [], null, $adminId);
    rrp_db()->prepare("UPDATE forum_reports SET status = 'dismissed', resolved_at = UTC_TIMESTAMP() WHERE target_type = ? AND target_id = ? AND status IN ('open','reviewing')")
        ->execute([$type, $id]);
}

/** After a report: 3 different reporters → hide for review (doesn't count as a removal yet). */
function rrp_mod_after_report(string $type, int $id): bool
{
    $st = rrp_db()->prepare("SELECT COUNT(DISTINCT reporter_owner_id) FROM forum_reports WHERE target_type = ? AND target_id = ? AND status IN ('open','reviewing')");
    $st->execute([$type, $id]);
    if ((int) $st->fetchColumn() < RRP_MOD_REPORTS_TO_HIDE) {
        return false;
    }
    $table = $type === 'comment' ? 'forum_comments' : 'forum_posts';
    $row = rrp_db()->query("SELECT owner_id, body, moderation_status FROM {$table} WHERE id = " . (int) $id)->fetch();
    if (!$row || $row['moderation_status'] !== 'visible') {
        return false;
    }
    rrp_db()->prepare("UPDATE {$table} SET moderation_status = 'hidden' WHERE id = ?")->execute([$id]);
    rrp_db()->prepare("UPDATE forum_reports SET status = 'reviewing' WHERE target_type = ? AND target_id = ? AND status = 'open'")->execute([$type, $id]);
    rrp_mod_log((int) $row['owner_id'], $type, $id, 'hidden', 'reports', RRP_MOD_REPORTS_TO_HIDE . '+ reports — waiting for review', [], (string) $row['body']);
    return true;
}
