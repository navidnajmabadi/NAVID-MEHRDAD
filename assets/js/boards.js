/**
 * Boards feed (forum.php). Renders window.BOARDS posts, composer with one photo,
 * like/dislike toggles, follow, report sheet and "load more".
 */
(function () {
  "use strict";
  var B = window.BOARDS;
  if (!B) return;
  var feed = document.getElementById("feed");
  var empty = document.getElementById("empty");
  var more = document.getElementById("more");
  var lastId = 0;

  function post(body) {
    body.csrf = B.csrf;
    return fetch(B.api.post, {
      method: "POST", credentials: "same-origin",
      headers: { "Content-Type": "application/json" }, body: JSON.stringify(body)
    }).then(function (r) { return r.json(); });
  }

  function ago(iso) {
    var s = Math.max(1, (Date.now() - Date.parse(iso)) / 1000);
    if (s < 60) return "now";
    if (s < 3600) return Math.floor(s / 60) + "m";
    if (s < 86400) return Math.floor(s / 3600) + "h";
    return Math.floor(s / 86400) + "d";
  }

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }

  function avatar(pet) {
    if (pet.avatar) {
      var i = el("img", "pet-avatar");
      i.src = pet.avatar; i.alt = ""; i.loading = "lazy";
      return i;
    }
    return el("span", "pet-avatar", pet.initial || "?");
  }

  function card(p) {
    var a = el("article", "b-card");
    a.dataset.id = p.id;
    var head = el("header", "b-head");
    var who = el("a", "b-who");
    who.href = "forum.php?pet=" + encodeURIComponent(p.pet.public_id);
    who.appendChild(avatar(p.pet));
    var names = el("span");
    names.appendChild(el("b", null, p.pet.name));
    names.appendChild(el("small", null, p.pet.kind + " · " + ago(p.created_at)));
    who.appendChild(names);
    head.appendChild(who);
    var menu = el("button", "b-menu", "⋯");
    menu.type = "button";
    menu.setAttribute("aria-label", p.owner_id === B.me ? "Delete post" : "Report post");
    menu.addEventListener("click", function () {
      if (p.owner_id === B.me) {
        if (confirm("Delete this post?")) post({ action: "delete", post_id: p.id }).then(function (j) { if (j.ok) a.remove(); });
      } else openReport(p.id, a);
    });
    head.appendChild(menu);
    a.appendChild(head);
    if (p.image) {
      var img = el("img", "b-img");
      img.src = p.image; img.alt = p.body ? p.body.slice(0, 80) : p.pet.name + "'s photo"; img.loading = "lazy";
      if (p.image_w && p.image_h) { img.width = p.image_w; img.height = p.image_h; }
      a.appendChild(img);
    }
    if (p.body) a.appendChild(el("p", "b-body", p.body));
    var bar = el("div", "b-bar");
    var like = el("button", "b-vote" + (p.my_vote === 1 ? " is-on" : ""), "👍 " + p.likes);
    var dis = el("button", "b-vote" + (p.my_vote === -1 ? " is-on" : ""), "👎 " + p.dislikes);
    [like, dis].forEach(function (b, i) {
      b.type = "button";
      b.setAttribute("aria-pressed", String((i === 0 ? 1 : -1) === p.my_vote));
      b.setAttribute("aria-label", i === 0 ? "Like" : "Dislike");
      b.addEventListener("click", function () {
        post({ action: "vote", post_id: p.id, vote: i === 0 ? 1 : -1 }).then(function (j) {
          if (!j.ok) return;
          p.my_vote = j.my_vote;
          like.textContent = "👍 " + j.likes; dis.textContent = "👎 " + j.dislikes;
          like.classList.toggle("is-on", j.my_vote === 1); dis.classList.toggle("is-on", j.my_vote === -1);
          like.setAttribute("aria-pressed", String(j.my_vote === 1)); dis.setAttribute("aria-pressed", String(j.my_vote === -1));
        });
      });
    });
    bar.appendChild(like);
    bar.appendChild(dis);
    var com = el("a", "b-vote", "💬 " + p.comments);
    com.href = "forum-post.php?id=" + p.id;
    bar.appendChild(com);
    if (p.owner_id !== B.me && !B.pet) {
      var f = el("button", "b-followlink" + (p.following ? " is-on" : ""), p.following ? "Following" : "Follow");
      f.type = "button";
      f.addEventListener("click", function () {
        var on = !p.following;
        post({ action: "follow", pet_id: p.pet.id, on: on ? 1 : 0 }).then(function (j) {
          if (!j.ok) return;
          p.following = on; f.textContent = on ? "Following" : "Follow"; f.classList.toggle("is-on", on);
        });
      });
      bar.appendChild(f);
    }
    a.appendChild(bar);
    return a;
  }

  function render(list, prepend) {
    list.forEach(function (p) {
      var c = card(p);
      if (prepend) feed.insertBefore(c, feed.firstChild); else feed.appendChild(c);
      if (!lastId || p.id < lastId) lastId = p.id;
    });
    empty.hidden = feed.children.length > 0;
    more.hidden = list.length < 20 && !prepend ? true : more.hidden;
  }
  render(B.posts);
  more.hidden = B.posts.length < 20;
  more.addEventListener("click", function () {
    more.disabled = true;
    var q = "?scope=" + B.scope + "&before_id=" + lastId + (B.pet ? "&pet=" + encodeURIComponent(B.pet) : "");
    fetch(B.api.feed + q, { credentials: "same-origin" }).then(function (r) { return r.json(); }).then(function (j) {
      more.disabled = false;
      if (j.ok) { render(j.posts); more.hidden = j.posts.length < 20; }
    });
  });

  // Report sheet
  var sheet = document.getElementById("reportSheet");
  var reporting = null;
  function openReport(id, cardEl) {
    reporting = { id: id, card: cardEl };
    if (sheet.showModal) sheet.showModal(); else sheet.setAttribute("open", "");
  }
  sheet.addEventListener("click", function (e) {
    if (e.target === sheet || e.target.closest("[data-close]")) sheet.close();
    var r = e.target.closest("[data-reason]");
    if (r && reporting) {
      post({ action: "report", target_type: "post", target_id: reporting.id, reason: r.getAttribute("data-reason") }).then(function (j) {
        sheet.close();
        if (j.ok) {
          var note = el("p", "b-reported", j.hidden ? "Thanks. This post is hidden while we review it." : "Thanks for reporting. We'll take a look.");
          reporting.card.replaceWith(note);
        }
      });
    }
  });

  // Follow button on a pet profile
  var fb = document.querySelector("[data-follow]");
  if (fb) {
    fb.addEventListener("click", function () {
      var on = fb.getAttribute("data-on") !== "1";
      post({ action: "follow", pet_id: Number(fb.getAttribute("data-follow")), on: on ? 1 : 0 }).then(function (j) {
        if (!j.ok) return;
        fb.setAttribute("data-on", on ? "1" : "0");
        fb.textContent = on ? "Following" : "Follow";
        fb.className = "btn " + (on ? "outline" : "primary") + " b-follow";
        var c = document.getElementById("followerCount");
        if (c) c.textContent = j.followers;
      });
    });
  }

  // Shrink on the phone first (long side 2000 px, JPEG 0.88): fast on mobile data, under any upload limit.
  function shrink(file) {
    return new Promise(function (resolve) {
      if (!file) return resolve(null);
      var img = new Image();
      var url = URL.createObjectURL(file);
      img.onload = function () {
        var s = Math.min(1, 2000 / Math.max(img.naturalWidth, img.naturalHeight));
        var c = document.createElement("canvas");
        c.width = Math.round(img.naturalWidth * s); c.height = Math.round(img.naturalHeight * s);
        c.getContext("2d").drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(function (b) { resolve(b || file); }, "image/jpeg", 0.88);
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  // Composer
  var form = document.getElementById("composer");
  if (!form) return;
  var text = form.querySelector("textarea");
  var count = document.getElementById("count");
  var photo = document.getElementById("photo");
  var preview = document.getElementById("preview");
  var note = document.getElementById("composeNote");
  var btn = document.getElementById("postBtn");
  text.addEventListener("input", function () { count.textContent = text.value.length + "/" + text.maxLength; });
  photo.addEventListener("change", function () {
    var f = photo.files && photo.files[0];
    if (!f) { preview.hidden = true; return; }
    if (f.size > 40 * 1024 * 1024) { note.textContent = "That photo is too big."; photo.value = ""; return; }
    preview.src = URL.createObjectURL(f);
    preview.hidden = false;
  });
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    if (!text.value.trim() && !(photo.files && photo.files[0])) { note.textContent = "Write something or add a photo 🐾"; return; }
    btn.disabled = true;
    note.textContent = "Posting…";
    shrink(photo.files && photo.files[0]).then(function (blob) {
      var fd = new FormData(form);
      fd.delete("photo");
      if (blob) fd.append("photo", blob, "photo.jpg");
      fd.append("csrf", B.csrf);
      fd.append("action", "create");
      return fetch(B.api.post, { method: "POST", credentials: "same-origin", body: fd });
    })
      .then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); })
      .then(function (j) {
        btn.disabled = false;
        if (j.ok && j.post) {
          form.reset(); preview.hidden = true; count.textContent = "0/" + text.maxLength;
          note.textContent = "Posted 🎉";
          render([j.post], true);
          return;
        }
        if (j.suspended) { location.href = "login.php"; return; }
        note.textContent = j.error || "Could not post.";
      })
      .catch(function () { btn.disabled = false; note.textContent = "No connection. Try again."; });
  });
})();
