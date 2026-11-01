/**
 * SIPO v2 "Plush": the SI (Super Intelligence) mascot Web Component for SIPaw.
 *
 * <sipo-mascot
 *   state="idle|happy|celebrate|thinking|talking|listening|concerned|sad|urgent|sleepy|confused|love"
 *   size="96"
 *   animated="true|false"
 *   label="Sipo, SIPaw assistant"
 *   base=""   optional asset base URL (no trailing slash)
 * ></sipo-mascot>
 *
 * Legacy states still work: writing → talking, alert → concerned.
 * `theme` is accepted and ignored (v2 has one look).
 *
 * Renders the transparent 3D plush: a looping animated WebP (160 px for avatars,
 * 320 px from 130 px upward), or the still frame when animated="false" or the
 * visitor prefers reduced motion.
 */
(function () {
  "use strict";

  var STATES = [
    "idle",
    "happy",
    "celebrate",
    "thinking",
    "talking",
    "listening",
    "concerned",
    "sad",
    "urgent",
    "sleepy",
    "confused",
    "love",
  ];
  var ALIASES = { writing: "talking", alert: "concerned" };
  var DEFAULT_LABEL = "Sipo, SIPaw assistant";

  function reduceMotion() {
    try {
      return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    } catch (e) {
      return false;
    }
  }

  function boolAttr(el, name, fallback) {
    if (!el.hasAttribute(name)) return fallback;
    var v = String(el.getAttribute(name) || "").trim().toLowerCase();
    if (v === "" || v === name) return true;
    if (v === "false" || v === "0" || v === "no" || v === "off") return false;
    return true;
  }

  function normalizeState(s) {
    s = String(s || "idle").toLowerCase();
    if (ALIASES[s]) s = ALIASES[s];
    return STATES.indexOf(s) >= 0 ? s : "idle";
  }

  function assetBase(el) {
    var b = (el.getAttribute("base") || "").trim();
    if (b) return b.replace(/\/$/, "");
    if (window.SIPO_ASSET_BASE) return String(window.SIPO_ASSET_BASE).replace(/\/$/, "");
    return "assets/mascot/v2";
  }

  class SipoMascot extends HTMLElement {
    static get observedAttributes() {
      return ["state", "size", "animated", "label", "base"];
    }

    constructor() {
      super();
      this._root = this.attachShadow({ mode: "open" });
      this._mq = null;
      this._onMq = null;
    }

    connectedCallback() {
      var self = this;
      try {
        this._mq = window.matchMedia("(prefers-reduced-motion: reduce)");
        this._onMq = function () {
          self.render();
        };
        if (this._mq.addEventListener) this._mq.addEventListener("change", this._onMq);
        else if (this._mq.addListener) this._mq.addListener(this._onMq);
      } catch (e) {}
      this.render();
    }

    disconnectedCallback() {
      if (this._mq && this._onMq) {
        try {
          if (this._mq.removeEventListener) this._mq.removeEventListener("change", this._onMq);
          else if (this._mq.removeListener) this._mq.removeListener(this._onMq);
        } catch (e) {}
      }
    }

    attributeChangedCallback() {
      if (this.isConnected) this.render();
    }

    get state() {
      return normalizeState(this.getAttribute("state"));
    }
    set state(v) {
      this.setAttribute("state", normalizeState(v));
    }

    render() {
      var state = this.state;
      var size = parseInt(this.getAttribute("size") || "96", 10);
      if (!size || size < 16) size = 96;
      var label = this.getAttribute("label") || DEFAULT_LABEL;
      var base = assetBase(this);
      var animated = boolAttr(this, "animated", true) && !reduceMotion();
      var variant = !animated ? "still" : size >= 130 ? "l" : "s";
      var src = base + "/" + state + "-" + variant + ".webp";

      this.setAttribute("role", "img");
      this.setAttribute("aria-label", label);

      var img = this._root.querySelector("img");
      if (img && img.getAttribute("src") === src && img.width === size) return;

      var style =
        ":host{display:inline-block;line-height:0;vertical-align:middle}" +
        ".wrap{display:block;width:" +
        size +
        "px;height:" +
        size +
        "px}" +
        ".wrap img{display:block;width:100%;height:100%;object-fit:contain}";
      this._root.innerHTML =
        "<style>" +
        style +
        '</style><div class="wrap"><img src="' +
        src +
        '" alt="" width="' +
        size +
        '" height="' +
        size +
        '" decoding="async"></div>';
    }
  }

  if (!customElements.get("sipo-mascot")) {
    customElements.define("sipo-mascot", SipoMascot);
  }

  window.Sipo = {
    STATES: STATES,
    setState: function (state, sel) {
      var el = document.querySelector(sel || "sipo-mascot#sipo, sipo-mascot");
      if (el) el.setAttribute("state", normalizeState(state));
      return el;
    },
    setTheme: function () {},
  };
})();
