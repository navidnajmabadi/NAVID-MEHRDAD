<?php
declare(strict_types=1);

/**
 * Minimal marketing blog posts for the public SIPAW site.
 * No DB — placeholder content only.
 */

function rrp_blog_posts(): array
{
    return [
        [
            'slug' => 'meet-sipo',
            'title' => 'Meet SIPO, your pet\'s SI companion',
            'date' => '2026-10-20',
            'excerpt' => 'SIPO remembers breed, habits, and health notes so you never retype the same story in every chat.',
            'body' => <<<'HTML'
<p>SIPO is the SI assistant inside SIPAW. Ask about meals, meds, or “is this normal?” and get a clear answer grounded in your pet’s profile.</p>
<p>Profiles are short and useful — breed, allergies, the fact that Milo hates baths — so Super Intelligence actually has something to work with.</p>
<p>Ready to try it? Register, add your first pet, and say hello to SIPO.</p>
HTML,
        ],
        [
            'slug' => 'reminders-that-stick',
            'title' => 'Reminders that stick (without the guilt trip)',
            'date' => '2026-10-18',
            'excerpt' => 'Vaccines, pills, and meals — SIPO nudges you the day before and on the day. Nudges beat guilt trips.',
            'body' => <<<'HTML'
<p>Pet care fails when the calendar lives in your head. SIPAW keeps walks, meds, and check-ins in one place.</p>
<p>SIPO writes them down, then reminds you on time. Your cat can still judge. We just make sure the flea meds happen.</p>
HTML,
        ],
        [
            'slug' => 'boards-not-doomscroll',
            'title' => 'Owner boards, not doomscroll',
            'date' => '2026-10-15',
            'excerpt' => 'Species tips from other owners. Social lite for people with lint rollers — no endless feed.',
            'body' => <<<'HTML'
<p>Sometimes you need another beagle owner, not another algorithm. SIPAW boards are short threads by breed and life stage.</p>
<p>Ask, share a tip, move on. SIPO stays available when you want Super Intelligence instead of a thread.</p>
HTML,
        ],
    ];
}

function rrp_blog_by_slug(string $slug): ?array
{
    foreach (rrp_blog_posts() as $post) {
        if (($post['slug'] ?? '') === $slug) {
            return $post;
        }
    }
    return null;
}

function rrp_blog_format_date(string $ymd): string
{
    $ts = strtotime($ymd);
    if ($ts === false) {
        return $ymd;
    }
    return date('j F Y', $ts);
}
