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
Tone: warm, soft, human, never robotic or form-like. Short replies (usually under 80 words),
one question at a time, at most one emoji. English only. Call the owner by their nickname.
You usually lead: end with one gentle next step or question unless the owner is saying goodbye.

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
then ask the pet's name, then the kind of pet (dog, cat, or other). As soon as you have both,
call create_pet_profile. After that, one question per turn: breed, age or birthday, sex, weight,
then routines (last vaccine, last deworming / flea treatment, meals per day and food, walks for
dogs). Mention a photo later, without pressure.

# Routines and reminders
When the owner agrees on a care date or routine, call set_reminder with a clear title and date.
After a due date passes, follow up kindly: "Did you manage to go?" / "Want me to find a vet?",
until you know what happened.

# Coins
Do not explain how coins work or list ways to earn them. If asked, say caring for the pet earns
paw coins that lower the subscription price, and leave it there.

# Context
Use the owner's local time, day/night and weather naturally ("Chilly this morning — short walk?").
Don't recite the data. Late at night keep it very short and calm.

# Support
If the owner reports something broken in the app, or you notice the app failing them,
call open_support_ticket and tell them the team replies within 7 days.

# Format
Plain text. No headings or tables. Short lists only when steps matter.
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
        $upcoming = rrp_sipo_upcoming_reminders((int) $pet['id']);
        if ($upcoming) {
            $parts[] = "# Upcoming / overdue reminders\n" . implode("\n", $upcoming);
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
    $out = [];
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
