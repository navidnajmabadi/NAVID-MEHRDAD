<?php
declare(strict_types=1);

const RRP_MAX_PETS = 5;

const RRP_BREED_SUGGESTIONS = [
    'dog' => ['Labrador Retriever', 'German Shepherd', 'Golden Retriever', 'French Bulldog', 'Poodle', 'Beagle', 'Dachshund', 'Mixed'],
    'cat' => ['Domestic Shorthair', 'Domestic Longhair', 'Siamese', 'Persian', 'Maine Coon', 'British Shorthair', 'Ragdoll', 'Mixed'],
    'rabbit' => ['Holland Lop', 'Netherland Dwarf', 'Rex', 'Lionhead', 'Flemish Giant', 'Mini Lop', 'Mixed'],
    'custom' => [],
];

function rrp_pet_count(int $ownerId): int
{
    $stmt = rrp_db()->prepare('SELECT COUNT(*) FROM pets WHERE owner_id = ?');
    $stmt->execute([$ownerId]);
    return (int) $stmt->fetchColumn();
}

function rrp_pets_by_owner(int $ownerId): array
{
    $stmt = rrp_db()->prepare('SELECT * FROM pets WHERE owner_id = ? ORDER BY created_at DESC');
    $stmt->execute([$ownerId]);
    return $stmt->fetchAll();
}

function rrp_pet_by_public_id(string $publicId): ?array
{
    $stmt = rrp_db()->prepare('SELECT * FROM pets WHERE public_id = ? LIMIT 1');
    $stmt->execute([$publicId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function rrp_pet_by_id(int $id, ?int $ownerId = null): ?array
{
    if ($ownerId !== null) {
        $stmt = rrp_db()->prepare('SELECT * FROM pets WHERE id = ? AND owner_id = ? LIMIT 1');
        $stmt->execute([$id, $ownerId]);
    } else {
        $stmt = rrp_db()->prepare('SELECT * FROM pets WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
    }
    $row = $stmt->fetch();
    return $row ?: null;
}

function rrp_pet_type_label(array $pet): string
{
    if ($pet['type'] === 'custom') {
        return $pet['custom_type'] ?: 'Pet';
    }
    return ucfirst((string) $pet['type']);
}

function rrp_save_pet_image(?array $file, ?string $existing = null): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $existing;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }
    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new RuntimeException('Image must be under 3 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($map[$mime])) {
        throw new RuntimeException('Use a JPG, PNG, WebP, or GIF image.');
    }

    $dir = dirname(__DIR__) . '/uploads/pets';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = rrp_public_id(12) . '.' . $map[$mime];
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save image.');
    }

    if ($existing) {
        $old = dirname(__DIR__) . '/' . ltrim($existing, '/');
        if (is_file($old) && str_starts_with(realpath($old) ?: '', realpath($dir) ?: '___')) {
            @unlink($old);
        }
    }

    return 'uploads/pets/' . $name;
}

function rrp_create_pet(int $ownerId, array $data, ?array $file = null): array
{
    if (rrp_pet_count($ownerId) >= RRP_MAX_PETS) {
        return ['ok' => false, 'error' => 'You can register up to ' . RRP_MAX_PETS . ' pets.'];
    }

    $parsed = rrp_parse_pet_input($data);
    if (!$parsed['ok']) {
        return $parsed;
    }

    try {
        $imagePath = rrp_save_pet_image($file);
    } catch (RuntimeException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    $publicId = rrp_public_id(8);
    $p = $parsed['data'];
    $stmt = rrp_db()->prepare(
        'INSERT INTO pets (
            owner_id, public_id, name, type, custom_type, breed, color, magic_word,
            identifying_marks, image_path, medical_vaccines, medical_next_due,
            medical_pills, medical_conditions
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $ownerId,
        $publicId,
        $p['name'],
        $p['type'],
        $p['custom_type'],
        $p['breed'],
        $p['color'],
        $p['magic_word'],
        $p['identifying_marks'],
        $imagePath,
        $p['medical_vaccines'],
        $p['medical_next_due'],
        $p['medical_pills'],
        $p['medical_conditions'],
    ]);

    return ['ok' => true, 'pet_id' => (int) rrp_db()->lastInsertId(), 'public_id' => $publicId];
}

function rrp_update_pet(int $ownerId, int $petId, array $data, ?array $file = null): array
{
    $pet = rrp_pet_by_id($petId, $ownerId);
    if (!$pet) {
        return ['ok' => false, 'error' => 'Pet not found.'];
    }

    $parsed = rrp_parse_pet_input($data);
    if (!$parsed['ok']) {
        return $parsed;
    }

    try {
        $imagePath = rrp_save_pet_image($file, $pet['image_path']);
    } catch (RuntimeException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    $p = $parsed['data'];
    $stmt = rrp_db()->prepare(
        'UPDATE pets SET
            name = ?, type = ?, custom_type = ?, breed = ?, color = ?, magic_word = ?,
            identifying_marks = ?, image_path = ?, medical_vaccines = ?, medical_next_due = ?,
            medical_pills = ?, medical_conditions = ?
         WHERE id = ? AND owner_id = ?'
    );
    $stmt->execute([
        $p['name'],
        $p['type'],
        $p['custom_type'],
        $p['breed'],
        $p['color'],
        $p['magic_word'],
        $p['identifying_marks'],
        $imagePath,
        $p['medical_vaccines'],
        $p['medical_next_due'],
        $p['medical_pills'],
        $p['medical_conditions'],
        $petId,
        $ownerId,
    ]);

    return ['ok' => true];
}

function rrp_delete_pet(int $ownerId, int $petId): array
{
    $pet = rrp_pet_by_id($petId, $ownerId);
    if (!$pet) {
        return ['ok' => false, 'error' => 'Pet not found.'];
    }
    $stmt = rrp_db()->prepare('DELETE FROM pets WHERE id = ? AND owner_id = ?');
    $stmt->execute([$petId, $ownerId]);
    if (!empty($pet['image_path'])) {
        $path = dirname(__DIR__) . '/' . ltrim($pet['image_path'], '/');
        if (is_file($path)) {
            @unlink($path);
        }
    }
    return ['ok' => true];
}

function rrp_parse_pet_input(array $data): array
{
    $name = trim((string) ($data['name'] ?? ''));
    $type = (string) ($data['type'] ?? '');
    $customType = trim((string) ($data['custom_type'] ?? ''));
    $breed = trim((string) ($data['breed'] ?? ''));
    $color = trim((string) ($data['color'] ?? ''));
    $magic = trim((string) ($data['magic_word'] ?? ''));
    $marks = trim((string) ($data['identifying_marks'] ?? ''));
    $vaccines = trim((string) ($data['medical_vaccines'] ?? ''));
    $nextDue = trim((string) ($data['medical_next_due'] ?? ''));
    $pills = trim((string) ($data['medical_pills'] ?? ''));
    $conditions = trim((string) ($data['medical_conditions'] ?? ''));

    if ($name === '') {
        return ['ok' => false, 'error' => 'Pet name is required.'];
    }
    if (!in_array($type, ['dog', 'cat', 'rabbit', 'custom'], true)) {
        return ['ok' => false, 'error' => 'Choose a pet type.'];
    }
    if ($type === 'custom' && $customType === '') {
        return ['ok' => false, 'error' => 'Describe the custom pet type.'];
    }
    if ($nextDue !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextDue)) {
        return ['ok' => false, 'error' => 'Next vaccine due date must be YYYY-MM-DD.'];
    }

    return [
        'ok' => true,
        'data' => [
            'name' => $name,
            'type' => $type,
            'custom_type' => $type === 'custom' ? $customType : null,
            'breed' => $breed !== '' ? $breed : null,
            'color' => $color !== '' ? $color : null,
            'magic_word' => $magic !== '' ? $magic : null,
            'identifying_marks' => $marks !== '' ? $marks : null,
            'medical_vaccines' => $vaccines !== '' ? $vaccines : null,
            'medical_next_due' => $nextDue !== '' ? $nextDue : null,
            'medical_pills' => $pills !== '' ? $pills : null,
            'medical_conditions' => $conditions !== '' ? $conditions : null,
        ],
    ];
}
