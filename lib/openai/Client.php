<?php
declare(strict_types=1);

/**
 * Minimal OpenAI Chat Completions client.
 * Empty api_key → structured error (Navid adds key in Hostinger sipaw.php).
 * Dual-key: product (api_key) vs brain (brain_api_key, falls back to api_key).
 */
final class RrpOpenAiClient
{
    public const KEY_PRODUCT = 'product';
    public const KEY_BRAIN = 'brain';

    private string $apiKey;
    private string $baseUrl;
    private string $keyRole;

    public function __construct(?array $cfg = null, string $keyRole = self::KEY_PRODUCT)
    {
        $cfg = $cfg ?? (rrp_config_ready() ? (rrp_load_config()['openai'] ?? []) : []);
        $this->keyRole = $keyRole === self::KEY_BRAIN ? self::KEY_BRAIN : self::KEY_PRODUCT;
        $product = trim((string) ($cfg['api_key'] ?? ''));
        $brain = trim((string) ($cfg['brain_api_key'] ?? ''));
        if ($this->keyRole === self::KEY_BRAIN) {
            $this->apiKey = $brain !== '' ? $brain : $product;
        } else {
            $this->apiKey = $product;
        }
        $this->baseUrl = rtrim((string) ($cfg['base_url'] ?? 'https://api.openai.com/v1'), '/');
    }

    public static function product(?array $cfg = null): self
    {
        return new self($cfg, self::KEY_PRODUCT);
    }

    public static function brain(?array $cfg = null): self
    {
        return new self($cfg, self::KEY_BRAIN);
    }

    public function configured(): bool
    {
        return $this->apiKey !== '';
    }

    public function keyRole(): string
    {
        return $this->keyRole;
    }

    public function defaultModel(): string
    {
        return $this->modelFor('si_chat', 'gpt-6-luna');
    }

    public function brainModel(): string
    {
        return $this->modelFor('si_brain', 'gpt-6-astra');
    }

    public function modelFor(string $slot, string $fallback = 'gpt-6-luna'): string
    {
        $cfg = rrp_config_ready() ? (rrp_load_config()['openai'] ?? []) : [];
        $models = $cfg['models'] ?? [];
        if (!empty($models[$slot])) {
            return (string) $models[$slot];
        }
        if ($slot === 'si_brain') {
            return (string) ($cfg['model'] ?? $fallback) === '' ? $fallback : 'gpt-6-astra';
        }
        return (string) ($cfg['model'] ?? $fallback);
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @return array{ok:bool, error?:string, content?:string, model?:string, usage?:array, gate?:string, latency_ms?:int}
     */
    public function chat(array $messages, ?string $model = null, float $temperature = 0.6): array
    {
        $res = $this->complete($messages, [], $model, $temperature);
        if (!empty($res['ok']) && ($res['content'] ?? '') === '') {
            return ['ok' => false, 'error' => 'Empty assistant response.', 'latency_ms' => $res['latency_ms'] ?? 0];
        }
        return $res;
    }

    /**
     * One Chat Completions call, optionally with function tools.
     *
     * @param list<array<string,mixed>> $messages
     * @param list<array<string,mixed>> $tools OpenAI "tools" array (type=function)
     * @return array{ok:bool, error?:string, content?:string, tool_calls?:list<array>, model?:string, usage?:array, gate?:string, latency_ms?:int}
     */
    public function complete(array $messages, array $tools = [], ?string $model = null, float $temperature = 0.6, array $extra = []): array
    {
        if (!$this->configured()) {
            $which = $this->keyRole === self::KEY_BRAIN ? 'openai.brain_api_key (or api_key)' : 'openai.api_key';
            return [
                'ok' => false,
                'gate' => 'missing_api_key',
                'error' => 'OpenAI is not configured yet. Add ' . $which . ' in Hostinger config/sipaw.php.',
            ];
        }

        if ($model === null || $model === '') {
            $model = $this->keyRole === self::KEY_BRAIN ? $this->brainModel() : $this->defaultModel();
        }

        $payload = [
            'model' => $model,
            'temperature' => $temperature,
            'messages' => $messages,
        ];
        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }
        $payload = array_merge($payload, $extra);

        $t0 = (int) floor(microtime(true) * 1000);
        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $latency = (int) floor(microtime(true) * 1000) - $t0;

        if ($errno || $raw === false) {
            return ['ok' => false, 'error' => 'OpenAI request failed (network).', 'latency_ms' => $latency];
        }
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'OpenAI returned invalid JSON.', 'latency_ms' => $latency];
        }
        if ($status >= 400) {
            $msg = (string) ($data['error']['message'] ?? 'OpenAI error ' . $status);
            return ['ok' => false, 'error' => $msg, 'latency_ms' => $latency, 'gate' => $status === 429 ? 'provider_rate_limit' : null];
        }
        $msg = $data['choices'][0]['message'] ?? [];
        $toolCalls = is_array($msg['tool_calls'] ?? null) ? $msg['tool_calls'] : [];
        $content = (string) ($msg['content'] ?? '');
        if ($content === '' && $toolCalls === []) {
            return ['ok' => false, 'error' => 'Empty assistant response.', 'latency_ms' => $latency];
        }
        return [
            'ok' => true,
            'content' => $content,
            'tool_calls' => $toolCalls,
            'model' => $model,
            'usage' => $data['usage'] ?? [],
            'latency_ms' => $latency,
        ];
    }

    /**
     * JSON-mode call (vision/files allowed in $messages content parts).
     * @return array{ok:bool, data?:array, error?:string, usage?:array, gate?:string}
     */
    public function json(array $messages, ?string $model = null): array
    {
        $res = $this->complete($messages, [], $model, 0.1, ['response_format' => ['type' => 'json_object']]);
        if (empty($res['ok'])) {
            return $res;
        }
        $data = json_decode((string) $res['content'], true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Model did not return JSON.'];
        }
        return ['ok' => true, 'data' => $data, 'usage' => $res['usage'] ?? [], 'model' => $res['model'] ?? null];
    }
}
