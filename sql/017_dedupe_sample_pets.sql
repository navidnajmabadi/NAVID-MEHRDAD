-- QA v3 B03: "Fill sample data" pressed several times left Rocky x3 and Mochi x2 on one account,
-- splitting Finance/Spending totals. Keep the newest sample Rocky and Mochi per owner, delete the older
-- copies (pet rows cascade to their chats, records, posts and spending). Real pets are not matched:
-- only the exact sample name + breed pairs created by the admin sample tool.
DELETE p FROM pets p
JOIN pets newer ON newer.owner_id = p.owner_id AND newer.name = p.name AND newer.breed = p.breed AND newer.id > p.id
WHERE (p.name = 'Rocky' AND p.breed = 'Golden Retriever') OR (p.name = 'Mochi' AND p.breed = 'Scottish Fold');
