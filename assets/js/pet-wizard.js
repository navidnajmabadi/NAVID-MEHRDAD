(function () {
  "use strict";
  if (!window.RRP_PET_WIZARD) return;

  var steps = Array.prototype.slice.call(document.querySelectorAll(".step[data-step]"));
  var bar = document.getElementById("add-bar");
  var sub = document.getElementById("add-sub");
  var nextBtn = document.getElementById("add-next");
  var saveBtn = document.getElementById("add-save");
  var typeInput = document.getElementById("type");
  var customWrap = document.getElementById("customTypeWrap");
  var customVis = document.getElementById("custom_type_vis");
  var customHidden = document.getElementById("custom_type");
  var photo = document.getElementById("image");
  var prev = document.getElementById("photo-prev");
  var idx = 0;

  function show(i) {
    idx = Math.max(0, Math.min(steps.length - 1, i));
    steps.forEach(function (el, n) {
      el.classList.toggle("on", n === idx);
      if (n === idx) el.style.display = "flex";
      else el.style.display = "none";
    });
    if (bar) {
      Array.prototype.forEach.call(bar.querySelectorAll("i"), function (dot, n) {
        dot.classList.toggle("on", n <= idx);
      });
    }
    if (sub) {
      sub.textContent = "Step " + (idx + 1) + " of " + steps.length;
    }
    if (nextBtn && saveBtn) {
      var last = idx === steps.length - 1;
      nextBtn.hidden = last;
      saveBtn.hidden = !last;
    }
  }

  function setType(val) {
    if (!typeInput) return;
    typeInput.value = val;
    document.querySelectorAll("#species .opt, .chips .chip[data-val]").forEach(function (btn) {
      btn.setAttribute("aria-pressed", btn.getAttribute("data-val") === val ? "true" : "false");
    });
    if (customWrap) customWrap.style.display = val === "custom" ? "" : "none";
    if (window.RRP_BREEDS && typeof window.refreshBreedList === "function") {
      window.refreshBreedList(val);
    } else {
      var ev = document.createEvent("Event");
      ev.initEvent("change", true, true);
      typeInput.dispatchEvent(ev);
    }
  }

  document.querySelectorAll("#species .opt, .chips .chip[data-val]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      setType(btn.getAttribute("data-val"));
    });
  });

  if (customVis && customHidden) {
    customVis.addEventListener("input", function () {
      customHidden.value = customVis.value;
    });
  }

  if (photo && prev) {
    photo.addEventListener("change", function () {
      var f = photo.files && photo.files[0];
      if (!f) return;
      var url = URL.createObjectURL(f);
      prev.src = url;
      prev.hidden = false;
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener("click", function () {
      if (idx === 0 && typeInput && !typeInput.value) {
        setType("dog");
      }
      if (idx === 2) {
        var name = document.getElementById("name");
        if (name && !name.value.trim()) {
          name.focus();
          return;
        }
      }
      show(idx + 1);
    });
  }

  // collapse steps for wizard mode
  steps.forEach(function (el, n) {
    if (n > 0) {
      el.classList.remove("on");
      el.style.display = "none";
    }
  });
  show(0);
})();
