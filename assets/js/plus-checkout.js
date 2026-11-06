/* Plus page: live price (server computed), coupon, paw coins, Square card form, auto-renew settings. */
(function () {
  var box = document.getElementById("plusPay");
  var manage = document.getElementById("plusManage");
  var $ = function (id) { return document.getElementById(id); };

  function post(url, body) {
    return fetch(url, { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: "The server did not answer properly. Please try again." }; }); });
  }
  function money(cents, cur) { return cur + " $" + (cents / 100).toFixed(2); }
  function row(label, value, cls) {
    var d = document.createElement("div"); if (cls) d.className = cls;
    var a = document.createElement("dt"); a.textContent = label;
    var b = document.createElement("dd"); b.textContent = value;
    d.appendChild(a); d.appendChild(b); return d;
  }

  // ---- Settings after a card is saved (auto-renew, coins, remove card) ----
  if (manage) {
    var pm = $("pmMsg");
    manage.addEventListener("change", function (e) {
      var w = e.target && e.target.getAttribute("data-manage");
      if (!w) return;
      post("api/billing/manage.php", { csrf: manage.getAttribute("data-csrf"), what: w, on: e.target.checked ? 1 : 0 }).then(function (j) {
        pm.textContent = j.ok ? "Saved." : (j.error || "Could not save that.");
      });
    });
    manage.addEventListener("click", function (e) {
      var b = e.target.closest && e.target.closest("[data-manage=remove_card]");
      if (!b) return;
      if (!window.confirm("Remove the saved card? Auto-renew will switch off.")) return;
      post("api/billing/manage.php", { csrf: manage.getAttribute("data-csrf"), what: "remove_card", on: 0 }).then(function (j) {
        if (j.ok) location.reload(); else pm.textContent = j.error || "Could not remove it.";
      });
    });
  }

  // ---- Payment history: email an invoice PDF ----
  var hist = $("billHistory");
  if (hist) {
    hist.addEventListener("click", function (e) {
      var b = e.target.closest && e.target.closest("[data-email-invoice]");
      if (!b) return;
      var f = hist.querySelector('[data-mail-form="' + b.getAttribute("data-email-invoice") + '"]');
      if (f) { f.hidden = !f.hidden; if (!f.hidden) { var i = f.querySelector("input"); i && i.focus(); } }
    });
    hist.addEventListener("submit", function (e) {
      var f = e.target.closest && e.target.closest("[data-mail-form]");
      if (!f) return;
      e.preventDefault();
      var msgEl = f.querySelector("[data-mail-msg]"), btn = f.querySelector("button[type=submit]");
      btn.disabled = true; msgEl.style.color = ""; msgEl.textContent = "Sending…";
      post("api/billing/invoice-email.php", { csrf: hist.getAttribute("data-csrf"), order_id: f.getAttribute("data-mail-form"), to: f.querySelector("input").value.trim() })
        .then(function (j) {
          btn.disabled = false;
          if (j.ok) { msgEl.textContent = "Sent to " + j.to + " ✓"; }
          else { msgEl.style.color = "var(--red)"; msgEl.textContent = j.error || "Could not send it."; }
        }).catch(function () { btn.disabled = false; msgEl.style.color = "var(--red)"; msgEl.textContent = "Could not reach the server."; });
    });
  }

  if (!box || box.getAttribute("data-ready") !== "1") return;
  var csrf = box.getAttribute("data-csrf");
  var hasCard = box.getAttribute("data-has-card") === "1";
  var useSaved = hasCard;
  var payBtn = $("ppPay"), msg = $("ppMsg"), lines = $("ppLines");
  var coupon = "", quote = null, busy = false, card = null, cardInit = null;

  function setMsg(t, bad) { msg.textContent = t || ""; msg.style.color = bad ? "var(--red)" : ""; }

  function render() {
    var q = quote, cur = q.currency;
    lines.textContent = "";
    lines.appendChild(row("Plus, 1 month", money(q.list, cur)));
    if (q.coupon_discount > 0) lines.appendChild(row("Coupon " + (q.coupon ? q.coupon.code : ""), "−" + money(q.coupon_discount, cur).replace(cur + " ", ""), "pp-minus"));
    if (q.week_discount > 0) lines.appendChild(row("Perfect care weeks (" + q.week_pct + "%)", "−" + money(q.week_discount, cur).replace(cur + " ", ""), "pp-minus"));
    if (q.coin_discount > 0) lines.appendChild(row("Paw coins (" + q.coins_used + " used)", "−" + money(q.coin_discount, cur).replace(cur + " ", ""), "pp-minus"));
    if (q.tax > 0 || q.discount > 0) lines.appendChild(row("Price after discounts", money(q.subtotal, cur)));
    if (q.tax > 0) lines.appendChild(row(q.tax_label + " " + (+q.tax_pct) + "%", "+" + money(q.tax, cur).replace(cur + " ", "")));
    lines.appendChild(row("Total today", money(q.amount, cur), "pp-total"));
    var hint = $("ppCoinsHint");
    if (q.coins_balance <= 0) hint.textContent = "You have no coins yet. Caring for your pet earns them.";
    else if (!$("ppCoins").checked) hint.textContent = "You have " + q.coins_balance + " coins. Tick this to use them.";
    else if (q.coins_used > 0) hint.textContent = q.coins_used + " of your " + q.coins_balance + " coins give " + money(q.coin_discount, cur) + " off. They are spent when you pay.";
    else hint.textContent = "You have " + q.coins_balance + " coins, but the discount limit is already reached.";
    payBtn.disabled = busy;
    payBtn.textContent = q.amount === 0 ? "Start Plus (free)" : (useSaved ? "Pay " + money(q.amount, cur) + " with saved card" : "Pay " + money(q.amount, cur));
  }

  function refresh() {
    return post("api/billing/quote.php", { csrf: csrf, coupon: coupon, use_coins: $("ppCoins").checked ? 1 : 0 }).then(function (j) {
      if (!j.ok) { setMsg(j.error || "Could not get the price.", true); return; }
      quote = j.quote;
      var cm = $("ppCouponMsg");
      if (coupon && j.coupon_ok) cm.textContent = "Code applied ✓";
      else if (coupon) cm.textContent = j.coupon_error || "That code is not valid.";
      else cm.textContent = "";
      render();
    });
  }

  $("ppApply").addEventListener("click", function () {
    coupon = $("ppCoupon").value.trim().toUpperCase();
    refresh();
  });
  $("ppCoupon").addEventListener("keydown", function (e) { if (e.key === "Enter") { e.preventDefault(); $("ppApply").click(); } });
  $("ppCoins").addEventListener("change", refresh);

  var newCard = $("ppNewCard");
  if (newCard) newCard.addEventListener("click", function () {
    useSaved = false;
    $("ppSaved").hidden = true;
    $("ppCardWrap").hidden = false;
    initCard();
    render();
  });

  function initCard() {
    if (cardInit) return cardInit;
    if (!window.Square) { setMsg("The secure card form could not load. Check your connection and refresh.", true); cardInit = Promise.reject(); cardInit.catch(function () {}); return cardInit; }
    cardInit = window.Square.payments(box.getAttribute("data-app-id"), box.getAttribute("data-location-id")).card().then(function (c) {
      card = c; return c.attach("#sqCard");
    }).catch(function () { setMsg("The secure card form could not start. Please refresh and try again.", true); });
    return cardInit;
  }

  payBtn.addEventListener("click", function () {
    if (busy || !quote) return;
    busy = true; payBtn.disabled = true; setMsg("Processing your payment…");
    var body = { csrf: csrf, coupon: coupon, use_coins: $("ppCoins").checked ? 1 : 0, auto_renew: $("ppAuto").checked ? 1 : 0 };
    var go = function () {
      return post("api/billing/subscribe.php", body).then(function (j) {
        if (j.ok) { setMsg("Payment received. Welcome to Plus! 🎉"); location.href = "billing.php?paid=" + encodeURIComponent(j.order_id || ""); return; }
        busy = false; setMsg(j.error || "The payment could not be completed.", true); render();
      });
    };
    if (useSaved) { body.saved = 1; go().catch(fail); return; }
    initCard().then(function () {
      if (!card) throw new Error("no card");
      return card.tokenize();
    }).then(function (r) {
      if (!r || r.status !== "OK") {
        busy = false; render();
        setMsg((r && r.errors && r.errors[0] && r.errors[0].message) || "Please check the card details.", true);
        return;
      }
      body.token = r.token;
      return go();
    }).catch(fail);
    function fail() { busy = false; render(); setMsg("Something went wrong. Please try again.", true); }
  });

  if (!hasCard) initCard();
  refresh();
})();
