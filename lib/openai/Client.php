<?php
declare(strict_types=1);

/**
 * Minimal OpenAI Chat Completions client.
 * Empty api_key → structured error (Navid adds key in Hostinger sipaw.php).
 */
final class RrpOpenAiClient
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(?array $cfg = null)
    {
        $cfg = $cfg ?? (rrp_config_ready() ? (rrp_load_config()['openai'] ?? []) : []);
        $this->apiKey = trim((string) ($cfg['api_key'] ?? ''));
        $this->baseUrl = rtrim((string) ($cfg['base_url'] ?? 'https://api.openai.com/v1'), '/');
    }

    public function configured(): bool
    {
        return $this->apiKey !== '';
    }

    public function defaultModel(): string
    {
        $cfg = rrp_config_ready() ? (rrp_load_config()['openai'] ?? []) : [];
        $models = $cfg['models'] ?? [];
        if (!empty($models['si_chat'])) {
            return (string) $models['si_chat'];
        }
        return (string) ($cfg['model'] ?? 'gpt-4o-mini');
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @return array{ok:bool, error?:string, content?:string, model?:string, usage?:array, gate?:string}
     */
    public function chat(array $messages, ?string $model = null, float $temperature = 0.6): array
    {
        if (!$this->configured()) {
            return [
                'ok' => false,
                'gate' => 'missing_api_key',
                'error' => 'OpenAI is not configured yet. Add openai.api_key in Hostinger config/sipaw.php.',
            ];
        }

        $model = $model ?: $this->defaultModel();
        $payload = [
            'model' => $model,
            'temperature' => $temperature,
            'messages' => $messages,
        ];

        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 60,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno || $raw === false) {
            return ['ok' => false, 'error' => 'OpenAI request failed (network).'];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'OpenAI returned invalid JSON.'];
        }
        if ($status >= 400) {
            $msg = (string) ($data['error']['message'] ?? 'OpenAI error ' . $status);
            return ['ok' => false, 'error' => $msg];
        }
        $content = (string) ($data['choices'][0]['message']['content'] ?? '');
        if ($content === '') {
            return ['ok' => false, 'error' => 'Empty assistant response.'];
        }
        return [
            'ok' => true,
            'content' => $content,
            'model' => $model,
            'usage' => $data['usage'] ?? [],
        ];
    }
}
