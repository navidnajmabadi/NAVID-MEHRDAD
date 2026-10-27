<?php
declare(strict_types=1);

/**
 * Social boards (brief §E): pets post (≤ 250 chars + 1 photo), like / dislike, follow pets,
 * comment, report. Every post and comment passes moderation (lib/social/moderation.php).
 */

const RRP_FORUM_BODY_MAX = 250;
const RRP_FORUM_COMMENT_MAX = 500;

/**
 * @param string $scope 'all' | 'following'
 * @return list<array> posts with pet info, my_vote, following flag
 */
function rrp_forum_feed(int $limit = 40, ?string $species = null, ?int $viewerId = null, string $scope = 'all', int $beforeId = 0, ?int $petId = null): array
{
    $limit = max(1, min(100, $limit));
    $viewer = (int) ($viewerId ?? 0);
    $sql = "SELECT p.*, pets.name AS pet_name, pets.type AS pet_type, pets.species_key, pets.public_id AS pet_public_id,
                   pets.image_path AS pet_image,
                   (SELECT v.vote FROM forum_post_votes v WHERE v.post_id = p.id AND v.voter_owner_id = ?) AS my_vote,
                   EXISTS (SELECT 1 FROM forum_follows f WHERE f.follower_owner_id = ? AND f.following_pet_id = p.pet_id) AS following
            FROM forum_posts p
            JOIN pets ON pets.id = p.pet_id AND pets.is_active = 1
            JOIN owners ON owners.id = p.owner_id AND owners.status = 'active'
            WHERE p.moderation_status = 'visible'";
    $args = [$viewer, $viewer];
    if ($species) {
        $sql .= ' AND (pets.species_key = ? OR pets.type = ?)';
        $args[] = $species;
        $args[] = $species;
    }
    if ($scope === 'following') {
        $sql .= ' AND (p.pet_id IN (SELECT following_pet_id FROM forum_follows WHERE follower_owner_id = ?) OR p.owner_id = ?)';
        $args[] = $viewer;
        $args[] = $viewer;
    }
    if ($petId) {
        $sql .= ' AND p.pet_id = ?';
        $args[] = $petId;
    }
    if ($beforeId > 0) {
        $sql .= ' AND p.id < ?';
        $args[] = $beforeId;
    }
    $sql .= " ORDER BY p.id DESC LIMIT {$limit}";
    try {
        $stmt = rrp_db()->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('rrp_forum_feed: ' . $e->getMessage());
        return [];
    }
}

function rrp_forum_post_by_id(int $id, ?int $viewerId = null): ?array
{
    try {
        $stmt = rrp_db()->prepare(
            'SELECT p.*, pets.name AS pet_name, pets.type AS pet_type, pets.public_id AS pet_public_id, pets.image_path AS pet_image,
                    (SELECT v.vote FROM forum_post_votes v WHERE v.post_id = p.id AND v.voter_owner_id = ?) AS my_vote
             FROM forum_posts p JOIN pets ON pets.id = p.pet_id WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([(int) ($viewerId ?? 0), $id]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Create a post as one of the owner's pets, with optional photo ($_FILES entry). Moderated before it shows.
 * @return array{ok:bool, error?:string, post_id?:int, removed?:bool, reason?:string, suspended?:bool}
 */
function rrp_forum_create_post(int $ownerId, int $petId, string $body, ?array $photo = null): array
{
    $pet = rrp_pet_by_id($petId, $ownerId);
    if (!$pet || (int) ($pet['is_active'] ?? 1) !== 1) {
        return ['ok' => false, 'error' => 'Pet not found.'];
    }
    $body = trim(preg_replace('/[ \t]+/u', ' ', $body) ?? $body);
    $hasPhoto = $photo && ($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if (($body === '' && !$hasPhoto) || mb_strlen($body) > RRP_FORUM_BODY_MAX) {
        return ['ok' => false, 'error' => 'Write something (max ' . RRP_FORUM_BODY_MAX . ' characters) or add a photo.'];
    }
    $img = null;
    if ($hasPhoto) {
        $img = rrp_social_compress_photo($photo);
        if (empty($img['ok'])) {
            return ['ok' => false, 'error' => $img['error'] ?? 'Photo failed.'];
        }
    }
    $check = rrp_mod_check($body, $img['jpeg'] ?? null);
    try {
        $stmt = rrp_db()->prepare(
            "INSERT INTO forum_posts (owner_id, pet_id, body, image_path, image_w, image_h, moderation_status) VALUES (?, ?, ?, ?, ?, ?, 'visible')"
        );
        $stmt->execute([$ownerId, $petId, $body, $img['path'] ?? null, $img['w'] ?? null, $img['h'] ?? null]);
        $id = (int) rrp_db()->lastInsertId();
    } catch (Throwable $e) {
        error_log('rrp_forum_create_post: ' . $e->getMessage());
        rrp_social_delete_photo($img['path'] ?? null);
        return ['ok' => false, 'error' => 'Could not post right now.'];
    }
    if (!$check['allow']) {
        // Keep the row for the per-user log and admin review; the photo file is removed.
        rrp_social_delete_photo($img['path'] ?? null);
        $m = rrp_mod_remove('post', $id, $ownerId, (string) $check['reason'], $check['source'], $check['categories'], $body);
        return ['ok' => false, 'removed' => true, 'post_id' => $id, 'reason' => $check['reason'], 'suspended' => $m['suspended'],
            'error' => rrp_mod_owner_message((string) $check['reason'], $m)];
    }
    $coins = rrp_coin_award($ownerId, 'post', 'Post on the boards', 'forum_post', $id, $petId);
    return ['ok' => true, 'post_id' => $id, 'coins' => $coins];
}

/** value: 1 like, -1 dislike, 0 clear. Same value twice clears it (toggle). */
function rrp_forum_vote(int $ownerId, int $postId, int $value): array
{
    if (!in_array($value, [-1, 0, 1], true)) {
        return ['ok' => false, 'error' => 'Invalid vote.'];
    }
    try {
        $cur = rrp_db()->prepare('SELECT vote FROM forum_post_votes WHERE post_id = ? AND voter_owner_id = ?');
        $cur->execute([$postId, $ownerId]);
        $now = $cur->fetchColumn();
        if ($value === 0 || ($now !== false && (int) $now === $value)) {
            rrp_db()->prepare('DELETE FROM forum_post_votes WHERE post_id = ? AND voter_owner_id = ?')->execute([$postId, $ownerId]);
            $value = 0;
        } else {
            rrp_db()->prepare(
                'INSERT INTO forum_post_votes (post_id, voter_owner_id, vote) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE vote = VALUES(vote)'
            )->execute([$postId, $ownerId, $value]);
        }
        rrp_db()->prepare(
            'UPDATE forum_posts SET like_count = (SELECT COUNT(*) FROM forum_post_votes WHERE post_id = ? AND vote = 1),
                                    dislike_count = (SELECT COUNT(*) FROM forum_post_votes WHERE post_id = ? AND vote = -1) WHERE id = ?'
        )->execute([$postId, $postId, $postId]);
        $c = rrp_db()->prepare('SELECT like_count, dislike_count FROM forum_posts WHERE id = ?');
        $c->execute([$postId]);
        $row = $c->fetch() ?: ['like_count' => 0, 'dislike_count' => 0];
        return ['ok' => true, 'my_vote' => $value, 'likes' => (int) $row['like_count'], 'dislikes' => (int) $row['dislike_count']];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not vote.'];
    }
}

/** @return array{ok:bool, error?:string, hidden?:bool} */
function rrp_forum_report(int $reporterId, string $targetType, int $targetId, string $reason, ?string $detail = null): array
{
    if (!in_array($targetType, ['post', 'comment'], true)) {
        return ['ok' => false, 'error' => 'Invalid target.'];
    }
    $reason = in_array($reason, ['porn', 'drugs', 'fraud', 'spam', 'abuse', 'animal_harm', 'other'], true) ? $reason : 'other';
    try {
        $dup = rrp_db()->prepare('SELECT 1 FROM forum_reports WHERE reporter_owner_id = ? AND target_type = ? AND target_id = ? LIMIT 1');
        $dup->execute([$reporterId, $targetType, $targetId]);
        if (!$dup->fetchColumn()) {
            rrp_db()->prepare(
                "INSERT INTO forum_reports (reporter_owner_id, target_type, target_id, reason, detail, status) VALUES (?, ?, ?, ?, ?, 'open')"
            )->execute([$reporterId, $targetType, $targetId, $reason, $detail ? mb_substr($detail, 0, 1000) : null]);
        }
        return ['ok' => true, 'hidden' => rrp_mod_after_report($targetType, $targetId)];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not report.'];
    }
}

function rrp_forum_comments(int $postId): array
{
    try {
        $stmt = rrp_db()->prepare(
            "SELECT c.*, pets.name AS pet_name, pets.public_id AS pet_public_id, pets.image_path AS pet_image FROM forum_comments c
             JOIN pets ON pets.id = c.pet_id
             WHERE c.post_id = ? AND c.moderation_status = 'visible' ORDER BY c.id ASC"
        );
        $stmt->execute([$postId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** @return array{ok:bool, error?:string, comment_id?:int, removed?:bool} */
function rrp_forum_add_comment(int $ownerId, int $petId, int $postId, string $body): array
{
    $pet = rrp_pet_by_id($petId, $ownerId);
    $body = trim($body);
    if (!$pet || $body === '' || mb_strlen($body) > RRP_FORUM_COMMENT_MAX) {
        return ['ok' => false, 'error' => 'Comment required (max ' . RRP_FORUM_COMMENT_MAX . ' characters).'];
    }
    $post = rrp_forum_post_by_id($postId);
    if (!$post || $post['moderation_status'] !== 'visible') {
        return ['ok' => false, 'error' => 'Post not found.'];
    }
    $check = rrp_mod_check($body);
    try {
        rrp_db()->prepare("INSERT INTO forum_comments (post_id, owner_id, pet_id, body, moderation_status) VALUES (?, ?, ?, ?, 'visible')")
            ->execute([$postId, $ownerId, $petId, $body]);
        $id = (int) rrp_db()->lastInsertId();
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not comment.'];
    }
    if (!$check['allow']) {
        $m = rrp_mod_remove('comment', $id, $ownerId, (string) $check['reason'], $check['source'], $check['categories'], $body);
        return ['ok' => false, 'removed' => true, 'suspended' => $m['suspended'],
            'error' => rrp_mod_owner_message((string) $check['reason'], $m)];
    }
    rrp_db()->prepare('UPDATE forum_posts SET comment_count = comment_count + 1 WHERE id = ?')->execute([$postId]);
    if ((int) $post['owner_id'] !== $ownerId) {
        rrp_notify_owner_inbox((int) $post['owner_id'], 'forum_comment', $pet['name'] . ' commented on ' . $post['pet_name'] . "'s post", mb_substr($body, 0, 140));
    }
    $coins = rrp_coin_award($ownerId, 'comment', 'Comment on the boards', 'forum_comment', $id, $petId);
    return ['ok' => true, 'comment_id' => $id, 'coins' => $coins];
}

/** Author deletes their own post. */
function rrp_forum_delete_own(int $ownerId, int $postId): array
{
    $post = rrp_forum_post_by_id($postId);
    if (!$post || (int) $post['owner_id'] !== $ownerId) {
        return ['ok' => false, 'error' => 'Not your post.'];
    }
    rrp_db()->prepare("UPDATE forum_posts SET moderation_status = 'removed', removed_reason = 'Deleted by author', removed_at = UTC_TIMESTAMP() WHERE id = ?")
        ->execute([$postId]);
    rrp_social_delete_photo($post['image_path'] ?? null);
    return ['ok' => true];
}

// -----------------------------------------------------------------------------
// Follows + pet profiles
// -----------------------------------------------------------------------------

function rrp_forum_follow(int $ownerId, int $petId, bool $on): array
{
    $own = rrp_pet_by_id($petId, $ownerId);
    if ($own) {
        return ['ok' => false, 'error' => 'That is your own pet 😊'];
    }
    if ($on) {
        rrp_db()->prepare('INSERT IGNORE INTO forum_follows (follower_owner_id, following_pet_id) VALUES (?, ?)')->execute([$ownerId, $petId]);
    } else {
        rrp_db()->prepare('DELETE FROM forum_follows WHERE follower_owner_id = ? AND following_pet_id = ?')->execute([$ownerId, $petId]);
    }
    return ['ok' => true, 'following' => $on, 'followers' => rrp_forum_follower_count($petId)];
}

function rrp_forum_follower_count(int $petId): int
{
    $st = rrp_db()->prepare('SELECT COUNT(*) FROM forum_follows WHERE following_pet_id = ?');
    $st->execute([$petId]);
    return (int) $st->fetchColumn();
}

/** Public pet profile for the boards (no owner PII: owner nickname only). */
function rrp_forum_pet_profile(string $publicId, int $viewerId): ?array
{
    $st = rrp_db()->prepare(
        "SELECT p.id, p.public_id, p.name, p.type, p.breed, p.image_path, p.owner_id, pp.bio, o.nickname, o.name AS owner_name
         FROM pets p JOIN owners o ON o.id = p.owner_id AND o.status = 'active'
         LEFT JOIN forum_pet_pages pp ON pp.pet_id = p.id
         WHERE p.public_id = ? AND p.is_active = 1 LIMIT 1"
    );
    $st->execute([$publicId]);
    $p = $st->fetch();
    if (!$p) {
        return null;
    }
    $f = rrp_db()->prepare('SELECT 1 FROM forum_follows WHERE follower_owner_id = ? AND following_pet_id = ?');
    $f->execute([$viewerId, (int) $p['id']]);
    $n = rrp_db()->prepare("SELECT COUNT(*) FROM forum_posts WHERE pet_id = ? AND moderation_status = 'visible'");
    $n->execute([(int) $p['id']]);
    return [
        'id' => (int) $p['id'],
        'public_id' => $p['public_id'],
        'name' => $p['name'],
        'kind' => rrp_pet_type_label($p),
        'breed' => $p['breed'],
        'avatar' => $p['image_path'] ? rrp_asset((string) $p['image_path']) : null,
        'initial' => mb_strtoupper(mb_substr((string) $p['name'], 0, 1)),
        'bio' => $p['bio'],
        'owner_nickname' => $p['nickname'] ?: $p['owner_name'],
        'is_mine' => (int) $p['owner_id'] === $viewerId,
        'following' => (bool) $f->fetchColumn(),
        'followers' => rrp_forum_follower_count((int) $p['id']),
        'posts' => (int) $n->fetchColumn(),
    ];
}

/** Shape a post for the client (no owner PII). */
function rrp_forum_present(array $p): array
{
    if (!$p) {
        return [];
    }
    return [
        'id' => (int) $p['id'],
        'body' => (string) $p['body'],
        'image' => !empty($p['image_path']) ? rrp_asset((string) $p['image_path']) : null,
        'image_w' => isset($p['image_w']) ? (int) $p['image_w'] : null,
        'image_h' => isset($p['image_h']) ? (int) $p['image_h'] : null,
        'likes' => (int) ($p['like_count'] ?? 0),
        'dislikes' => (int) ($p['dislike_count'] ?? 0),
        'comments' => (int) ($p['comment_count'] ?? 0),
        'my_vote' => (int) ($p['my_vote'] ?? 0),
        'following' => !empty($p['following']),
        'is_mine' => false,
        'pet' => [
            'id' => (int) $p['pet_id'],
            'public_id' => (string) ($p['pet_public_id'] ?? ''),
            'name' => (string) ($p['pet_name'] ?? 'Pet'),
            'kind' => ucfirst((string) ($p['pet_type'] ?? '')),
            'avatar' => !empty($p['pet_image']) ? rrp_asset((string) $p['pet_image']) : null,
            'initial' => mb_strtoupper(mb_substr((string) ($p['pet_name'] ?? 'P'), 0, 1)),
        ],
        'owner_id' => (int) $p['owner_id'],
        'created_at' => gmdate(DATE_ATOM, strtotime((string) $p['created_at'] . ' UTC') ?: time()),
    ];
}
