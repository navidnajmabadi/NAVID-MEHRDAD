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
