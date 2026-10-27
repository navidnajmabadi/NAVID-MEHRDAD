<?php
declare(strict_types=1);

/**
 * Files sent to SIPO (chat attach or Health page): one vision call classifies
 * and extracts, then the data is saved and the file deleted (Navid 2026-10-28).
 * Only a pet photo is kept — as the profile picture used on Lost posters.
 */

const RRP_UPLOAD_MAX_BYTES = 10 * 1024 * 1024;
const RRP_UPLOAD_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

/**
 * @param array $file one $_FILES entry
 * @return array{ok:bool, error?:string, kind?:string, body?:string, saved?:array}
 */
function rrp_care_handle_upload(array $owner, array $thread, array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return ['ok' => false, 'error' => ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'File is too big (max 10 MB).' : 'Upload failed. Try again.'];
    }
    $tmp = (string) $file['tmp_name'];
    try {
        if ((int) $file['size'] > RRP_UPLOAD_MAX_BYTES) {
            return ['ok' => false, 'error' => 'File is too big (max 10 MB).'];
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if (!isset(RRP_UPLOAD_MIME[$mime])) {
            return ['ok' => false, 'error' => 'Send a photo (JPG, PNG, WebP) or a PDF. On iPhone, HEIC photos can be shared as "Most Compatible".'];
        }
        $pet = !empty($thread['pet_id']) ? rrp_pet_by_id((int) $thread['pet_id'], (int) $owner['id']) : null;
        if (!$pet) {
            return ['ok' => false, 'error' => 'Finish adding your pet first, then send files.'];
        }

        $client = RrpOpenAiClient::product();
        if (!$client->configured()) {
            return ['ok' => false, 'error' => 'SIPO can read files once the OpenAI key is set in the Hostinger config.'];
        }
        $part = rrp_care_file_part($tmp, $mime, (string) ($file['name'] ?? 'file'));
        if ($part === null) {
            return ['ok' => false, 'error' => 'Could not read that image.'];
        }
        $res = $client->json([
            ['role' => 'system', 'content' => rrp_care_extract_prompt($pet)],
            ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Classify and extract this file.'], $part]],
        ], $client->modelFor('food_vision'));
        if (empty($res['ok'])) {
            error_log('upload extract: ' . ($res['error'] ?? '?'));
            return ['ok' => false, 'error' => 'SIPO could not read that file. Try a clearer photo or the PDF.'];
        }
        rrp_sipo_track_tokens((int) $owner['id'], (int) ($res['usage']['total_tokens'] ?? 0));
        return rrp_care_route_extract($owner, $pet, $res['data'], $tmp, $mime);
    } finally {
        // Files are not kept (except a pet photo, which was copied before this point).
        if (is_file($tmp)) {
            @unlink($tmp);
        }
    }
}

/** Image → downscaled JPEG data URL; PDF → file part. */
function rrp_care_file_part(string $path, string $mime, string $name): ?array
{
    if ($mime === 'application/pdf') {
        return ['type' => 'file', 'file' => [
            'filename' => preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'report.pdf',
            'file_data' => 'data:application/pdf;base64,' . base64_encode((string) file_get_contents($path)),
        ]];
    }
    $jpeg = rrp_care_downscale($path, 2000, 85);
    if ($jpeg === null) {
        return null;
    }
    return ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . base64_encode($jpeg), 'detail' => 'high']];
}

/** Resize so the long side ≤ $max px; returns JPEG bytes (keeps orientation). */
function rrp_care_downscale(string $path, int $max, int $quality): ?string
{
    if (!function_exists('imagecreatefromstring')) {
        return (string) file_get_contents($path);
    }
    $src = @imagecreatefromstring((string) file_get_contents($path));
    if (!$src) {
        return null;
    }
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $o = (int) ($exif['Orientation'] ?? 1);
        $src = match ($o) {
            3 => imagerotate($src, 180, 0),
            6 => imagerotate($src, -90, 0),
            8 => imagerotate($src, 90, 0),
            default => $src,
        };
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, $max / max($w, $h));
    $dst = $src;
    if ($scale < 1) {
        $dst = imagecreatetruecolor((int) round($w * $scale), (int) round($h * $scale));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
    }
    ob_start();
    imagejpeg($dst, null, $quality);
    return (string) ob_get_clean();
}

function rrp_care_extract_prompt(array $pet): string
{
    $kind = rrp_pet_type_label($pet);
    return <<<TXT
You read files that a pet owner sends about their {$kind} "{$pet['name']}". Return ONLY JSON:
{
 "kind": "lab_report" | "food_label" | "prescription" | "pet_photo" | "other",
 "summary": "one short sentence describing the file",
 "lab": {"examined_on": "YYYY-MM-DD or null", "clinic": "string or null", "exam_type": "blood|urine|other",
         "results": [{"key": "short id e.g. alt", "label": "as printed", "value": number or null,
                      "value_text": "if not numeric", "unit": "as printed", "ref_low": number or null, "ref_high": number or null}]},
 "food": {"name": "brand + product", "kind": "dry|wet|treat|raw|other", "kcal_per_kg": number or null,
          "kcal_per_cup": number or null, "grams_per_cup": number or null, "package_grams": number or null,
          "ingredients": "first ~15 ingredients as printed", "species": "dog|cat|other|unknown"},
 "prescription": {"medicine": "", "dose_as_written": "exactly as printed", "frequency_as_written": "",
                  "duration_as_written": "", "condition": "", "vet": ""}
}
Rules: copy numbers exactly as printed; never guess a value or range that is not on the page;
convert "ME (calculated): 3,650 kcal/kg" style numbers to plain numbers; leave sections you don't need as null.
A photo of an animal with no document is "pet_photo".
TXT;
}

/**
 * Save extracted data and build the chat line that stands in for the file.
 * @return array{ok:bool, kind:string, body:string, saved:array}
 */
function rrp_care_route_extract(array $owner, array $pet, array $x, string $tmp, string $mime): array
{
    $kind = in_array($x['kind'] ?? '', ['lab_report', 'food_label', 'prescription', 'pet_photo', 'other'], true) ? $x['kind'] : 'other';
    $saved = [];

    if ($kind === 'lab_report' && !empty($x['lab']['results'])) {
        $r = rrp_labs_save($owner, $pet, (array) $x['lab'] + ['summary' => $x['summary'] ?? null]);
        $saved = $r;
        if (!empty($r['ok'])) {
            $c = $r['counts'];
            $lines = ["📎 Lab report from {$r['date']}: " . array_sum($c) . ' results — ' . $c['normal'] . ' in range, ' . ($c['low'] + $c['high']) . ' outside.'];
            foreach (array_slice($r['abnormal'], 0, 10) as $a) {
                $lines[] = "• {$a['label']} {$a['value']} {$a['unit']} ({$a['flag']}; ref {$a['ref_low']}–{$a['ref_high']})";
            }
            $lines[] = '(File not kept — the numbers are saved in Health.)';
            return ['ok' => true, 'kind' => $kind, 'body' => implode("\n", $lines), 'saved' => $saved];
        }
    }

    if ($kind === 'food_label' && !empty($x['food']['name'])) {
        $f = (array) $x['food'];
        $r = rrp_food_save($owner, $pet, $f + ['just_opened' => false]);
        $saved = $r;
        if (!empty($r['ok'])) {
            $kcal = !empty($f['kcal_per_kg']) ? $f['kcal_per_kg'] . ' kcal/kg' : (!empty($f['kcal_per_cup']) ? $f['kcal_per_cup'] . ' kcal/cup' : 'calories not shown');
            $body = "📎 Food label: {$f['name']} ({$kcal})."
                . ($r['grams_per_day'] ? " About {$r['grams_per_day']} g a day would meet {$pet['name']}'s daily need on its own." : '')
                . ($r['warnings'] ? ' ⚠ ' . implode(' ', $r['warnings']) : '')
                . (!empty($f['species']) && $f['species'] !== 'unknown' && $f['species'] !== ($pet['type'] ?? '') ? " Note: the label says it's for {$f['species']}s." : '');
            return ['ok' => true, 'kind' => $kind, 'body' => $body, 'saved' => $saved];
        }
    }

    if ($kind === 'prescription' && !empty($x['prescription']['medicine'])) {
        $p = (array) $x['prescription'];
        $body = "📎 Prescription: {$p['medicine']} — as written: \"" . trim(($p['dose_as_written'] ?? '') . ' ' . ($p['frequency_as_written'] ?? '') . ' ' . ($p['duration_as_written'] ?? '')) . '"'
            . (!empty($p['condition']) ? " for {$p['condition']}" : '') . (!empty($p['vet']) ? " (vet: {$p['vet']})" : '')
            . '. Please set up the treatment and reminders with me — check the times with me first.';
        return ['ok' => true, 'kind' => $kind, 'body' => $body, 'saved' => $p];
    }

    if ($kind === 'pet_photo' && $mime !== 'application/pdf') {
        $path = rrp_care_store_pet_photo($tmp, $pet);
        if ($path) {
            return ['ok' => true, 'kind' => $kind, 'body' => "📷 New photo of {$pet['name']} — saved as the profile picture.", 'saved' => ['image_path' => $path]];
        }
    }

    $summary = trim((string) ($x['summary'] ?? 'a file'));
    return ['ok' => true, 'kind' => 'other', 'body' => "📎 I sent a file: {$summary} (not kept).", 'saved' => []];
}

/** Profile photo: square-ish JPEG, long side 1200 px, replaces the old one. */
function rrp_care_store_pet_photo(string $tmp, array $pet): ?string
{
    $jpeg = rrp_care_downscale($tmp, 1200, 84);
    if ($jpeg === null) {
        return null;
    }
    $dir = dirname(__DIR__, 2) . '/uploads/pets';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $name = rrp_public_id(12) . '.jpg';
    if (@file_put_contents($dir . '/' . $name, $jpeg) === false) {
        return null;
    }
    $rel = 'uploads/pets/' . $name;
    if (!empty($pet['image_path'])) {
        $old = dirname(__DIR__, 2) . '/' . ltrim((string) $pet['image_path'], '/');
        if (is_file($old) && str_starts_with(realpath($old) ?: '', realpath($dir) ?: '___')) {
            @unlink($old);
        }
    }
    rrp_db()->prepare('UPDATE pets SET image_path = ? WHERE id = ?')->execute([$rel, (int) $pet['id']]);
    return $rel;
}
