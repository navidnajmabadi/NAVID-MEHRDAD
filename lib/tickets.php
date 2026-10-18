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
    return ['ok' => true];
}
