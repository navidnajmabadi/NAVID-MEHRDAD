<?php
declare(strict_types=1);

/**
 * Sign in with Google (OpenID Connect, authorization code + PKCE).
 * Config (Hostinger config file → google.*): client_id, client_secret, redirect_uri.
 * The button only shows when client_id and client_secret are set.
 *
 * Checks: state (CSRF), nonce, PKCE, and the ID token's audience, issuer, expiry and verified email
 * (via Google's tokeninfo endpoint, which also validates the signature).
 * Accounts: linked by Google id; else an existing owner with the same verified email is linked;
 * else a new Free account is created and SIPO starts onboarding.
 */

function rrp_google_cfg(): array
{
    $g = rrp_config_ready() ? (rrp_load_config()['google'] ?? []) : [];
    return [
        'client_id' => trim((string) ($g['client_id'] ?? '')),
        'client_secret' => trim((string) ($g['client_secret'] ?? '')),
        'redirect_uri' => trim((string) ($g['redirect_uri'] ?? '')) ?: rtrim(rrp_app_url(), '/') . '/oauth/google-callback.php',
        // Overridable only for local tests (fake Google); production uses Google's real endpoints.
        'auth_url' => (string) ($g['auth_url'] ?? 'https://accounts.google.com/o/oauth2/v2/auth'),
        'token_url' => (string) ($g['token_url'] ?? 'https://oauth2.googleapis.com/token'),
        'tokeninfo_url' => (string) ($g['tokeninfo_url'] ?? 'https://oauth2.googleapis.com/tokeninfo'),
    ];
}

function rrp_google_configured(): bool
{
    $c = rrp_google_cfg();
    return $c['client_id'] !== '' && $c['client_secret'] !== '' && !str_starts_with($c['client_secret'], 'CHANGE_ME');
}

/** URL to send the browser to Google. Stores state, nonce and the PKCE verifier in the session. */
function rrp_google_start_url(string $next = ''): string
{
    rrp_start_session();
    $c = rrp_google_cfg();
    $state = bin2hex(random_bytes(16));
    $nonce = bin2hex(random_bytes(16));
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $_SESSION['google_sso'] = [
        'state' => $state,
        'nonce' => $nonce,
        'verifier' => $verifier,
        'next' => rrp_safe_next($next, ''),
        'at' => time(),
    ];
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    return $c['auth_url'] . '?' . http_build_query([
        'client_id' => $c['client_id'],
        'redirect_uri' => $c['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'nonce' => $nonce,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'prompt' => 'select_account',
    ]);
}

/** @return array{status:int, body:?array} */
function rrp_google_http(string $url, ?array $post = null): array
{
    $ch = curl_init($url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12, CURLOPT_HTTPHEADER => ['Accept: application/json']];
    if ($post !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $body = is_string($raw) ? json_decode($raw, true) : null;
    return ['status' => $status, 'body' => is_array($body) ? $body : null];
}

/**
 * Finish the Google round trip: verify everything, then log in, link or create the owner.
 * @return array{ok:bool, error?:string, new?:bool, next?:string}
 */
function rrp_google_finish(string $code, string $state): array
{
    rrp_start_session();
    $s = $_SESSION['google_sso'] ?? null;
    unset($_SESSION['google_sso']); // one use only
    if (!is_array($s) || $state === '' || !hash_equals((string) $s['state'], $state) || time() - (int) $s['at'] > 600) {
        return ['ok' => false, 'error' => 'That Google sign-in link expired. Please try again.'];
    }
    if ($code === '') {
        return ['ok' => false, 'error' => 'Google did not send a sign-in code. Please try again.'];
    }
    $c = rrp_google_cfg();
    $tok = rrp_google_http($c['token_url'], [
        'code' => $code,
        'client_id' => $c['client_id'],
        'client_secret' => $c['client_secret'],
        'redirect_uri' => $c['redirect_uri'],
        'grant_type' => 'authorization_code',
        'code_verifier' => (string) $s['verifier'],
    ]);
    $idToken = (string) ($tok['body']['id_token'] ?? '');
    if ($tok['status'] !== 200 || $idToken === '') {
        error_log('google sso token: HTTP ' . $tok['status'] . ' ' . json_encode($tok['body']));
        return ['ok' => false, 'error' => 'Google sign-in failed. Please try again or use your email and password.'];
    }
    $info = rrp_google_http($c['tokeninfo_url'] . '?' . http_build_query(['id_token' => $idToken]));
    $claims = $info['body'] ?? [];
    $valid = $info['status'] === 200
        && ($claims['aud'] ?? '') === $c['client_id']
        && in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)
        && (int) ($claims['exp'] ?? 0) > time()
        && hash_equals((string) $s['nonce'], (string) ($claims['nonce'] ?? ''))
        && ($claims['sub'] ?? '') !== '';
    if (!$valid) {
        error_log('google sso: id token rejected ' . json_encode(array_intersect_key($claims, array_flip(['aud', 'iss', 'exp']))));
        return ['ok' => false, 'error' => 'Google sign-in could not be verified. Please try again.'];
    }
    $verified = in_array($claims['email_verified'] ?? false, [true, 'true'], true);
    $email = strtolower(trim((string) ($claims['email'] ?? '')));
    if (!$verified || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Your Google email is not verified yet. Verify it with Google, or sign up with email and password.'];
    }
    $res = rrp_google_login_or_create((string) $claims['sub'], $email, (string) ($claims['given_name'] ?? $claims['name'] ?? ''));
    return $res + ['next' => (string) ($s['next'] ?? '')];
}

/** @return array{ok:bool, error?:string, new?:bool} */
function rrp_google_login_or_create(string $sub, string $email, string $name): array
{
    $db = rrp_db();
    $st = $db->prepare('SELECT * FROM owners WHERE google_sub = ? LIMIT 1');
    $st->execute([$sub]);
    $owner = $st->fetch();
    if (!$owner) {
        // Same verified email as an existing account: link it (Google proved the address).
        $st = $db->prepare('SELECT * FROM owners WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $owner = $st->fetch();
        if ($owner) {
            if (!empty($owner['google_sub']) && $owner['google_sub'] !== $sub) {
                return ['ok' => false, 'error' => 'This email is already linked to another Google account. Log in with your password.'];
            }
            $db->prepare('UPDATE owners SET google_sub = ? WHERE id = ?')->execute([$sub, (int) $owner['id']]);
        }
    }
    if ($owner) {
        if (($owner['status'] ?? 'active') !== 'active') {
            return ['ok' => false, 'error' => 'This account is paused for review because several posts were removed. Our Support team will email you within 7 days. Your pets\' data is safe.'];
        }
        rrp_auth_start_owner_session((int) $owner['id'], true);
        return ['ok' => true, 'new' => false];
    }
    // New owner: Free plan, unusable random password (they can set one with "Forgot password").
    $nick = trim(preg_replace('/\s+/u', ' ', $name) ?? '') ?: ucfirst(strtok($email, '@') ?: 'Friend');
    $nick = mb_substr($nick, 0, 60);
    try {
        $db->prepare(
            'INSERT INTO owners (name, nickname, email, phone, address, password_hash, google_sub, plan, locale, timezone, terms_accepted_at, privacy_accepted_at)
             VALUES (?, ?, ?, NULL, NULL, ?, ?, \'free\', \'en\', NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        )->execute([$nick, $nick, $email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $sub]);
        $id = (int) $db->lastInsertId();
    } catch (Throwable $e) {
        error_log('google sso create: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not create your account. Please try again.'];
    }
    try {
        rrp_referral_attach($id, $_COOKIE['sipaw_ref'] ?? null);
    } catch (Throwable $e) {
    }
    rrp_auth_start_owner_session($id, true);
    return ['ok' => true, 'new' => true];
}

/** "Continue with Google" button (Google's sign-in button style). Prints nothing until configured. */
function rrp_google_button(string $next = '', string $label = 'Continue with Google', bool $termsNote = false): void
{
    if (!rrp_google_configured()) {
        return;
    }
    $href = 'oauth/google-start.php' . ($next !== '' ? '?next=' . rawurlencode($next) : '');
    ?>
<a class="google-btn" href="<?= rrp_h($href) ?>">
  <svg viewBox="0 0 48 48" width="20" height="20" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.6 5.4 2.7 13.3l7.9 6.1C12.5 13.7 17.8 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.7 6c4.5-4.2 6.9-10.3 6.9-17.7z"/><path fill="#FBBC05" d="M10.6 28.6c-.5-1.4-.8-3-.8-4.6s.3-3.2.8-4.6l-7.9-6.1C1 16.6 0 20.2 0 24s1 7.4 2.7 10.7l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.7-6c-2.1 1.4-4.9 2.3-8.2 2.3-6.2 0-11.5-4.2-13.4-9.9l-7.9 6.1C6.6 42.6 14.6 48 24 48z"/></svg>
  <span><?= rrp_h($label) ?></span>
</a>
<?php if ($termsNote): ?><p class="meta sso-terms">By continuing with Google you accept the <a class="link" href="legal.php">Terms and Privacy Policy</a>.</p><?php endif; ?>
<div class="or-line"><span>or</span></div>
<?php
}
