<?php
declare(strict_types=1);

function rrp_forum_feed(int $limit = 40, ?string $species = null): array
{
    $limit = max(1, min(100, $limit));
    $sql = "SELECT p.*, pets.name AS pet_name, pets.type AS pet_type, pets.species_key, pets.image_path AS pet_image,
                   owners.name AS owner_name
            FROM forum_posts p
            JOIN pets ON pets.id = p.pet_id
            JOIN owners ON owners.id = p.owner_id
            WHERE p.moderation_status = 'visible'";
    $args = [];
    if ($species) {
        $sql .= ' AND (pets.species_key = ? OR pets.type = ?)';
        $args[] = $species;
        $args[] = $species;
    }
    $sql .= " ORDER BY p.created_at DESC LIMIT {$limit}";
    try {
        $stmt = rrp_db()->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('rrp_forum_feed: ' . $e->getMessage());
        return [];
    }
}

function rrp_forum_post_by_id(int $id): ?array
{
    try {
        $stmt = rrp_db()->prepare(
            'SELECT p.*, pets.name AS pet_name, pets.type AS pet_type, pets.public_id AS pet_public_id
             FROM forum_posts p JOIN pets ON pets.id = p.pet_id WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * @return array{ok:bool, error?:string, post_id?:int}
 */
function rrp_forum_create_post(int $ownerId, int $petId, string $body): array
{
    $pet = rrp_pet_by_id($petId, $ownerId);
    if (!$pet || (int) ($pet['is_active'] ?? 1) !== 1) {
        return ['ok' => false, 'error' => 'Pet not found.'];
    }
    $body = trim($body);
    if ($body === '' || mb_strlen($body) > 250) {
        return ['ok' => false, 'error' => 'Post body required (max 250 chars).'];
    }
    try {
        $stmt = rrp_db()->prepare(
            "INSERT INTO forum_posts (owner_id, pet_id, body, moderation_status)
             VALUES (?, ?, ?, 'visible')"
        );
        $stmt->execute([$ownerId, $petId, $body]);
        return ['ok' => true, 'post_id' => (int) rrp_db()->lastInsertId()];
    } catch (Throwable $e) {
        error_log('rrp_forum_create_post: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not post. Import sql/006_v1_product.sql if forum tables are missing.'];
    }
}

/**
 * @return array{ok:bool, error?:string}
 */
function rrp_forum_vote(int $ownerId, int $postId, int $value): array
{
    if (!in_array($value, [-1, 1], true)) {
        return ['ok' => false, 'error' => 'Vote must be +1 or -1.'];
    }
    $post = rrp_forum_post_by_id($postId);
    if (!$post) {
        return ['ok' => false, 'error' => 'Post not found.'];
    }
    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO forum_post_votes (post_id, voter_owner_id, vote) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE vote = VALUES(vote)'
        );
        $stmt->execute([$postId, $ownerId, $value]);
        $likes = rrp_db()->prepare('SELECT COUNT(*) FROM forum_post_votes WHERE post_id = ? AND vote = 1');
        $likes->execute([$postId]);
        $dislikes = rrp_db()->prepare('SELECT COUNT(*) FROM forum_post_votes WHERE post_id = ? AND vote = -1');
        $dislikes->execute([$postId]);
        rrp_db()->prepare('UPDATE forum_posts SET like_count = ?, dislike_count = ? WHERE id = ?')
            ->execute([(int) $likes->fetchColumn(), (int) $dislikes->fetchColumn(), $postId]);
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not vote.'];
    }
}

/**
 * @return array{ok:bool, error?:string, report_id?:int}
 */
function rrp_forum_report(int $reporterId, string $targetType, int $targetId, string $reason, ?string $detail = null): array
{
    if (!in_array($targetType, ['post', 'comment', 'pet_page', 'owner'], true)) {
        return ['ok' => false, 'error' => 'Invalid target.'];
    }
    $reason = trim($reason);
    if ($reason === '' || mb_strlen($reason) > 40) {
        return ['ok' => false, 'error' => 'Reason required (max 40).'];
    }
    try {
        $stmt = rrp_db()->prepare(
            "INSERT INTO forum_reports (reporter_owner_id, target_type, target_id, reason, detail, status)
             VALUES (?, ?, ?, ?, ?, 'open')"
        );
        $stmt->execute([$reporterId, $targetType, $targetId, $reason, $detail]);
        return ['ok' => true, 'report_id' => (int) rrp_db()->lastInsertId()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not submit report.'];
    }
}

function rrp_forum_comments(int $postId): array
{
    try {
        $stmt = rrp_db()->prepare(
            "SELECT c.*, pets.name AS pet_name FROM forum_comments c
             JOIN pets ON pets.id = c.pet_id
             WHERE c.post_id = ? AND c.moderation_status = 'visible' ORDER BY c.id ASC"
        );
        $stmt->execute([$postId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return array{ok:bool, error?:string, comment_id?:int}
 */
function rrp_forum_add_comment(int $ownerId, int $petId, int $postId, string $body): array
{
    $pet = rrp_pet_by_id($petId, $ownerId);
    if (!$pet) {
        return ['ok' => false, 'error' => 'Pet not found.'];
    }
    $body = trim($body);
    if ($body === '' || mb_strlen($body) > 500) {
        return ['ok' => false, 'error' => 'Comment required (max 500).'];
    }
    try {
        $stmt = rrp_db()->prepare(
            "INSERT INTO forum_comments (post_id, owner_id, pet_id, body, moderation_status) VALUES (?, ?, ?, ?, 'visible')"
        );
        $stmt->execute([$postId, $ownerId, $petId, $body]);
        $cid = (int) rrp_db()->lastInsertId();
        rrp_db()->prepare('UPDATE forum_posts SET comment_count = comment_count + 1 WHERE id = ?')->execute([$postId]);
        return ['ok' => true, 'comment_id' => $cid];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not comment.'];
    }
}
