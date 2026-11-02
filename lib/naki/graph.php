<?php
declare(strict_types=1);

/**
 * NAKI graph: SIPO at the core; topics around it; request groups (KNN clusters of similar owner requests);
 * the issues seen in them; the prompt rules; the tests. Everything here is aggregated or redacted:
 * no owner ids, no names, no contact details.
 *
 * Grouping is local and free: TF-IDF vectors, cosine similarity, each request linked to its k nearest
 * neighbours inside the same topic, groups = communities of that neighbour graph.
 */

const RRP_GRAPH_K = 5;
const RRP_GRAPH_MIN_SIM = 0.22;
const RRP_GRAPH_MAX_ROWS = 1500;

const RRP_GRAPH_STOP = ['the','and','for','with','that','this','you','your','what','when','how','can','are','was','were','has','have','had','not','but','does','did','she','her','him','his','they','them','its','from','about','just','please','would','could','should','will','get','got','any','all','too','very','also','into','out','off','than','then','there','here','now','one','some','more','much','like','want','need','tell','let','know','make','sure','yes','yeah','okay','thanks','thank','hello','name'];

/** @return list<string> */
function rrp_graph_tokens(string $text): array
{
    $t = mb_strtolower($text);
    $t = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $t) ?? $t;
    $out = [];
    foreach (preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
        if (mb_strlen($w) < 3 || in_array($w, RRP_GRAPH_STOP, true) || ctype_digit($w)) {
            continue;
        }
        // Very light stemming so "vaccines/vaccine", "walking/walk", "vomited/vomit" meet.
        $stem = preg_replace('/(ing|ed|s)$/u', '', $w) ?? $w;
        if (mb_strlen($stem) >= 3) {
            $out[] = $stem;
            $GLOBALS['rrp_graph_orig'][$stem][$w] = ($GLOBALS['rrp_graph_orig'][$stem][$w] ?? 0) + 1;
        }
    }
    return $out;
}

/**
 * Group requests: returns [requestId => clusterKey] and cluster info.
 *
 * @param list<array{id:int,domain:string,req_text:string}> $rows
 * @return array{assign:array<int,string>, clusters:array<string,array{domain:string,members:list<int>,terms:list<string>}>}
 */
function rrp_graph_cluster(array $rows, int $k = RRP_GRAPH_K, float $minSim = RRP_GRAPH_MIN_SIM): array
{
    $GLOBALS['rrp_graph_orig'] = [];
    $docs = [];
    $df = [];
    foreach ($rows as $r) {
        $tf = array_count_values(rrp_graph_tokens((string) $r['req_text']));
        $docs[(int) $r['id']] = $tf;
        foreach (array_keys($tf) as $term) {
            $df[$term] = ($df[$term] ?? 0) + 1;
        }
    }
    $n = max(1, count($docs));
    $vec = [];
    $norm = [];
    foreach ($docs as $id => $tf) {
        $v = [];
        $s = 0.0;
        foreach ($tf as $term => $c) {
            $w = (1 + log($c)) * log(1 + $n / $df[$term]);
            $v[$term] = $w;
            $s += $w * $w;
        }
        $vec[$id] = $v;
        $norm[$id] = sqrt($s) ?: 1.0;
    }
    // Inverted index: only documents that share a term are ever compared.
    $inv = [];
    foreach ($vec as $id => $v) {
        foreach (array_keys($v) as $term) {
            $inv[$term][] = $id;
        }
    }
    $domain = [];
    foreach ($rows as $r) {
        $domain[(int) $r['id']] = (string) $r['domain'];
    }
    $neigh = [];
    foreach ($vec as $id => $v) {
        $score = [];
        foreach ($v as $term => $w) {
            if (count($inv[$term]) > 400) {
                continue; // a term in most documents says nothing
            }
            foreach ($inv[$term] as $other) {
                if ($other === $id || $domain[$other] !== $domain[$id]) {
                    continue;
                }
                $score[$other] = ($score[$other] ?? 0.0) + $w * ($vec[$other][$term] ?? 0.0);
            }
        }
        $best = [];
        foreach ($score as $other => $dot) {
            $sim = $dot / ($norm[$id] * $norm[$other]);
            if ($sim >= $minSim) {
                $best[$other] = $sim;
            }
        }
        arsort($best);
        $neigh[$id] = array_slice($best, 0, $k, true);
    }
    // Label propagation over the neighbour graph (symmetric weights), a few rounds.
    $label = array_combine(array_keys($vec), array_keys($vec));
    $adj = [];
    foreach ($neigh as $a => $list) {
        foreach ($list as $b => $s) {
            $adj[$a][$b] = max($adj[$a][$b] ?? 0, $s);
            $adj[$b][$a] = max($adj[$b][$a] ?? 0, $s);
        }
    }
    $ids = array_keys($vec);
    for ($round = 0; $round < 8; $round++) {
        $changed = false;
        foreach ($ids as $id) {
            if (empty($adj[$id])) {
                continue;
            }
            $tally = [];
            foreach ($adj[$id] as $o => $s) {
                $tally[$label[$o]] = ($tally[$label[$o]] ?? 0.0) + $s;
            }
            arsort($tally);
            $top = (int) array_key_first($tally);
            if ($top !== $label[$id]) {
                $label[$id] = $top;
                $changed = true;
            }
        }
        if (!$changed) {
            break;
        }
    }
    // Build clusters; lonely requests of a topic share one "Other" group.
    $byLabel = [];
    foreach ($label as $id => $l) {
        $byLabel[$l][] = $id;
    }
    $clusters = [];
    $assign = [];
    $other = [];
    foreach ($byLabel as $l => $members) {
        $dom = $domain[$members[0]];
        if (count($members) < 2) {
            $other[$dom][] = $members[0];
            continue;
        }
        $key = $dom . ':' . $l;
        $tally = [];
        foreach ($members as $m) {
            foreach ($vec[$m] as $term => $w) {
                $tally[$term] = ($tally[$term] ?? 0.0) + $w;
            }
        }
        arsort($tally);
        // Show the words people actually wrote, not their stems.
        $terms = array_map(static function ($stem) {
            $o = $GLOBALS['rrp_graph_orig'][$stem] ?? [];
            arsort($o);
            return (string) (array_key_first($o) ?? $stem);
        }, array_slice(array_keys($tally), 0, 3));
        $clusters[$key] = ['domain' => $dom, 'members' => $members, 'terms' => $terms];
        foreach ($members as $m) {
            $assign[$m] = $key;
        }
    }
    foreach ($other as $dom => $members) {
        $key = $dom . ':other';
        $clusters[$key] = ['domain' => $dom, 'members' => $members, 'terms' => ['other']];
        foreach ($members as $m) {
            $assign[$m] = $key;
        }
    }
    return ['assign' => $assign, 'clusters' => $clusters];
}

/** Recompute groups for the last 30 days and store them. @return array{requests:int, clusters:int} */
function rrp_graph_rebuild(): array
{
    $db = rrp_db();
    $rows = $db->query(
        'SELECT id, domain, req_text, score, prompt_tokens + completion_tokens AS tok FROM si_request_log
         WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY) AND req_text <> \'\' ORDER BY id DESC LIMIT ' . RRP_GRAPH_MAX_ROWS
    )->fetchAll();
    if (!$rows) {
        return ['requests' => 0, 'clusters' => 0];
    }
    $res = rrp_graph_cluster($rows);
    $byId = [];
    foreach ($rows as $r) {
        $byId[(int) $r['id']] = $r;
    }
    $db->beginTransaction();
    try {
        $db->exec('DELETE FROM si_clusters');
        $db->exec('UPDATE si_request_log SET cluster_id = NULL');
        $ins = $db->prepare('INSERT INTO si_clusters (label, domain, size, avg_score, avg_tokens, terms) VALUES (?, ?, ?, ?, ?, ?)');
        $upd = $db->prepare('UPDATE si_request_log SET cluster_id = ? WHERE id = ?');
        foreach ($res['clusters'] as $c) {
            $scores = array_filter(array_map(static fn($m) => $byId[$m]['score'] ?? null, $c['members']), static fn($v) => $v !== null);
            $tok = array_map(static fn($m) => (int) ($byId[$m]['tok'] ?? 0), $c['members']);
            $label = ($c['terms'] === ['other'] ? 'Other ' . (RRP_DOMAINS[$c['domain']] ?? $c['domain']) : ucfirst(implode(' · ', $c['terms'])));
            $ins->execute([mb_substr($label, 0, 120), $c['domain'], count($c['members']),
                $scores ? round(array_sum($scores) / count($scores), 2) : null, $tok ? (int) round(array_sum($tok) / count($tok)) : 0,
                mb_substr(implode(',', $c['terms']), 0, 250)]);
            $cid = (int) $db->lastInsertId();
            foreach ($c['members'] as $m) {
                $upd->execute([$cid, $m]);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('rrp_graph_rebuild: ' . $e->getMessage());
        return ['requests' => count($rows), 'clusters' => 0];
    }
    return ['requests' => count($rows), 'clusters' => count($res['clusters'])];
}

/** What to do about an issue, in one line. */
const RRP_GRAPH_FIX = [
    'multiple_questions' => 'Prompt: one question per message. Add an exam case.',
    'dash' => 'Prompt: no long dashes in replies.',
    'too_long' => 'Prompt: keep replies under 90 words unless asked.',
    'false_claim' => 'Code check: only say Done after a tool saved it (verify against the database).',
    'capability_denial' => 'Prompt: SIPO can set reminders itself; never say it cannot.',
    'repeat_question' => 'Memory: read saved facts before asking; add to the pet card.',
    'tool_error' => 'Code: a tool refused or failed; read the tool message and fix the rule.',
    'token_heavy' => 'Tokens: route fewer tools and blocks for this topic, or shorten the history.',
    'off_scope' => 'Prompt: restate the scope rule with an example.',
    'unsafe' => 'Prompt + code: stronger safety rule and a test.',
    'wrong_time' => 'Code: check the greeting or time against the owner clock.',
    'cold_tone' => 'Prompt: warmer opening line.',
    'missed_emergency' => 'Prompt + code: force the emergency path for this wording.',
];

/** @return array{nodes:list<array>, links:list<array>} */
function rrp_graph_build(): array
{
    $db = rrp_db();
    $nodes = [];
    $links = [];
    $avg = (float) ($db->query("SELECT AVG(score) FROM si_request_log WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)")->fetchColumn() ?: 0);
    $total = (int) $db->query("SELECT COUNT(*) FROM si_request_log WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)")->fetchColumn();
    $nodes[] = ['id' => 'core', 'type' => 'core', 'label' => 'SIPO', 'size' => max(1, $total), 'score' => $total ? round($avg, 2) : null];

    $dom = $db->query(
        "SELECT domain, COUNT(*) n, AVG(score) s, AVG(prompt_tokens + completion_tokens) t FROM si_request_log
         WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY) GROUP BY domain"
    )->fetchAll();
    $seenDomain = [];
    foreach ($dom as $d) {
        $seenDomain[$d['domain']] = true;
        $nodes[] = ['id' => 'd:' . $d['domain'], 'type' => 'domain', 'label' => RRP_DOMAINS[$d['domain']] ?? $d['domain'],
            'size' => (int) $d['n'], 'score' => round((float) $d['s'], 2), 'tokens' => (int) round((float) $d['t'])];
        $links[] = ['source' => 'core', 'target' => 'd:' . $d['domain']];
    }
    foreach ($db->query('SELECT id, label, domain, size, avg_score, avg_tokens FROM si_clusters ORDER BY size DESC LIMIT 60')->fetchAll() as $c) {
        if (!isset($seenDomain[$c['domain']])) {
            continue;
        }
        $nodes[] = ['id' => 'c:' . $c['id'], 'type' => 'cluster', 'label' => $c['label'], 'size' => (int) $c['size'],
            'score' => $c['avg_score'] !== null ? (float) $c['avg_score'] : null, 'tokens' => (int) $c['avg_tokens']];
        $links[] = ['source' => 'd:' . $c['domain'], 'target' => 'c:' . $c['id']];
    }
    // Issues, linked to the groups where they occur most.
    $iss = [];
    foreach ($db->query("SELECT cluster_id, issues FROM si_request_log WHERE issues <> '' AND cluster_id IS NOT NULL AND created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)")->fetchAll() as $r) {
        foreach (explode(',', (string) $r['issues']) as $i) {
            if ($i !== '') {
                $iss[$i]['n'] = ($iss[$i]['n'] ?? 0) + 1;
                $iss[$i]['c'][(int) $r['cluster_id']] = ($iss[$i]['c'][(int) $r['cluster_id']] ?? 0) + 1;
            }
        }
    }
    $clusterIds = array_flip(array_map(static fn($n) => $n['id'], $nodes));
    foreach ($iss as $name => $info) {
        $nodes[] = ['id' => 'i:' . $name, 'type' => 'issue', 'label' => str_replace('_', ' ', $name), 'size' => $info['n'], 'score' => null];
        arsort($info['c']);
        foreach (array_slice($info['c'], 0, 3, true) as $cid => $cnt) {
            if (isset($clusterIds['c:' . $cid])) {
                $links[] = ['source' => 'c:' . $cid, 'target' => 'i:' . $name];
            }
        }
    }
    // Rules: the sections of SIPO's core instructions.
    if (function_exists('rrp_sipo_core_prompt') && preg_match_all('/^# (.+)$/m', rrp_sipo_core_prompt(), $m)) {
        foreach (array_slice($m[1], 0, 14) as $i => $h) {
            $nodes[] = ['id' => 'r:' . $i, 'type' => 'rule', 'label' => trim($h), 'size' => 1, 'score' => null];
            $links[] = ['source' => 'core', 'target' => 'r:' . $i];
        }
    }
    // Tests per topic.
    foreach ($db->query("SELECT domain, COUNT(*) n, SUM(last_pass = 1) p, SUM(last_pass = 0) f FROM si_tests WHERE active = 1 GROUP BY domain")->fetchAll() as $t) {
        $ran = (int) $t['p'] + (int) $t['f'];
        $nodes[] = ['id' => 't:' . $t['domain'], 'type' => 'test', 'label' => 'Tests · ' . (RRP_DOMAINS[$t['domain']] ?? $t['domain']), 'size' => (int) $t['n'],
            'score' => $ran ? round(5 * (int) $t['p'] / $ran, 2) : null];
        $links[] = ['source' => isset($seenDomain[$t['domain']]) ? 'd:' . $t['domain'] : 'core', 'target' => 't:' . $t['domain']];
    }
    return ['nodes' => $nodes, 'links' => $links];
}

/** Detail of one node for the side panel. @return array<string,mixed> */
function rrp_graph_node(string $id): array
{
    $db = rrp_db();
    [$type, $key] = array_pad(explode(':', $id, 2), 2, '');
    $kinds = ['d' => 'domain', 'c' => 'cluster', 'i' => 'issue', 'r' => 'rule', 't' => 'test'];
    $out = ['id' => $id, 'type' => $kinds[$type] ?? 'core'];
    $examples = static function (string $where, array $args) use ($db): array {
        $st = $db->prepare("SELECT id, req_text, score, issues, prompt_tokens + completion_tokens AS tok, rounds, tools_used, marked, source FROM si_request_log WHERE {$where} ORDER BY (score IS NULL), score ASC, id DESC LIMIT 12");
        $st->execute($args);
        return array_map(static fn($r) => [
            'id' => (int) $r['id'], 'text' => (string) $r['req_text'], 'score' => $r['score'] !== null ? (float) $r['score'] : null,
            'issues' => $r['issues'] !== '' ? explode(',', (string) $r['issues']) : [], 'tokens' => (int) $r['tok'],
            'rounds' => (int) $r['rounds'], 'tools' => (string) $r['tools_used'], 'marked' => (bool) $r['marked'], 'source' => (string) $r['source'],
        ], $st->fetchAll());
    };
    $improve = static function (array $examples): array {
        $count = [];
        $tok = 0;
        foreach ($examples as $e) {
            foreach ($e['issues'] as $i) {
                $count[$i] = ($count[$i] ?? 0) + 1;
            }
            $tok += $e['tokens'];
        }
        arsort($count);
        $tips = [];
        foreach (array_slice($count, 0, 3, true) as $i => $n) {
            $tips[] = ['issue' => $i, 'count' => $n, 'fix' => RRP_GRAPH_FIX[$i] ?? 'Review these replies and add a test.'];
        }
        if (!$tips && $examples) {
            $tips[] = ['issue' => 'none', 'count' => 0, 'fix' => 'No issue found in these. To raise the score, add tests with harder wording.'];
        }
        return $tips;
    };
    if ($type === 'd') {
        $out['label'] = RRP_DOMAINS[$key] ?? $key;
        $st = $db->prepare('SELECT COUNT(*) n, AVG(score) s, AVG(prompt_tokens + completion_tokens) t, AVG(rounds) r FROM si_request_log WHERE domain = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)');
        $st->execute([$key]);
        $a = $st->fetch();
        $out += ['count' => (int) $a['n'], 'score' => $a['s'] !== null ? round((float) $a['s'], 2) : null, 'tokens' => (int) round((float) $a['t']), 'rounds' => round((float) $a['r'], 1)];
        $out['examples'] = $examples('domain = ?', [$key]);
        $out['improve'] = $improve($out['examples']);
    } elseif ($type === 'c') {
        $st = $db->prepare('SELECT * FROM si_clusters WHERE id = ?');
        $st->execute([(int) $key]);
        $c = $st->fetch() ?: [];
        $out += ['label' => $c['label'] ?? 'Group', 'count' => (int) ($c['size'] ?? 0), 'score' => isset($c['avg_score']) ? (float) $c['avg_score'] : null,
            'tokens' => (int) ($c['avg_tokens'] ?? 0), 'domain' => $c['domain'] ?? ''];
        $out['examples'] = $examples('cluster_id = ?', [(int) $key]);
        $out['improve'] = $improve($out['examples']);
    } elseif ($type === 'i') {
        $out['label'] = str_replace('_', ' ', $key);
        $out['examples'] = $examples('FIND_IN_SET(?, issues)', [$key]);
        $out['improve'] = [['issue' => $key, 'count' => count($out['examples']), 'fix' => RRP_GRAPH_FIX[$key] ?? 'Review and add a test.']];
        $out['count'] = (int) $db->query("SELECT COUNT(*) FROM si_request_log WHERE FIND_IN_SET(" . $db->quote($key) . ", issues)")->fetchColumn();
    } elseif ($type === 't') {
        $st = $db->prepare('SELECT id, ask, last_pass, last_score, last_reply, source FROM si_tests WHERE domain = ? AND active = 1 ORDER BY (last_pass IS NULL), last_pass ASC, id DESC LIMIT 20');
        $st->execute([$key]);
        $out['label'] = 'Tests · ' . (RRP_DOMAINS[$key] ?? $key);
        $out['tests'] = $st->fetchAll();
        $out['count'] = count($out['tests']);
    } elseif ($type === 'r') {
        $heads = [];
        if (preg_match_all('/^# (.+)$/m', rrp_sipo_core_prompt(), $m)) {
            $heads = $m[1];
        }
        $out['label'] = $heads[(int) $key] ?? 'Rule';
        $out['count'] = 1;
        if (preg_match('/^# ' . preg_quote((string) ($heads[(int) $key] ?? ''), '/') . '\R(.*?)(?=^# |\z)/ms', rrp_sipo_core_prompt(), $mm)) {
            $out['text'] = trim($mm[1]);
        }
    } else {
        $out['label'] = 'SIPO';
        $out['count'] = (int) $db->query("SELECT COUNT(*) FROM si_request_log WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)")->fetchColumn();
        $out['score'] = ($s = $db->query("SELECT AVG(score) FROM si_request_log WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)")->fetchColumn()) !== null ? round((float) $s, 2) : null;
        $out['tokens'] = (int) round((float) $db->query("SELECT AVG(prompt_tokens + completion_tokens) FROM si_request_log WHERE source = 'live' AND created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)")->fetchColumn());
    }
    return $out;
}
