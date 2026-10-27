<?php
declare(strict_types=1);

/**
 * Health routine catalogue by species × region (brief §A1).
 * Guideline-level intervals only; the vet sets the exact schedule and products.
 * Researched 2026-10-28 — re-check sources yearly (sources list per row).
 *
 * Regions: na (USA/Canada), eu (Europe + UK), global (WSAVA default).
 */

const RRP_CARE_SOURCES = [
    'wsava' => ['WSAVA 2024 Guidelines for the Vaccination of Dogs and Cats', 'https://wsava.org/global-guidelines/vaccination-guidelines/'],
    'aaha_dog' => ['2022 AAHA Canine Vaccination Guidelines (2024 update)', 'https://www.aaha.org/aaha-guidelines/2022-aaha-canine-vaccination-guidelines/'],
    'aaha_cat' => ['2020 AAHA/AAFP Feline Vaccination Guidelines', 'https://aaha.org/resources/2020-aahaaafp-feline-vaccination-guidelines/'],
    'capc' => ['Companion Animal Parasite Council recommendations', 'https://capcvet.org/articles/capc-recommendations/'],
    'esccap' => ['ESCCAP Guideline 01: Worm control in dogs and cats', 'https://www.esccap.org/guidelines/gl1/'],
    'nutrition' => ['WSAVA Global Nutrition Guidelines', 'https://wsava.org/global-guidelines/global-nutrition-guidelines/'],
    'avma_dental' => ['AVMA: pet dental care', 'https://www.avma.org/resources-tools/pet-owners/petcare/pet-dental-care'],
    'aspca_groom' => ['ASPCA: dog grooming tips', 'https://www.aspca.org/pet-care/dog-care/dog-grooming-tips'],
    'aspca_cat_groom' => ['ASPCA: cat grooming tips', 'https://www.aspca.org/pet-care/cat-care/cat-grooming-tips'],
    'merck_nutrition' => ['Merck Veterinary Manual: nutritional requirements of small animals', 'https://www.merckvetmanual.com/management-and-nutrition/nutrition-small-animals/nutritional-requirements-of-small-animals'],
];

/** Country (ISO-2) → care region. */
function rrp_care_region_for_country(?string $cc): string
{
    $cc = strtoupper(trim((string) $cc));
    if (in_array($cc, ['US', 'CA'], true)) {
        return 'na';
    }
    $eu = ['GB', 'IE', 'FR', 'DE', 'ES', 'PT', 'IT', 'NL', 'BE', 'LU', 'AT', 'CH', 'DK', 'SE', 'NO', 'FI',
        'IS', 'PL', 'CZ', 'SK', 'HU', 'SI', 'HR', 'RO', 'BG', 'GR', 'CY', 'MT', 'EE', 'LV', 'LT'];
    return in_array($cc, $eu, true) ? 'eu' : 'global';
}

/**
 * @return list<array{key:string, title:string, group:string, interval_days:int, when:string, note:string, sources:list<string>, ask:string}>
 */
function rrp_care_catalog(string $species, string $region, ?string $lifestyle = null): array
{
    $isDog = $species === 'dog';
    $isCat = $species === 'cat';
    $rows = [];

    if ($isDog) {
        $rows[] = [
            'key' => 'dog_core_dhpp', 'group' => 'vaccine', 'title' => 'Core vaccine (distemper, adenovirus, parvovirus)',
            'interval_days' => 1095, 'when' => 'Adults: usually every 3 years after the first-year booster',
            'note' => 'Puppies need a series; your vet sets the schedule.',
            'sources' => $region === 'na' ? ['aaha_dog'] : ['wsava'],
            'ask' => 'When did he last have his core (DHPP / DA2PP) vaccine?',
        ];
        $rows[] = [
            'key' => 'dog_rabies', 'group' => 'vaccine', 'title' => 'Rabies vaccine',
            'interval_days' => 365, 'when' => 'Every 1 or 3 years, depending on the vaccine and local law',
            'note' => 'Required by law in many places. Use the date on the certificate.',
            'sources' => $region === 'na' ? ['aaha_dog'] : ['wsava'],
            'ask' => 'When was the last rabies shot? The certificate shows the next due date too.',
        ];
        $rows[] = [
            'key' => 'dog_lepto', 'group' => 'vaccine', 'title' => 'Leptospirosis vaccine',
            'interval_days' => 365, 'when' => 'Every year',
            'note' => $region === 'na' ? 'Core for all dogs in North America since the 2024 AAHA update.' : 'Recommended where leptospirosis is common — most of Europe.',
            'sources' => $region === 'na' ? ['aaha_dog'] : ['wsava'],
            'ask' => 'Has he had the leptospirosis vaccine in the last year?',
        ];
        $rows[] = [
            'key' => 'dog_kennel_cough', 'group' => 'vaccine', 'title' => 'Kennel cough (Bordetella)',
            'interval_days' => 365, 'when' => 'Every year if he meets other dogs, boards or goes to daycare',
            'note' => 'Optional — depends on lifestyle.',
            'sources' => $region === 'na' ? ['aaha_dog'] : ['wsava'],
            'ask' => 'Does he go to daycare, boarding or dog parks? If so, when was his kennel cough vaccine?',
        ];
        $rows[] = [
            'key' => 'dog_worming', 'group' => 'parasite', 'title' => 'Worming (intestinal parasites)',
            'interval_days' => $region === 'na' ? 30 : 90, 'when' => $region === 'na' ? 'Monthly, year-round (often in the heartworm product)' : 'At least 4 times a year when risk is unclear',
            'note' => 'More often if he eats raw food, hunts or scavenges.',
            'sources' => $region === 'na' ? ['capc'] : ['esccap'],
            'ask' => 'When was he last dewormed?',
        ];
        $rows[] = [
            'key' => 'dog_flea_tick', 'group' => 'parasite', 'title' => 'Flea and tick prevention',
            'interval_days' => 30, 'when' => $region === 'na' ? 'Year-round' : 'Year-round or in the season your vet advises',
            'note' => 'Interval depends on the product (monthly, 3-monthly, collar).',
            'sources' => $region === 'na' ? ['capc'] : ['esccap'],
            'ask' => 'When did he last get flea and tick protection, and what kind (pill, spot-on, collar)?',
        ];
        if ($region === 'na') {
            $rows[] = [
                'key' => 'dog_heartworm', 'group' => 'parasite', 'title' => 'Heartworm prevention + yearly test',
                'interval_days' => 30, 'when' => 'Prevention monthly year-round; test once a year',
                'note' => 'Heartworm is spread by mosquitoes across North America.',
                'sources' => ['capc'],
                'ask' => 'Is he on a heartworm preventive? When was the last dose?',
            ];
        }
    }

    if ($isCat) {
        $rows[] = [
            'key' => 'cat_core_fvrcp', 'group' => 'vaccine', 'title' => 'Core vaccine (FVRCP: herpes, calici, panleukopenia)',
            'interval_days' => 1095, 'when' => 'Adults: every 1–3 years depending on the vaccine and lifestyle',
            'note' => 'Kittens need a series; your vet sets the schedule.',
            'sources' => $region === 'na' ? ['aaha_cat'] : ['wsava'],
            'ask' => 'When did she last have her FVRCP (core) vaccine?',
        ];
        $rows[] = [
            'key' => 'cat_rabies', 'group' => 'vaccine', 'title' => 'Rabies vaccine',
            'interval_days' => 365, 'when' => 'Every 1 or 3 years, depending on the vaccine and local law',
            'note' => 'Core where rabies is present or required by law.',
            'sources' => $region === 'na' ? ['aaha_cat'] : ['wsava'],
            'ask' => 'When was her last rabies shot?',
        ];
        if ($lifestyle !== 'indoor') {
            $rows[] = [
                'key' => 'cat_felv', 'group' => 'vaccine', 'title' => 'FeLV (feline leukaemia) vaccine',
                'interval_days' => 365, 'when' => 'Core for kittens under 1 year; adults if they go outside or live with outdoor cats',
                'note' => 'Your vet decides how often after the first year.',
                'sources' => $region === 'na' ? ['aaha_cat'] : ['wsava'],
                'ask' => 'Does she go outside? Has she had the FeLV vaccine?',
            ];
        }
        $rows[] = [
            'key' => 'cat_worming', 'group' => 'parasite', 'title' => 'Worming (intestinal parasites)',
            'interval_days' => $lifestyle === 'indoor' ? 180 : ($region === 'na' ? 30 : 90),
            'when' => $lifestyle === 'indoor' ? 'Indoor-only: 1–2 times a year' : ($region === 'na' ? 'Monthly, year-round' : '4 times a year (more if she hunts)'),
            'note' => 'Hunting cats may need it monthly.',
            'sources' => $region === 'na' ? ['capc'] : ['esccap'],
            'ask' => 'When was she last dewormed?',
        ];
        $rows[] = [
            'key' => 'cat_flea', 'group' => 'parasite', 'title' => 'Flea prevention',
            'interval_days' => 30, 'when' => 'Year-round, interval depends on the product',
            'note' => 'Never use dog flea products on cats — some are toxic to cats.',
            'sources' => $region === 'na' ? ['capc'] : ['esccap'],
            'ask' => 'When did she last get flea protection?',
        ];
    }

    // Grooming, teeth, baths (brief §C3). Owner can change any interval with SIPO.
    if ($isDog || $isCat) {
        $him = $isDog ? 'his' : 'her';
        $rows[] = [
            'key' => $species . '_teeth', 'group' => 'hygiene', 'title' => 'Tooth brushing',
            'interval_days' => 1, 'when' => 'Daily is best; a few times a week still helps',
            'note' => 'Use pet toothpaste — human toothpaste is not safe for pets.',
            'sources' => ['avma_dental'],
            'ask' => "Do you brush {$him} teeth? How often would be realistic for you?",
        ];
        $rows[] = [
            'key' => $species . '_nails', 'group' => 'hygiene', 'title' => 'Nail trim',
            'interval_days' => $isDog ? 28 : 14, 'when' => $isDog ? 'Every 3–4 weeks, or when nails click on the floor' : 'Every 10–14 days',
            'note' => 'Avoid the pink quick; ask the vet or a groomer to show you once.',
            'sources' => [$isDog ? 'aspca_groom' : 'aspca_cat_groom'],
            'ask' => 'When were the nails last trimmed?',
        ];
        $rows[] = [
            'key' => $species . '_coat', 'group' => 'hygiene', 'title' => 'Coat brushing',
            'interval_days' => 7, 'when' => 'Weekly for short coats; long coats several times a week',
            'note' => 'Brushing is also a good moment to check the skin, lumps and ticks.',
            'sources' => [$isDog ? 'aspca_groom' : 'aspca_cat_groom'],
            'ask' => 'How often does the coat get brushed?',
        ];
        if ($isDog) {
            $rows[] = [
                'key' => 'dog_bath', 'group' => 'hygiene', 'title' => 'Bath',
                'interval_days' => 90, 'when' => 'About every 3 months; more if he gets dirty or has a skin condition',
                'note' => 'Bathing too often can irritate the skin. Use a dog shampoo.',
                'sources' => ['aspca_groom'],
                'ask' => 'When did he last have a bath?',
            ];
            $rows[] = [
                'key' => 'dog_ears', 'group' => 'hygiene', 'title' => 'Ear check',
                'interval_days' => 7, 'when' => 'Weekly look and sniff; clean only if your vet advises',
                'note' => 'Redness, smell or head shaking → vet.',
                'sources' => ['aspca_groom'],
                'ask' => 'Do you check his ears regularly?',
            ];
        }
    }

    // Every species
    $rows[] = [
        'key' => $species . '_checkup', 'group' => 'checkup', 'title' => 'Vet health check',
        'interval_days' => 365, 'when' => 'Once a year (twice for seniors)',
        'note' => 'The yearly visit is more than shots: teeth, weight, heart, blood work when needed.',
        'sources' => ['wsava'],
        'ask' => 'When was the last full vet check-up?',
    ];
    return $rows;
}

/** Source list for display: [[label, url], …] */
function rrp_care_sources(array $keys): array
{
    $out = [];
    foreach ($keys as $k) {
        if (isset(RRP_CARE_SOURCES[$k])) {
            $out[] = RRP_CARE_SOURCES[$k];
        }
    }
    return $out;
}
