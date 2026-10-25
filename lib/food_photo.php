<?php
declare(strict_types=1);

require_once __DIR__ . '/openai/Client.php';

/**
 * T12 — food photo soft rating (vision). Requires openai.api_key.
 * @return array{ok:bool, error?:string, gate?:string, rating?:array}
 */
function rrp_rate_food_photo(array $owner, int $petId, string $imageDataUrlOrPath): array
{
    if (rrp_owner_plan($owner) !== 'plus') {
        return rrp_plus_required_payload('food_photo');
    }
    $pet = rrp_pet_by_id($petId, (int) $owner['id']);
    if (!$pet) {
        return ['ok' => false, 'error' => 'Pet not found.'];
    }

    $client = new RrpOpenAiClient();
    if (!$client->configured()) {
        return [
            'ok' => false,
            'gate' => 'missing_api_key',
            'error' => 'Add openai.api_key in Hostinger config to enable food photo rating.',
        ];
    }

    $cfg = rrp_load_config()['openai'] ?? [];
    $model = (string) (($cfg['models']['food_vision'] ?? null) ?: ($cfg['model'] ?? 'gpt-4o-mini'));

    $content = [
        [
            'type' => 'text',
            'text' => 'Rate this pet food photo for ' . $pet['name'] . '. Return JSON only: {"quality":1-5,"calories_guess":number|null,"notes":"short","disclaimer":"not veterinary advice"}. Soft educational score only.',
        ],
    ];
    if (str_starts_with($imageDataUrlOrPath, 'data:image')) {
        $content[] = ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrlOrPath]];
    } elseif (is_file($imageDataUrlOrPath)) {
        $mime = mime_content_type($imageDataUrlOrPath) ?: 'image/jpeg';
        $b64 = base64_encode((string) file_get_contents($imageDataUrlOrPath));
        $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . $b64]];
    } else {
        return ['ok' => false, 'error' => 'Image required.'];
    }

    // Vision via chat completions multimodal
    $ch = curl_init(rtrim((string) ($cfg['base_url'] ?? 'https://api.openai.com/v1'), '/') . '/chat/completions');
    $payload = [
        'model' => $model,
        'temperature' => 0.2,
        'messages' => [
            ['role' => 'system', 'content' => 'You are SIPO. Educational food photo notes only. No dosing. JSON only.'],
            ['role' => 'user', 'content' => $content],
        ],
        'response_format' => ['type' => 'json_object'],
    ];
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . trim((string) ($cfg['api_key'] ?? '')),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 90,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $status >= 400) {
        return ['ok' => false, 'error' => 'Food rating failed. Check API key / model vision support.'];
    }
    $data = json_decode($raw, true);
    $text = (string) ($data['choices'][0]['message']['content'] ?? '');
    $rating = json_decode($text, true);
    if (!is_array($rating)) {
        return ['ok' => false, 'error' => 'Could not parse rating.'];
    }
    rrp_si_bump_usage((int) $owner['id'], 1, (int) ($data['usage']['total_tokens'] ?? 0));
    return ['ok' => true, 'rating' => $rating];
}
