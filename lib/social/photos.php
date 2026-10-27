<?php
declare(strict_types=1);

/**
 * One photo per post (brief §E): auto-compressed so it stays small without looking bad.
 * Long side ≤ 1600 px, JPEG quality stepped down from 84 until ≤ 350 KB. Re-encoding drops
 * EXIF, so GPS and camera data never leave the owner's phone.
 */

const RRP_SOCIAL_MAX_UPLOAD = 10 * 1024 * 1024; // matches .user.ini (12M) with headroom; the app shrinks photos first
const RRP_SOCIAL_MAX_SIDE = 1600;
const RRP_SOCIAL_TARGET_BYTES = 350 * 1024;

/**
 * @param array $file one $_FILES entry
 * @return array{ok:bool, error?:string, path?:string, w?:int, h?:int, bytes?:int, jpeg?:string}
 */
function rrp_social_compress_photo(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No photo.'];
    }
    $err = (int) ($file['error'] ?? 0);
    if (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || (int) ($file['size'] ?? 0) > RRP_SOCIAL_MAX_UPLOAD) {
        return ['ok' => false, 'error' => 'Photo is too big (max 10 MB).'];
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Photo upload failed. Please try again.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']) ?: '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return ['ok' => false, 'error' => 'Use a JPG, PNG or WebP photo. On iPhone choose "Most Compatible".'];
    }
    $res = rrp_social_compress_path((string) $file['tmp_name']);
    @unlink((string) $file['tmp_name']);
    return $res;
}

/** @return array{ok:bool, error?:string, path?:string, w?:int, h?:int, bytes?:int, jpeg?:string} */
function rrp_social_compress_path(string $path): array
{
    $src = @imagecreatefromstring((string) file_get_contents($path));
    if (!$src) {
        return ['ok' => false, 'error' => 'That photo could not be read.'];
    }
    if (function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $src = match ($o) { 3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src };
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, RRP_SOCIAL_MAX_SIDE / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // flatten PNG transparency
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $jpeg = '';
    foreach ([84, 78, 72, 66, 60] as $q) {
        ob_start();
        imagejpeg($dst, null, $q);
        $jpeg = (string) ob_get_clean();
        if (strlen($jpeg) <= RRP_SOCIAL_TARGET_BYTES) {
            break;
        }
    }
    $dir = dirname(__DIR__, 2) . '/uploads/social/' . gmdate('Y/m');
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return ['ok' => false, 'error' => 'Could not save the photo.'];
    }
    $name = rrp_public_id(12) . '.jpg';
    if (@file_put_contents($dir . '/' . $name, $jpeg) === false) {
        return ['ok' => false, 'error' => 'Could not save the photo.'];
    }
    return ['ok' => true, 'path' => 'uploads/social/' . gmdate('Y/m') . '/' . $name, 'w' => $nw, 'h' => $nh, 'bytes' => strlen($jpeg), 'jpeg' => $jpeg];
}

function rrp_social_delete_photo(?string $rel): void
{
    if (!$rel || !str_starts_with($rel, 'uploads/social/')) {
        return;
    }
    $abs = dirname(__DIR__, 2) . '/' . $rel;
    if (is_file($abs)) {
        @unlink($abs);
    }
}
