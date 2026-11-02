<?php
declare(strict_types=1);

/**
 * Basic dog and cat health knowledge SIPO starts with. General guidance from widely used
 * veterinary sources (WSAVA, AAHA, AVMA, ASPCA); it is background, never a diagnosis.
 * The vet's word and the owner's own records always win over these numbers.
 */

const RRP_KNOWLEDGE_DOG = <<<TXT
Normal adult dog, at rest: temperature 38.0–39.2 °C (100.4–102.5 °F); heart rate 60–140 bpm (small dogs and puppies faster); breathing 10–30 breaths a minute (over 40 at rest is worth a call to the vet).
Water: roughly 50–60 ml per kg a day; more in heat, after exercise or on dry food. A sudden big rise or drop in drinking is worth noting and mentioning to the vet.
Bathroom: most adult dogs pee 3–5 times and poop 1–2 times a day. Compare the dog to its own habits. Blood, black tarry stool, diarrhoea over 24 hours, straining, or no pee for 12 hours need the vet.
Food: two meals a day for adults; puppies three or four. Change food slowly over 7 days. Do not feed grapes and raisins, chocolate, xylitol (sugar-free gum), onion, garlic, macadamia nuts, alcohol or cooked bones.
Weight: you should feel the ribs under a thin layer, with a visible waist. Gaining or losing over 10% of body weight in a few months is worth a vet visit.
Teeth: most dogs have dental disease by age 3. Brushing daily with pet toothpaste is best, a few times a week still helps. Bad breath, red gums or drooling can mean dental trouble.
Prevention: core vaccines (distemper, parvo, adenovirus, rabies by law), yearly check-up, flea and tick and worm control as the vet advises, heartworm prevention in mosquito regions.
Ages: puppy to about 1 year; adult 1–7; senior from about 7 (giant breeds from about 5). Seniors benefit from a check-up twice a year.
Emergency, go to a vet now: trouble breathing, collapse, seizure, swollen hard belly with retching, pale or blue gums, heatstroke, known poison, hit by a car, repeated vomiting, cannot pee.
TXT;

const RRP_KNOWLEDGE_CAT = <<<TXT
Normal adult cat, at rest: temperature 38.0–39.2 °C (100.4–102.5 °F); heart rate 140–220 bpm; breathing 20–30 breaths a minute (over 40 at rest, or open-mouth breathing, is an emergency).
Water: roughly 40–50 ml per kg a day, less on wet food. Cats that drink much more than usual, or pee much more, may have kidney disease, diabetes or thyroid disease: tell the vet.
Bathroom: most cats pee 2–4 times and poop about once a day. Straining in the litter box, many tiny pees or crying in the box, especially in males, is an emergency (urinary blockage).
Food: cats are strict meat-eaters. Two or more small meals a day. Never skip eating for more than 24 hours: a cat that stops eating can develop serious liver problems. Do not feed onion, garlic, chocolate, grapes, raisins, alcohol, raw dough, or any dog flea product. Lilies are extremely poisonous to cats.
Weight: ribs should be easy to feel with a slight waist. Weight loss in an older cat, even with a good appetite, can be a sign of thyroid disease or diabetes.
Teeth: dental disease is very common. Brush with pet toothpaste when the cat allows; ask the vet about dental care. Drooling, dropping food or bad breath need a check.
Prevention: core vaccine (FVRCP), rabies where required, FeLV for outdoor cats and kittens, yearly check-up, flea and worm control as the vet advises.
Ages: kitten to about 1 year; adult 1–10; senior from about 10. Seniors benefit from a check-up and blood work twice a year.
Hiding, not grooming, or a change in litter habits is often the first sign that a cat is unwell, because cats hide pain.
Emergency, go to a vet now: trouble breathing, straining to pee, collapse, seizure, not eating for a day, repeated vomiting, known poison (lily, human medicine), hit by a car, pale gums.
TXT;

/** The basics for this pet's species, or null for other animals. */
function rrp_knowledge_for_pet(array $pet): ?string
{
    return match ($pet['type'] ?? '') {
        'dog' => RRP_KNOWLEDGE_DOG,
        'cat' => RRP_KNOWLEDGE_CAT,
        default => null,
    };
}
