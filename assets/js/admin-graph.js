/* NAKI brain graph: force layout with d3, shapes by node kind, colour by score, click for detail. */
(function () {
  "use strict";
  var svgEl = document.getElementById("grSvg"), panel = document.getElementById("grPanel");
  var listBtn = document.getElementById("grList"), listBox = document.getElementById("grListBox");
  if (!svgEl || !window.d3) return;
  var cfg = window.GRAPH, nodes = [], links = [], picked = null;
  var KIND = { core: "Core", domain: "Topic", cluster: "Request group", issue: "Issue", rule: "Rule", test: "Tests" };
  var NAMEKEY = { d: "d", c: "c", i: "i", r: "r", t: "t" };

  function esc(s) { var d = document.createElement("div"); d.textContent = s == null ? "" : String(s); return d.innerHTML; }
  function colour(n) {
    if (n.score == null) return "#8a93a6";
    return n.score < 3 ? "#d6453d" : (n.score < 4 ? "#d98e04" : "#2da44e");
  }
  function radius(n) {
    if (n.type === "core") return 34;
    if (n.type === "domain") return 14 + Math.min(16, Math.sqrt(n.size || 1) * 2.4);
    if (n.type === "cluster") return 8 + Math.min(14, Math.sqrt(n.size || 1) * 2.2);
    return 8 + Math.min(10, Math.sqrt(n.size || 1) * 1.6);
  }
  function shape(n) {
    var r = radius(n), s = d3.symbol().size(Math.PI * r * r * 1.1);
    switch (n.type) {
      case "core": var a = []; for (var i = 0; i < 6; i++) a.push([Math.cos(Math.PI / 3 * i - Math.PI / 2) * r, Math.sin(Math.PI / 3 * i - Math.PI / 2) * r]); return "M" + a.join("L") + "Z";
      case "domain": return s.type(d3.symbolCircle)();
      case "cluster": return s.type(d3.symbolDiamond)();
      case "issue": return s.type(d3.symbolTriangle)();
      case "rule": return s.type(d3.symbolSquare)();
      default: return s.type(d3.symbolStar)();
    }
  }
  function nodeId(n) { return n.id; }

  var width = svgEl.clientWidth || 800, height = svgEl.clientHeight || 600;
  var svg = d3.select(svgEl).attr("viewBox", [0, 0, width, height]);
  var root = svg.append("g");
  svg.call(d3.zoom().scaleExtent([0.35, 3]).on("zoom", function (e) { root.attr("transform", e.transform); }));
  var linkSel, nodeSel, sim;

  function draw() {
    root.selectAll("*").remove();
    linkSel = root.append("g").selectAll("line").data(links).join("line").attr("class", "gr-link");
    nodeSel = root.append("g").selectAll("g").data(nodes, nodeId).join("g")
      .attr("class", "gr-node").attr("data-t", function (n) { return n.type; }).attr("tabindex", 0).attr("role", "button")
      .attr("aria-label", function (n) { return KIND[n.type] + " " + n.label + (n.score != null ? ", score " + n.score : "") + ", " + n.size + " items"; })
      .on("click", function (e, n) { pick(n); })
      .on("keydown", function (e, n) { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); pick(n); } })
      .call(d3.drag().on("start", function (e, n) { if (!e.active) sim.alphaTarget(0.25).restart(); n.fx = n.x; n.fy = n.y; })
        .on("drag", function (e, n) { n.fx = e.x; n.fy = e.y; })
        .on("end", function (e, n) { if (!e.active) sim.alphaTarget(0); n.fx = null; n.fy = null; }));
    nodeSel.append("path").attr("class", "gr-shape").attr("d", shape).attr("fill", colour);
    nodeSel.append("text").attr("class", "gr-label").attr("text-anchor", "middle").attr("dy", function (n) { return radius(n) + 13; })
      .text(function (n) { var t = n.label; return t.length > 22 ? t.slice(0, 21) + "…" : t; });
    nodeSel.append("title").text(function (n) { return KIND[n.type] + ": " + n.label + (n.score != null ? " · score " + n.score : ""); });
    sim = d3.forceSimulation(nodes)
      .force("link", d3.forceLink(links).id(nodeId).distance(function (l) { return l.source.id === "core" || l.target.id === "core" ? 120 : 62; }).strength(0.7))
      .force("charge", d3.forceManyBody().strength(function (n) { return n.type === "core" ? -900 : -160; }))
      .force("center", d3.forceCenter(width / 2, height / 2))
      .force("collide", d3.forceCollide().radius(function (n) { return radius(n) + 8; }))
      .on("tick", function () {
        linkSel.attr("x1", function (l) { return l.source.x; }).attr("y1", function (l) { return l.source.y; }).attr("x2", function (l) { return l.target.x; }).attr("y2", function (l) { return l.target.y; });
        nodeSel.attr("transform", function (n) { return "translate(" + n.x + "," + n.y + ")"; });
      });
    if (matchMedia("(prefers-reduced-motion: reduce)").matches) { sim.stop(); for (var i = 0; i < 220; i++) sim.tick(); sim.alpha(0); linkSel.attr("x1", function (l) { return l.source.x; }).attr("y1", function (l) { return l.source.y; }).attr("x2", function (l) { return l.target.x; }).attr("y2", function (l) { return l.target.y; }); nodeSel.attr("transform", function (n) { return "translate(" + n.x + "," + n.y + ")"; }); }
  }

  function scoreChip(s) {
    if (s == null) return '<span class="ad-chip">no score yet</span>';
    return '<span class="ad-chip ' + (s < 3 ? "is-bad" : s < 4 ? "is-warn" : "is-good") + '">score ' + s + ' / 5</span>';
  }
  function pick(n) {
    picked = n.id;
    if (nodeSel) nodeSel.classed("is-picked", function (m) { return m.id === picked; });
    panel.innerHTML = "<h2>" + esc(n.label) + '</h2><p class="ad-note">Loading…</p>';
    fetch(cfg.data + "?node=" + encodeURIComponent(n.id), { credentials: "same-origin" }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) { panel.innerHTML = "<h2>" + esc(n.label) + '</h2><p class="ad-note">Could not load this node.</p>'; return; }
      render(j.node);
    }).catch(function () { panel.innerHTML = "<h2>" + esc(n.label) + '</h2><p class="ad-note">Network problem. Try again.</p>'; });
  }
  function render(d) {
    var h = "<h2>" + esc(d.label) + '</h2><p class="ad-note" style="margin-top:0">' + esc(KIND[d.type] || KIND.domain) + "</p>";
    h += '<div class="gr-stats">' + scoreChip(d.score);
    if (d.count != null) h += '<span class="ad-chip">' + d.count + " requests</span>";
    if (d.tokens) h += '<span class="ad-chip ' + (d.tokens > 9000 ? "is-warn" : "") + '">' + d.tokens.toLocaleString() + " tokens each</span>";
    if (d.rounds) h += '<span class="ad-chip">' + d.rounds + " model calls each</span>";
    h += "</div>";
    if (d.text) h += '<p class="ad-note" style="white-space:pre-wrap;color:var(--text)">' + esc(d.text) + "</p>";
    if (d.improve && d.improve.length) {
      h += "<h3>How to raise the score</h3>";
      d.improve.forEach(function (t) { h += '<div class="gr-tip"><b>' + esc(String(t.issue).replace(/_/g, " ")) + (t.count ? " · " + t.count + "×" : "") + "</b><br>" + esc(t.fix) + "</div>"; });
    }
    if (d.tests) {
      h += "<h3>Tests</h3>";
      if (!d.tests.length) h += '<p class="ad-note">No tests yet.</p>';
      h += '<ul class="gr-ex">' + d.tests.map(function (t) { return "<li>" + esc(t.ask) + '<div class="meta"><span class="ad-chip ' + (t.last_pass === null ? "" : t.last_pass == 1 ? "is-good" : "is-bad") + '">' + (t.last_pass === null ? "not run" : t.last_pass == 1 ? "pass" : "fail") + "</span><span>" + esc(t.source) + "</span></div></li>"; }).join("") + "</ul>";
      h += '<p style="margin-top:12px"><a class="ad-btn" href="lab.php">Open the test lab</a></p>';
    }
    if (d.examples) {
      h += "<h3>Requests (worst first)</h3>";
      if (!d.examples.length) h += '<p class="ad-note">No requests yet. Talk to SIPO or run the test lab, then regroup.</p>';
      h += '<ul class="gr-ex">' + d.examples.map(function (e) {
        return '<li data-id="' + e.id + '">' + esc(e.text) + '<div class="meta">' + scoreChip(e.score) + "<span>" + e.tokens.toLocaleString() + " tokens</span><span>" + e.rounds + " call" + (e.rounds > 1 ? "s" : "") + "</span>" + (e.source === "test" ? "<span>test</span>" : "") +
          (e.issues.length ? "<span>" + esc(e.issues.join(", ").replace(/_/g, " ")) + "</span>" : "") + '</div><div class="meta"><button type="button" class="ad-btn is-sm" data-act="mark" aria-pressed="' + e.marked + '">' + (e.marked ? "Marked" : "Mark to watch") + '</button><button type="button" class="ad-btn is-sm" data-act="test">Make a test</button></div></li>';
      }).join("") + "</ul>";
    }
    panel.innerHTML = h;
  }
  panel.addEventListener("click", function (e) {
    var b = e.target.closest("button[data-act]"); if (!b) return;
    var li = b.closest("li"), fd = new FormData();
    fd.append("csrf", cfg.csrf); fd.append("act", b.dataset.act); fd.append("id", li.dataset.id);
    if (b.dataset.act === "test") {
      var any = window.prompt("The reply must contain one of these words (separate with | ). Leave empty to skip:", "");
      if (any === null) return;
      var not = window.prompt("The reply must NOT contain (separate with | ). Leave empty to skip:", "");
      if (not === null) return;
      fd.append("must_any", any); fd.append("must_not", not);
    }
    b.disabled = true;
    fetch(cfg.act, { method: "POST", credentials: "same-origin", body: fd }).then(function (r) { return r.json(); }).then(function (j) {
      b.disabled = false;
      if (!j.ok) { b.textContent = j.error || "Failed"; return; }
      if (b.dataset.act === "mark") { b.textContent = j.marked ? "Marked" : "Mark to watch"; b.setAttribute("aria-pressed", j.marked); }
      else { b.textContent = "Test added ✓"; b.disabled = true; }
    }).catch(function () { b.disabled = false; b.textContent = "Network problem"; });
  });

  function renderList() {
    var rows = nodes.slice().sort(function (a, b) { return (a.score == null ? 9 : a.score) - (b.score == null ? 9 : b.score); });
    listBox.innerHTML = '<table class="ad-table"><thead><tr><th>Node</th><th>Kind</th><th class="num">Score</th><th class="num">Items</th></tr></thead><tbody>' +
      rows.map(function (n) { return '<tr><td><button class="ad-btn is-sm" data-pick="' + esc(n.id) + '">' + esc(n.label) + "</button></td><td>" + esc(KIND[n.type]) + '</td><td class="num">' + (n.score == null ? "–" : n.score) + '</td><td class="num">' + n.size + "</td></tr>"; }).join("") + "</tbody></table>";
  }
  listBox.addEventListener("click", function (e) { var b = e.target.closest("[data-pick]"); if (!b) return; var n = nodes.filter(function (m) { return m.id === b.dataset.pick; })[0]; if (n) pick(n); });
  listBtn.addEventListener("click", function () {
    var on = listBox.classList.toggle("is-on"); listBtn.setAttribute("aria-pressed", on); listBtn.textContent = on ? "Hide list" : "Show as list"; if (on) renderList();
  });

  fetch(cfg.data, { credentials: "same-origin" }).then(function (r) { return r.json(); }).then(function (j) {
    if (!j.ok) return;
    nodes = j.nodes; links = j.links;
    if (nodes.length <= 1) { panel.innerHTML = '<h2>No data yet</h2><p class="ad-note">The graph fills as owners talk to SIPO and as tests run. Open the Test lab, seed the known bugs, generate a bulk set and run them.</p><p><a class="ad-btn is-primary" href="lab.php">Open the test lab</a></p>'; }
    draw();
    var core = nodes.filter(function (n) { return n.type === "core"; })[0]; if (core && nodes.length > 1) pick(core);
  });
})();
