<?php
declare(strict_types=1);

/**
 * First-time onboarding, in two parts:
 *  1. welcome.php: SIPO (full body) says hello, explains SIPAW, asks the owner's name and country.
 *  2. The SIPO chat: pet details (see lib/sipo/setup.php), then the photo and profile steps below.
 *
 * The photo and profile steps are plain code, not model whim: they must always happen in the
 * same order, offer the same buttons, and never repeat. State lives in one pet fact, onb_photo,
 * stored as "<stage>@<unix time>".
 */

const RRP_ONB_PHOTO_YES = "Yes, I'll send a photo";
const RRP_ONB_PHOTO_LATER = 'Later';
const RRP_ONB_PROFILE_YES = 'Yes, open the profile';
const RRP_ONB_PROFILE_NO = 'Not now';

/** @return array<string,string> ISO code => English name, A to Z. */
function rrp_onb_countries(): array
{
    static $list = null;
    if ($list !== null) {
        return $list;
    }
    $list = [];
    if (class_exists('ResourceBundle')) {
        try {
            $b = ResourceBundle::create('en', 'ICUDATA-region');
            foreach ($b['Countries'] ?? [] as $code => $name) {
                if (preg_match('/^[A-Z]{2}$/', (string) $code) && !in_array($code, ['EU', 'UN', 'ZZ', 'QO', 'XA', 'XB', 'EZ', 'AC', 'CP', 'DG', 'EA', 'IC', 'TA'], true)) {
                    $list[(string) $code] = (string) $name;
                }
            }
        } catch (Throwable $e) {
            $list = [];
        }
    }
    if (count($list) < 100) {
        // Without the intl extension: the places most owners live.
        $list = ['AU' => 'Australia', 'AT' => 'Austria', 'BE' => 'Belgium', 'BR' => 'Brazil', 'CA' => 'Canada', 'CN' => 'China', 'DK' => 'Denmark',
            'FI' => 'Finland', 'FR' => 'France', 'DE' => 'Germany', 'IN' => 'India', 'IE' => 'Ireland', 'IT' => 'Italy', 'JP' => 'Japan', 'MX' => 'Mexico',
            'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NO' => 'Norway', 'PL' => 'Poland', 'PT' => 'Portugal', 'ES' => 'Spain', 'SE' => 'Sweden',
            'CH' => 'Switzerland', 'TR' => 'Türkiye', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States'];
    }
    asort($list, SORT_NATURAL | SORT_FLAG_CASE);
    return $list;
}

/**
 * Best guess of the owner's country (ISO code, or ''), no third party involved:
 * 1. the country header our CDN adds from the visitor's IP (Cloudflare: CF-IPCountry),
 * 2. else the country of the time zone their phone reported.
 */
function rrp_onb_guess_country(?array $owner = null, ?array $server = null): string
{
    $server ??= $_SERVER;
    $cf = strtoupper(trim((string) ($server['HTTP_CF_IPCOUNTRY'] ?? '')));
    if (preg_match('/^[A-Z]{2}$/', $cf) && !in_array($cf, ['XX', 'T1'], true)) {
        return $cf;
    }
    $tz = trim((string) ($owner['timezone'] ?? ''));
    if ($tz !== '') {
        try {
            $cc = (new DateTimeZone($tz))->getLocation()['country_code'] ?? '';
            if (preg_match('/^[A-Z]{2}$/', (string) $cc)) {
                return (string) $cc;
            }
        } catch (Throwable $e) {
        }
    }
    return '';
}

/** Country name stored on the owner (what Admin and the rest of the app already show). */
function rrp_onb_country_name(string $code): string
{
    return rrp_onb_countries()[strtoupper($code)] ?? '';
}

/** Reverse: stored name (or code) to ISO code, for pre-selecting the dropdown. */
function rrp_onb_country_code(string $nameOrCode): string
{
    $v = trim($nameOrCode);
    if ($v === '') {
        return '';
    }
    $all = rrp_onb_countries();
    if (isset($all[strtoupper($v)])) {
        return strtoupper($v);
    }
    foreach ($all as $code => $name) {
        if (strcasecmp($name, $v) === 0) {
            return (string) $code;
        }
    }
    return '';
}

/**
 * Save the welcome (or Profile) form: how SIPO should call the owner, country, optional city.
 *
 * @return array{ok:bool, error?:string}
 */
function rrp_onb_save_profile(array $owner, array $post, bool $finishOnboarding): array
{
    $name = trim(preg_replace('/\s+/u', ' ', (string) ($post['name'] ?? '')) ?? '');
    if ($name === '' || mb_strlen($name) > 80) {
        return ['ok' => false, 'error' => 'Tell me what to call you (up to 80 characters).'];
    }
    $country = rrp_onb_country_name((string) ($post['country'] ?? ''));
    $city = trim(preg_replace('/\s+/u', ' ', (string) ($post['city'] ?? '')) ?? '');
    if (mb_strlen($city) > 80) {
        return ['ok' => false, 'error' => 'City is too long.'];
    }
    $nick = mb_substr(explode(' ', $name)[0], 0, 40);
    $sql = 'UPDATE owners SET name = ?, nickname = ?, country = ?, city = ?' . ($finishOnboarding ? ', onboarded_at = COALESCE(onboarded_at, UTC_TIMESTAMP())' : '') . ' WHERE id = ?';
    rrp_db()->prepare($sql)->execute([$name, $nick, $country !== '' ? $country : null, $city !== '' ? $city : null, (int) $owner['id']]);
    return ['ok' => true];
}

// ---------------------------------------------------------------------------
// Photo and profile steps (in the SIPO chat)
// ---------------------------------------------------------------------------

/** @return array{stage:string, at:int} stage is '' when the step has not started. */
function rrp_onb_state(int $petId): array
{
    $v = (string) (rrp_setup_facts($petId)['onb_photo'] ?? '');
    if ($v === '') {
        return ['stage' => '', 'at' => 0];
    }
    [$stage, $at] = array_pad(explode('@', $v, 2), 2, '0');
    return ['stage' => $stage, 'at' => (int) $at];
}

function rrp_onb_set_state(int $ownerId, int $petId, string $stage): void
{
    rrp_sipo_save_fact($ownerId, $petId, 'onb_photo', $stage . '@' . time(), 'owner');
}

function rrp_onb_pet_has_photo(array $pet): bool
{
    return trim((string) ($pet['image_path'] ?? '')) !== '';
}

/**
 * The owner pressed one of our buttons: answer without the model.
 *
 * @return array{text:string, tokens:int, mascot:string, tools:list<string>, model:?string, choices?:list<string>}|null
 */
function rrp_onb_intercept(array &$ctx, string $body): ?array
{
    $pet = $ctx['pet'] ?? null;
    if (!$pet) {
        return null;
    }
    $pid = (int) $pet['id'];
    $oid = (int) $ctx['owner']['id'];
    $name = (string) $pet['name'];
    $st = rrp_onb_state($pid)['stage'];
    $b = trim($body);
    $reply = static fn(string $text, string $mascot, ?array $choices = null): array => ['text' => $text, 'tokens' => 0, 'mascot' => $mascot, 'tools' => [], 'model' => null]
        + ($choices ? ['choices' => $choices] : []);

    $photoAsked = in_array($st, ['asked', 'asked_again'], true);
    if ($photoAsked && strcasecmp($b, RRP_ONB_PHOTO_YES) === 0) {
        rrp_onb_set_state($oid, $pid, 'waiting');
        return $reply("Lovely 💙 Tap the 📎 paperclip below and pick a photo of {$name}. I will set it as their profile picture.", 'happy');
    }
    if ($photoAsked && strcasecmp($b, RRP_ONB_PHOTO_LATER) === 0) {
        rrp_onb_set_state($oid, $pid, 'profile_asked');
        return $reply("No problem at all. Want me to open {$name}'s profile so you can add a photo whenever you like?", 'happy', [RRP_ONB_PROFILE_YES, RRP_ONB_PROFILE_NO]);
    }
    if ($st === 'profile_asked' && strcasecmp($b, RRP_ONB_PROFILE_YES) === 0) {
        rrp_onb_set_state($oid, $pid, 'done');
        $ctx['links'][] = ['url' => 'pet-profile.php?id=' . $pid, 'label' => "Open {$name}'s profile"];
        $ctx['events'][] = 'page_opened';
        return $reply("Here you go. The button below opens {$name}'s profile. I am here whenever you want to carry on 🐾", 'happy');
    }
    if ($st === 'profile_asked' && strcasecmp($b, RRP_ONB_PROFILE_NO) === 0) {
        rrp_onb_set_state($oid, $pid, 'declined');
        return $reply("Of course. Whenever you want a photo on {$name}'s profile, just tell me. What would you like to do next?", 'idle');
    }
    return null;
}

/**
 * After SIPO's reply: once the pet's basics are done and there is no photo yet, add the photo
 * question to the same message (so one bubble, one set of buttons). Also notices a new photo.
 *
 * @param array $reply result of rrp_sipo_generate (text, choices, mascot, ...)
 */
function rrp_onb_after_reply(array &$ctx, array $reply): array
{
    $pet = $ctx['pet'] ?? null;
    if (!$pet) {
        return $reply;
    }
    $pid = (int) $pet['id'];
    $fresh = rrp_pet_by_id($pid, (int) $ctx['owner']['id']) ?: $pet;
    $state = rrp_onb_state($pid)['stage'];

    if (rrp_onb_pet_has_photo($fresh)) {
        if ($state !== '' && $state !== 'done') {
            rrp_onb_set_state((int) $ctx['owner']['id'], $pid, 'done');
        }
        return $reply;
    }
    if ($state !== '') {
        return $reply;
    }
    $plan = rrp_sipo_setup_plan($fresh);
    $petSection = $plan['sections'][0] ?? null;
    if (!$petSection || !$petSection['complete'] || !empty($reply['choices']) || !empty($ctx['links']) || preg_match('/\?\s*$|\?[^\n]*$/u', trim((string) $reply['text']))) {
        // One question per message: if this reply already asks something or offers a button,
        // the photo question waits for the next reply.
        return $reply;
    }
    $name = (string) $fresh['name'];
    rrp_onb_set_state((int) $ctx['owner']['id'], $pid, 'asked');
    $reply['text'] = rtrim((string) $reply['text']) . "\n\nThat is {$name}'s profile filled in 🎉 Could you share a photo of {$name}? It becomes their profile picture.";
    $reply['choices'] = [RRP_ONB_PHOTO_YES, RRP_ONB_PHOTO_LATER];
    $reply['mascot'] = 'celebrate';
    return $reply;
}

/**
 * Cron: the owner did not answer the photo or profile question. About 10 minutes later SIPO
 * checks in once, softly (push notification too if they allowed it). Never twice.
 *
 * @return array{checked:int, nudged:int}
 */
function rrp_onb_nudge_tick(int $max = 30): array
{
    $out = ['checked' => 0, 'nudged' => 0];
    $st = rrp_db()->prepare(
        "SELECT m.pet_id, m.owner_id, m.fact_value FROM sipo_memory m
         WHERE m.fact_key = 'onb_photo' AND (m.fact_value LIKE 'asked@%' OR m.fact_value LIKE 'profile_asked@%' OR m.fact_value LIKE 'waiting@%')
         LIMIT 200"
    );
    $st->execute();
    foreach ($st->fetchAll() as $row) {
        [$stage, $at] = array_pad(explode('@', (string) $row['fact_value'], 2), 2, '0');
        if ((int) $at > time() - 600) {
            continue; // not yet ten minutes
        }
        if ($out['checked'] >= $max) {
            break;
        }
        $out['checked']++;
        $ownerId = (int) $row['owner_id'];
        $pet = rrp_pet_by_id((int) $row['pet_id'], $ownerId);
        if (!$pet || rrp_onb_pet_has_photo($pet)) {
            rrp_onb_set_state($ownerId, (int) $row['pet_id'], 'done');
            continue;
        }
        $thread = rrp_sipo_thread_for_pet($ownerId, (int) $pet['id']);
        if (!$thread) {
            continue;
        }
        $name = (string) $pet['name'];
        $text = $stage === 'waiting'
            ? "Still thinking about {$name}'s photo? Whenever you are ready, tap the 📎 paperclip and send it. No rush 💙"
            : "Hi! Whenever you like, a photo of {$name} would make the profile feel complete. Want to add one now?";
        $meta = ['mascot' => 'happy', 'proactive' => 'onboarding_photo'];
        if ($stage !== 'waiting') {
            $meta['choices'] = [RRP_ONB_PHOTO_YES, RRP_ONB_PHOTO_LATER];
        }
        // The photo question is asked again only through this one nudge; mark it so it cannot repeat.
        rrp_onb_set_state($ownerId, (int) $pet['id'], $stage === 'waiting' ? 'nudged' : 'asked_again');
        rrp_sipo_insert_message((int) $thread['id'], 'assistant', $text, 0, $meta);
        rrp_sipo_push($ownerId, 'SIPO · ' . $name, $text, rrp_app_url('si-chat.php?pet=' . (int) $pet['id']));
        $out['nudged']++;
    }
    return $out;
}
