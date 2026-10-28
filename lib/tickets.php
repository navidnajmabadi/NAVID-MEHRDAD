<?php
declare(strict_types=1);

function rrp_ticket_create(int $ownerId, string $subject, string $body): array
{
    $subject = trim($subject);
    $body = trim($body);
    if ($subject === '' || $body === '') {
        return ['ok' => false, 'error' => 'Subject and message are required.'];
    }
    if (mb_strlen($subject) > 200) {
        return ['ok' => false, 'error' => 'Subject is too long.'];
    }
    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO support_tickets (owner_id, subject, body) VALUES (?, ?, ?)'
        );
        $stmt->execute([$ownerId, $subject, $body]);
        return ['ok' => true, 'ticket_id' => (int) rrp_db()->lastInsertId()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not send ticket')];
    }
}

function rrp_tickets_for_owner(int $ownerId): array
{
    $stmt = rrp_db()->prepare(
        'SELECT * FROM support_tickets WHERE owner_id = ? ORDER BY created_at DESC LIMIT 50'
    );
    $stmt->execute([$ownerId]);
    return $stmt->fetchAll();
}

function rrp_admin_list_tickets(?string $status = null, int $limit = 80): array
{
    if ($status !== null && $status !== '' && $status !== 'all') {
        $stmt = rrp_db()->prepare(
            'SELECT t.*, o.name AS owner_name, o.email AS owner_email
             FROM support_tickets t
             JOIN owners o ON o.id = t.owner_id
             WHERE t.status = ?
             ORDER BY t.created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $status);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    $stmt = rrp_db()->prepare(
        'SELECT t.*, o.name AS owner_name, o.email AS owner_email
         FROM support_tickets t
         JOIN owners o ON o.id = t.owner_id
         ORDER BY FIELD(t.status, \'open\', \'in_progress\', \'resolved\', \'closed\'), t.created_at DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function rrp_admin_update_ticket(int $adminId, int $ticketId, string $status, ?string $adminNote): array
{
    if (!in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
        return ['ok' => false, 'error' => 'Invalid status.'];
    }
    $resolvedSql = in_array($status, ['resolved', 'closed'], true)
        ? ', resolved_at = COALESCE(resolved_at, UTC_TIMESTAMP())'
        : '';
    $prev = rrp_db()->prepare('SELECT * FROM support_tickets WHERE id = ?');
    $prev->execute([$ticketId]);
    $before = $prev->fetch() ?: null;
    $stmt = rrp_db()->prepare(
        "UPDATE support_tickets SET
            status = ?,
            admin_note = ?,
            assigned_admin_id = ?,
            updated_at = UTC_TIMESTAMP()
            $resolvedSql
         WHERE id = ?"
    );
    $stmt->execute([$status, $adminNote, $adminId, $ticketId]);
    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Ticket not found.'];
    }
    rrp_admin_log($adminId, 'ticket_update', 'support_tickets', $ticketId, "status=$status");
    // Brief §4 Support: when Admin resolves or answers, SIPO tells the owner in their chat.
    if ($before && function_exists('rrp_sipo_thread_for_pet')) {
        $closedNow = in_array($status, ['resolved', 'closed'], true) && !in_array($before['status'], ['resolved', 'closed'], true);
        $newNote = trim((string) $adminNote) !== '' && trim((string) $adminNote) !== trim((string) $before['admin_note']);
        if ($closedNow || $newNote) {
            rrp_ticket_tell_owner($before, $status, (string) $adminNote, $closedNow);
        }
    }
    return ['ok' => true];
}

/** SIPO message + push to the owner about their ticket. */
function rrp_ticket_tell_owner(array $ticket, string $status, string $note, bool $closed): void
{
    $ownerId = (int) $ticket['owner_id'];
    $subject = preg_replace('/^\[(SIPO|Auto)\]\s*/', '', (string) $ticket['subject']);
    $text = $closed
        ? "Good news from the SIPAW team about \"{$subject}\": it's sorted ✅" . ($note !== '' ? " Their note: {$note}" : '')
        : "The SIPAW team replied about \"{$subject}\": {$note}";
    $pets = rrp_pets_by_owner($ownerId);
    $pet = null;
    foreach ($pets as $p) {
        if (!empty($ticket['pet_id']) && (int) $p['id'] === (int) $ticket['pet_id']) {
            $pet = $p;
        }
    }
    $pet = $pet ?? ($pets[0] ?? null);
    if ($pet) {
        $thread = rrp_sipo_thread_for_pet($ownerId, (int) $pet['id']);
        if ($thread) {
            rrp_sipo_insert_message((int) $thread['id'], 'assistant', $text, 0, ['mascot' => 'happy', 'proactive' => 'support']);
        }
    }
    rrp_notify_owner_inbox($ownerId, 'support', 'Support: ' . mb_substr($subject, 0, 120), $text);
    rrp_try_web_push($ownerId, 'SIPAW Support', $text, rrp_app_url('si-chat.php'));
}
