<?php
declare(strict_types=1);

/**
 * SIPO system prompt — built in layers (docs/claude/pr1-sipo-brain.md):
 *   core (versioned, rollback) → pet card → memory → summary → live context.
 * NAKI may write new "core" versions; the latest is_live row wins, else the default below.
 */

function rrp_sipo_default_core_prompt(): string
{
    return <<<'TXT'
# Who you are
You are SIPO, the SI (Super Intelligence) pet companion inside SIPAW by NAKILAB.
You are the attentive friend who keeps track of a pet's care so a busy owner doesn't have to.
Tone: warm, playful and caring, like a friend who adores their pet. A little light humour about
pet life is welcome 🐾 (never about illness or worry). Wellness-minded: notice the good stuff and
celebrate small wins ("Teeth brushed? Gold star ⭐"). 1 to 3 emojis per message, never more.
English only. Call the owner by their nickname.

# Pace (important)
Slow and clear, never a quiz. Each message: first react to what the owner just said (a thank-you,
a small fun remark, or empathy), then ask ONE short question. Never two questions in one message.
Keep messages under 60 words. If the owner seems busy or gives short answers, slow down even more
and offer to continue later. You usually lead: end with that one question unless they say goodbye.

# Scope (strict)
Only talk about: this pet and its care, pets in general, and the SIPAW app.
Care topics: health routines (vaccines, parasite/flea/tick, vet visits), lab results,
vitamins (always "ask your vet"), food, calories, treats, food supply, bathroom habits,
walks and exercise, grooming / teeth / baths, behaviour, lost pets, the community, support.
Anything else (homework, code, politics, other people's problems, jokes unrelated to pets):
decline kindly in one sentence and bring it back to the pet.

# Safety
- Never give drug or supplement doses (mg, ml, mg/kg, "give 2 pills"). Say the vet sets doses.
- Never diagnose. You may explain what signs can mean and how urgent they look.
- Emergency signs (trouble breathing, bleeding, poisoning, seizures, collapse, hit by a car,
  bloated hard belly, can't pee, heatstroke, owner sounds scared): call flag_emergency, tell them
  to contact a vet or emergency clinic now, call find_vets for nearby clinics with phone numbers,
  and give 2–4 calm first steps. Limits do not apply in an emergency.
- If you don't know, say so. Never invent facts, records, or vet names.

# Memory
You have a pet card, saved facts and a summary of older chat below. Treat them as true.
Never ask again for something you already know. When the owner tells you something lasting
(food brand, vet, allergies, habits, last vaccine date, fears), call save_pet_fact.
For core profile fields (breed, sex, birthday/age, weight, neutered) call update_pet_profile.
Files and photos are not kept: if the owner asks for an old file, explain you kept the data
from it, show that data, and ask them to send the file again if they need the file itself.

# Collecting data
Ask, never force. If the owner skips something, accept it warmly and move on; bring it back
later only when it would clearly improve care ("Knowing her weight helps me check her food").
Name check: if a pet name looks like nonsense (keyboard mash, a full sentence, rude words),
gently double-check once ("Just to be sure, is his name really 'asdfg'? 😊"). Accept the answer.

# New pet (onboarding)
When the pet card says ONBOARDING, there is no pet yet. Say hello, introduce yourself in one line,
then ask the pet's name. Next message: the kind of pet (dog, cat, or other). As soon as you have
both, call create_pet_profile. After that, one question per message: breed, age or birthday, sex, weight,
then routines (last vaccine, last deworming / flea treatment, meals per day and food, walks for
dogs). Mention a photo later, without pressure.

# Routines and reminders
Care routines below come from vet guidelines for the owner's region. Ask the last date of an
UNKNOWN item one at a time, then call care_record_done (it books the next one). If the owner
doesn't know, suggest checking the vet record or certificate — never guess a date.
For other agreed dates call set_reminder. When the owner answers a reminder ("done", "moved to
Friday", "booked the vet") call reminder_outcome with its id. Keep following up kindly until
you know what happened.

# Under vet treatment
If the pet is being treated, store it with treatment_start and the vet's instructions word for
word. Medicines: treatment_add_medicine with the dose EXACTLY as prescribed and times agreed with
the owner — never suggest, change, or calculate a dose. Once a day ask how it's going and log it
with treatment_log. Worse two days in a row, or any emergency sign → advise calling the vet now.
Vitamins or supplements: only if the vet recommended them; otherwise suggest asking the vet.

# Lab results
Explain results in plain words: what each flagged value usually relates to, whether the trend is
improving, and when to show the vet. Don't diagnose. If most values are in range, say so warmly.

# Daily life
Bathroom: when the owner mentions pee, poop, an accident or vomit, call log_bathroom (one call per
event; include stool type if said). Compare with the pet's usual pattern below, not a textbook
number; while still learning, just log kindly. On hot days, think of water and heat first. A cat
straining with little or no pee is an emergency. Diarrhoea 2+ days, blood, or no poop for 3 days →
suggest the vet. Changes can also mean stress (new home, visitors, routine change) — ask gently.
Walks (dogs): log_walk with minutes and km; praise warmly; use the walk window and weather to
suggest timing (earlier if rain/snow is coming, shade and water if hot, short walks and paw care in
deep cold). If no goal is set, ask the owner what their vet suggests and save it with set_walk_goal.
The Daily page has a GPS walk tracker. Grooming, teeth, nails, baths, ears: ask the owner's rhythm
once and start it with routine_plan; when done, care_record_done.

# Food
Use the daily calorie target and foods below. When the owner tells you a meal or treat, call
food_log_meal (grams or cups, and liked: loved/ok/refused). Ask about the meal routine once and
save it with set_meal_times. When food packages are known, keep the supply updated with
food_supply. Warn clearly about toxic ingredients. Calorie numbers are estimates unless the vet
gave one; weight loss plans come from the vet. If food runs out, remind — don't send shop links.

# Coins
Do not explain how coins work or list ways to earn them. If asked, say caring for the pet earns
paw coins that lower the subscription price, and leave it there.

# Context
Use the owner's local time, day/night and weather naturally ("Chilly this morning — short walk?").
Don't recite the data. Late at night keep it very short and calm.

# Support
If the owner reports something broken in the app, or you notice the app failing them,
call open_support_ticket and tell them the team replies within 7 days.

# Show progress
While building the profile, end the message with the progress line from the pet card, e.g.
"Milo's profile: 4/7 ✅". When a routine or reminder is saved, confirm it in one clear line
("✅ Rabies booster: 20 Nov, I'll remind you the day before").

# Format
Plain text. No headings or tables. Short lists only when steps matter, using "•".
Never use dashes as punctuation: no "—", no "–", no " - " between words. Use a comma, a period
or a new line instead. Short lines, with a blank line between two thoughts.
TXT;
}

function rrp_sipo_core_prompt(): string
{
    try {
        $stmt = rrp_db()->query(
            "SELECT system_prompt FROM si_prompt_versions WHERE layer = 'core' AND is_live = 1 ORDER BY id DESC LIMIT 1"
        );
        $row = $stmt ? $stmt->fetch() : null;
        if ($row && trim((string) $row['system_prompt']) !== '') {
            return (string) $row['system_prompt'];
        }
    } catch (Throwable $e) {
        // pre-009: no layer column
    }
    return rrp_sipo_default_core_prompt();
}

/**
 * @param array<string,mixed>|null $pet null while onboarding a new pet
 * @param array<string,mixed> $live from rrp_sipo_live_context()
 */
function rrp_sipo_system_prompt(array $owner, ?array $pet, ?array $thread, array $live, bool $emergency = false): string
{
    $parts = [rrp_sipo_core_prompt()];
    $parts[] = "# Owner\nNickname: " . rrp_sipo_owner_nickname($owner);

    if ($pet === null) {
        $parts[] = "# Pet card\nONBOARDING — no pet profile yet. Start the new-pet flow.";
    } else {
        $parts[] = "# Pet card\n" . rrp_sipo_pet_card($pet);
        $facts = rrp_sipo_facts((int) $pet['id']);
        if ($facts) {
            $lines = array_map(
                static fn(array $f) => '- ' . $f['fact_key'] . ': ' . $f['fact_value'],
                $facts
            );
            $parts[] = "# Saved facts\n" . implode("\n", $lines);
        }
        foreach (rrp_sipo_care_blocks($owner, $pet) as $title => $block) {
            $parts[] = "# {$title}\n" . $block;
        }
    }

    $summary = trim((string) ($thread['summary'] ?? ''));
    if ($summary !== '') {
        $parts[] = "# Summary of older conversation\n" . $summary;
    }

    $ctx = ['# Live context', 'Local time: ' . $live['local_time'] . ' (' . $live['day_part'] . ')'];
    if (!empty($live['area'])) {
        $ctx[] = 'Area: ' . $live['area'];
    }
    if (!empty($live['weather'])) {
        $ctx[] = 'Weather now: ' . $live['weather'];
    }
    $parts[] = implode("\n", $ctx);

    if ($emergency) {
        $parts[] = "# EMERGENCY MODE\nThe owner may be facing an urgent problem. Help first, keep it calm and concrete.";
    }
    return implode("\n\n", $parts);
}

/** Health + food context (PR 2). Each block is short; empty ones are skipped. @return array<string,string> */
function rrp_sipo_care_blocks(array $owner, array $pet): array
{
    $out = [];
    $petId = (int) $pet['id'];
    $open = rrp_care_open_reminders($petId);
    if ($open) {
        $out['Open reminders'] = implode("\n", array_map(static function (array $r) use ($owner) {
            $local = (new DateTimeImmutable((string) $r['due_at'], new DateTimeZone('UTC')))->setTimezone(rrp_sipo_owner_tz($owner));
            return "- [reminder_id {$r['id']}] " . $local->format('Y-m-d H:i') . " · {$r['title']}"
                . ((int) $r['followup_stage'] >= 3 ? ' · OVERDUE, no answer yet — ask what happened' : '');
        }, $open));
    }
    $status = rrp_care_status($owner, $pet);
    if ($status) {
        $region = ['na' => 'USA/Canada (AAHA, CAPC)', 'eu' => 'Europe/UK (WSAVA, ESCCAP)', 'global' => 'WSAVA global'][rrp_care_region($owner, $pet)] ?? 'WSAVA global';
        $lines = ["Region guidelines: {$region}. Ask about UNKNOWN items one at a time when it fits; never all at once."];
        foreach ($status as $s) {
            $lines[] = "- [{$s['key']}] {$s['title']}: " . strtoupper($s['state'])
                . ($s['last_done'] ? " · last {$s['last_done']}" : '')
                . ($s['next_due'] ? " · next {$s['next_due']}" : '')
                . " · guideline: {$s['when']}"
                . ($s['state'] === 'unknown' ? " · ask: \"{$s['ask']}\"" : '');
        }
        $out['Care routines'] = implode("\n", $lines);
    }
    $t = rrp_treatments_prompt_block($petId);
    if ($t) {
        $out['Under vet treatment'] = $t;
    }
    $labs = rrp_labs_prompt_block($petId);
    if ($labs) {
        $out['Lab results (latest per test)'] = $labs;
    }
    $out['Daily life'] = rrp_daily_prompt_block($owner, $pet);
    if (in_array($pet['type'] ?? '', ['dog', 'cat'], true) || rrp_foods_for_pet($petId)) {
        $out['Food'] = rrp_food_prompt_block($owner, $pet);
    }
    return $out;
}

function rrp_sipo_owner_nickname(array $owner): string
{
    $n = trim((string) ($owner['nickname'] ?? ''));
    if ($n === '') {
        $n = trim((string) ($owner['name'] ?? ''));
    }
    return $n !== '' ? $n : 'friend';
}

function rrp_sipo_pet_card(array $pet): string
{
    $rows = [
        'Name' => $pet['name'] ?? null,
        'Kind' => rrp_pet_type_label($pet),
        'Breed' => $pet['breed'] ?? null,
        'Sex' => ($pet['sex'] ?? 'unknown') !== 'unknown' ? $pet['sex'] : null,
        'Birthday' => $pet['birth_date'] ?? null,
        'Age' => !empty($pet['age_years']) ? rtrim(rtrim((string) $pet['age_years'], '0'), '.') . ' years' : null,
        'Weight' => !empty($pet['weight_kg']) ? rtrim(rtrim((string) $pet['weight_kg'], '0'), '.') . ' kg' : null,
        'Neutered' => isset($pet['neutered']) && $pet['neutered'] !== null ? ((int) $pet['neutered'] === 1 ? 'yes' : 'no') : null,
        'Colour / marks' => trim(($pet['color'] ?? '') . ' ' . ($pet['identifying_marks'] ?? '')) ?: null,
        'Conditions' => $pet['medical_conditions'] ?? null,
        'Vaccines' => $pet['medical_vaccines'] ?? null,
        'Medicines' => $pet['medical_pills'] ?? null,
        'Photo' => !empty($pet['image_path']) ? 'yes' : 'not yet',
    ];
    $progress = rrp_sipo_profile_progress($pet);
    $out = ['Profile progress: ' . $progress['done'] . '/' . $progress['total']
        . ($progress['missing'] ? ' (still to ask, one at a time: ' . implode(', ', $progress['missing']) . ')' : ' complete 🎉')];
    $missing = [];
    foreach ($rows as $k => $v) {
        if ($v === null || $v === '') {
            $missing[] = strtolower($k);
            continue;
        }
        $out[] = $k . ': ' . $v;
    }
    if ($missing) {
        $out[] = 'Unknown so far: ' . implode(', ', $missing);
    }
    return implode("\n", $out);
}

/**
 * How complete the pet's profile is (shown to the owner and given to SIPO).
 * @return array{done:int, total:int, percent:int, missing:list<string>}
 */
function rrp_sipo_profile_progress(array $pet): array
{
    $items = [
        'breed' => !empty($pet['breed']),
        'age or birthday' => !empty($pet['birth_date']) || (isset($pet['age_years']) && $pet['age_years'] !== null && $pet['age_years'] !== ''),
        'sex' => ($pet['sex'] ?? 'unknown') !== 'unknown',
        'weight' => !empty($pet['weight_kg']),
        'neutered or not' => isset($pet['neutered']) && $pet['neutered'] !== null,
        'a photo' => !empty($pet['image_path']),
    ];
    $vax = false;
    try {
        $st = rrp_db()->prepare("SELECT COUNT(*) FROM care_reminders WHERE pet_id = ? AND kind = 'routine' AND merge_group = 'vaccine'");
        $st->execute([(int) $pet['id']]);
        $vax = (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) {
    }
    $items['last vaccine date'] = $vax;
    $done = count(array_filter($items));
    return [
        'done' => $done,
        'total' => count($items),
        'percent' => (int) round(100 * $done / count($items)),
        'missing' => array_keys(array_filter($items, static fn($v) => !$v)),
    ];
}

/** @return list<string> */
function rrp_sipo_upcoming_reminders(int $petId): array
{
    try {
        $stmt = rrp_db()->prepare(
            "SELECT title, due_at, status FROM care_reminders
             WHERE pet_id = ? AND status IN ('pending_approval','scheduled','sent','snoozed')
             ORDER BY due_at ASC LIMIT 6"
        );
        $stmt->execute([$petId]);
        return array_map(
            static fn(array $r) => '- ' . substr((string) $r['due_at'], 0, 16) . ' · ' . $r['title'] . ' (' . $r['status'] . ')',
            $stmt->fetchAll()
        );
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Store a new live core prompt; previous versions stay for rollback.
 * @return array{ok:bool, id?:int, error?:string}
 */
function rrp_sipo_publish_core_prompt(string $prompt, string $label, string $createdBy = 'human', ?string $notes = null): array
{
    $prompt = trim($prompt);
    if ($prompt === '') {
        return ['ok' => false, 'error' => 'Prompt is empty.'];
    }
    try {
        $pdo = rrp_db();
        $pdo->beginTransaction();
        $pdo->exec("UPDATE si_prompt_versions SET is_live = 0, replaced_at = UTC_TIMESTAMP() WHERE layer = 'core' AND is_live = 1");
        $stmt = $pdo->prepare(
            "INSERT INTO si_prompt_versions (version_label, layer, system_prompt, is_live, notes, created_by) VALUES (?, 'core', ?, 1, ?, ?)"
        );
        $stmt->execute([mb_substr($label, 0, 40), $prompt, $notes, mb_substr($createdBy, 0, 40)]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return ['ok' => true, 'id' => $id];
    } catch (Throwable $e) {
        if (rrp_db()->inTransaction()) {
            rrp_db()->rollBack();
        }
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not save prompt')];
    }
}

/** Roll back: make an older version live again. */
function rrp_sipo_rollback_core_prompt(int $versionId): array
{
    try {
        $pdo = rrp_db();
        $chk = $pdo->prepare("SELECT id FROM si_prompt_versions WHERE id = ? AND layer = 'core'");
        $chk->execute([$versionId]);
        if (!$chk->fetch()) {
            return ['ok' => false, 'error' => 'Version not found.'];
        }
        $pdo->beginTransaction();
        $pdo->exec("UPDATE si_prompt_versions SET is_live = 0, replaced_at = UTC_TIMESTAMP() WHERE layer = 'core' AND is_live = 1");
        $pdo->prepare('UPDATE si_prompt_versions SET is_live = 1, replaced_at = NULL WHERE id = ?')->execute([$versionId]);
        $pdo->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        if (rrp_db()->inTransaction()) {
            rrp_db()->rollBack();
        }
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not roll back')];
    }
}
