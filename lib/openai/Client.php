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
     * Self-healing: if the API rejects a parameter this model does not support
     * (custom temperature, tools) it retries without it and remembers that for the
     * model; an unknown model falls back to openai.fallback_model; a 5xx retries once.
     *
     * @param list<array<string,mixed>> $messages
     * @param list<array<string,mixed>> $tools OpenAI "tools" array (type=function)
     * @return array{ok:bool, error?:string, status?:int, content?:string, tool_calls?:list<array>, model?:string, usage?:array, gate?:string, latency_ms?:int}
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
        $cfg = rrp_config_ready() ? (rrp_load_config()['openai'] ?? []) : [];
        $fallback = trim((string) ($cfg['fallback_model'] ?? ''));

        $payload = ['model' => $model, 'messages' => $messages];
        if (!self::quirk($model, 'no_temperature')) {
            $payload['temperature'] = $temperature;
        }
        if ($tools !== [] && !self::quirk($model, 'no_tools')) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }
        $payload = array_merge($payload, $extra);

        $res = ['ok' => false, 'error' => 'OpenAI request not sent.'];
        $retried5xx = false;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $res = $this->send($payload);
            if (!empty($res['ok'])) {
                break;
            }
            $status = (int) ($res['status'] ?? 0);
            $err = strtolower((string) ($res['error'] ?? ''));
            $param = strtolower((string) ($res['param'] ?? ''));
            if (isset($payload['temperature']) && ($param === 'temperature' || str_contains($err, 'temperature'))) {
                unset($payload['temperature']);
                self::rememberQuirk((string) $payload['model'], 'no_temperature');
                continue;
            }
            if (isset($payload['tools']) && (in_array($param, ['tools', 'tool_choice'], true)
                    || preg_match('/\b(tools?|tool_choice|function calling|functions)\b.*(not supported|unsupported|does not support)/', $err))) {
                unset($payload['tools'], $payload['tool_choice']);
                self::rememberQuirk((string) $payload['model'], 'no_tools');
                continue;
            }
            if (isset($payload['response_format']) && ($param === 'response_format' || str_contains($err, 'response_format'))) {
                unset($payload['response_format']);
                continue;
            }
            $modelProblem = $status === 404 || $param === 'model'
                || preg_match('/model.*(does not exist|not found|no access|do not have access|not supported|invalid)/', $err);
            if ($modelProblem && $fallback !== '' && $payload['model'] !== $fallback) {
                $payload['model'] = $fallback;
                continue;
            }
            if (($status >= 500 || $status === 0) && !$retried5xx) {
                $retried5xx = true;
                usleep(700000);
                continue;
            }
            break;
        }
        if (empty($res['ok'])) {
            self::recordError($res, (string) $payload['model']);
        }
        return $res + ['model' => $payload['model']];
    }

    /** @return array{ok:bool, status:int, error?:string, param?:string, content?:string, tool_calls?:list<array>, model?:string, usage?:array, latency_ms:int} */
    private function send(array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($body === false) {
            return ['ok' => false, 'status' => 0, 'error' => 'Could not encode the request.', 'latency_ms' => 0];
        }
        $t0 = (int) floor(microtime(true) * 1000);
        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $latency = (int) floor(microtime(true) * 1000) - $t0;

        if ($errno || $raw === false) {
            return ['ok' => false, 'status' => 0, 'error' => 'OpenAI request failed (network: ' . curl_strerror($errno) . ').', 'latency_ms' => $latency];
        }
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'status' => $status, 'error' => 'OpenAI returned invalid JSON (HTTP ' . $status . ').', 'latency_ms' => $latency];
        }
        if ($status >= 400) {
            return [
                'ok' => false,
                'status' => $status,
                'error' => (string) ($data['error']['message'] ?? 'OpenAI error ' . $status),
                'param' => (string) ($data['error']['param'] ?? ''),
                'code' => (string) ($data['error']['code'] ?? ''),
                'latency_ms' => $latency,
                'gate' => $status === 429 ? 'provider_rate_limit' : null,
            ];
        }
        $msg = $data['choices'][0]['message'] ?? [];
        $toolCalls = is_array($msg['tool_calls'] ?? null) ? $msg['tool_calls'] : [];
        $content = (string) ($msg['content'] ?? '');
        if ($content === '' && $toolCalls === []) {
            $why = (string) ($data['choices'][0]['finish_reason'] ?? '');
            return ['ok' => false, 'status' => $status, 'error' => 'Empty assistant response' . ($why !== '' ? ' (finish_reason: ' . $why . ')' : '') . '.', 'latency_ms' => $latency];
        }
        return [
            'ok' => true,
            'status' => $status,
            'content' => $content,
            'tool_calls' => $toolCalls,
            'model' => (string) ($data['model'] ?? $payload['model']),
            'usage' => $data['usage'] ?? [],
            'latency_ms' => $latency,
        ];
    }

    // Per-model quirks learned from API errors (file cache; shared hosting has no APCu guarantee).
    private static function quirkFile(): string
    {
        return sys_get_temp_dir() . '/sipaw-openai-quirks.json';
    }

    private static function quirk(string $model, string $q): bool
    {
        $all = json_decode((string) @file_get_contents(self::quirkFile()), true);
        return !empty($all[$model][$q]);
    }

    private static function rememberQuirk(string $model, string $q): void
    {
        $all = json_decode((string) @file_get_contents(self::quirkFile()), true) ?: [];
        $all[$model][$q] = true;
        @file_put_contents(self::quirkFile(), json_encode($all), LOCK_EX);
    }

    /** Last OpenAI failure — shown to super admins so a broken key/model is never silent. */
    private static function recordError(array $res, string $model): void
    {
        $info = [
            'at' => gmdate('c'),
            'model' => $model,
            'status' => (int) ($res['status'] ?? 0),
            'error' => mb_substr((string) ($res['error'] ?? ''), 0, 400),
        ];
        error_log('OpenAI error: ' . json_encode($info));
        @file_put_contents(sys_get_temp_dir() . '/sipaw-openai-last-error.json', json_encode($info), LOCK_EX);
    }

    public static function lastError(): ?array
    {
        $d = json_decode((string) @file_get_contents(sys_get_temp_dir() . '/sipaw-openai-last-error.json'), true);
        return is_array($d) ? $d : null;
    }
}
