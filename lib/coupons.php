<?php
declare(strict_types=1);

function rrp_coupons_list(int $limit = 100): array
{
    $stmt = rrp_db()->prepare('SELECT * FROM coupons ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * @return array{ok:bool, coupon_id?:int, error?:string}
 */
function rrp_coupon_create(int $adminId, array $data): array
{
    $code = strtoupper(trim((string) ($data['code'] ?? '')));
    $description = trim((string) ($data['description'] ?? ''));
    $type = (string) ($data['discount_type'] ?? 'percent');
    $value = (float) ($data['discount_value'] ?? 0);
    $max = trim((string) ($data['max_redemptions'] ?? ''));
    $maxRedemptions = $max === '' ? null : (int) $max;

    if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,40}$/', $code)) {
        return ['ok' => false, 'error' => 'Code must be 3–40 chars: A–Z, 0–9, _ or -.'];
    }
    if (!in_array($type, ['percent', 'fixed', 'free_month'], true)) {
        return ['ok' => false, 'error' => 'Invalid discount type.'];
    }
    if ($type !== 'free_month' && $value <= 0) {
        return ['ok' => false, 'error' => 'Discount value must be greater than 0.'];
    }
    if ($type === 'percent' && $value > 100) {
        return ['ok' => false, 'error' => 'Percent cannot exceed 100.'];
    }

    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO coupons (code, description, discount_type, discount_value, max_redemptions, created_by_admin_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $code,
            $description !== '' ? $description : null,
            $type,
            $type === 'free_month' ? 0 : $value,
            $maxRedemptions,
            $adminId,
        ]);
        $id = (int) rrp_db()->lastInsertId();
        rrp_admin_log($adminId, 'coupon_create', 'coupons', $id, "code=$code");
        return ['ok' => true, 'coupon_id' => $id];
    } catch (Throwable $e) {
        if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
            return ['ok' => false, 'error' => 'That coupon code already exists.'];
        }
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not create coupon')];
    }
}

function rrp_coupon_set_status(int $adminId, int $couponId, string $status): array
{
    if (!in_array($status, ['active', 'disabled', 'exhausted'], true)) {
        return ['ok' => false, 'error' => 'Invalid status.'];
    }
    $stmt = rrp_db()->prepare('UPDATE coupons SET status = ? WHERE id = ?');
    $stmt->execute([$status, $couponId]);
    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Coupon not found.'];
    }
    rrp_admin_log($adminId, 'coupon_status', 'coupons', $couponId, "status=$status");
    return ['ok' => true];
}

/**
 * Change a coupon. The code itself stays the same (past orders refer to it); everything else can be edited.
 * @return array{ok:bool, error?:string}
 */
function rrp_coupon_update(int $adminId, int $couponId, array $data): array
{
    $description = trim((string) ($data['description'] ?? ''));
    $type = (string) ($data['discount_type'] ?? 'percent');
    $value = (float) ($data['discount_value'] ?? 0);
    $max = trim((string) ($data['max_redemptions'] ?? ''));
    $until = trim((string) ($data['valid_until'] ?? ''));
    if (!in_array($type, ['percent', 'fixed', 'free_month'], true)) {
        return ['ok' => false, 'error' => 'Invalid discount type.'];
    }
    if ($type !== 'free_month' && $value <= 0) {
        return ['ok' => false, 'error' => 'Discount value must be greater than 0.'];
    }
    if ($type === 'percent' && $value > 100) {
        return ['ok' => false, 'error' => 'Percent cannot exceed 100.'];
    }
    $validUntil = null;
    if ($until !== '') {
        $t = strtotime($until . ' 23:59:59 UTC');
        if ($t === false) {
            return ['ok' => false, 'error' => 'The end date does not look right.'];
        }
        $validUntil = gmdate('Y-m-d H:i:s', $t);
    }
    try {
        $st = rrp_db()->prepare('SELECT redemption_count, status FROM coupons WHERE id = ?');
        $st->execute([$couponId]);
        $cur = $st->fetch();
        if (!$cur) {
            return ['ok' => false, 'error' => 'Coupon not found.'];
        }
        $maxRed = $max === '' ? null : max(1, (int) $max);
        // A coupon that ran out comes back to life when the limit is raised, and runs out when it is lowered to what was used.
        $status = (string) $cur['status'];
        if ($status === 'exhausted' && ($maxRed === null || $maxRed > (int) $cur['redemption_count'])) {
            $status = 'active';
        } elseif ($status === 'active' && $maxRed !== null && (int) $cur['redemption_count'] >= $maxRed) {
            $status = 'exhausted';
        }
        rrp_db()->prepare('UPDATE coupons SET description = ?, discount_type = ?, discount_value = ?, max_redemptions = ?, valid_until = ?, status = ? WHERE id = ?')
            ->execute([$description !== '' ? $description : null, $type, $type === 'free_month' ? 0 : $value, $maxRed, $validUntil, $status, $couponId]);
        rrp_admin_log($adminId, 'coupon_update', 'coupons', $couponId, "type=$type value=$value max=" . ($maxRed ?? 'none'));
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not update the coupon')];
    }
}

/** Delete a coupon for good. Invoices and orders keep the code they used as text. @return array{ok:bool, error?:string} */
function rrp_coupon_delete(int $adminId, int $couponId): array
{
    try {
        $st = rrp_db()->prepare('SELECT code FROM coupons WHERE id = ?');
        $st->execute([$couponId]);
        $code = $st->fetchColumn();
        if ($code === false) {
            return ['ok' => false, 'error' => 'Coupon not found.'];
        }
        rrp_db()->prepare('DELETE FROM coupons WHERE id = ?')->execute([$couponId]);
        rrp_admin_log($adminId, 'coupon_delete', 'coupons', $couponId, 'code=' . $code);
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not delete the coupon')];
    }
}
