/**
 * Sipo — SI (Super Intelligence) mascot Web Component for siPaw.
 *
 * <sipo-mascot
 *   state="idle|thinking|writing|listening|happy|alert"
 *   theme="ice-blue|blue-body|mono|dark"   (app default: ice-blue)
 *   size="96"
 *   animated="true|false"
 *   label="Sipo, SIPaw assistant"
 *   base=""   optional asset base URL (no trailing slash)
 * ></sipo-mascot>
 *
 * Animated webp/gif when animated + theme ice-blue|blue-body + not prefers-reduced-motion.
 * Otherwise inline themable SVG with --sipaw-* from themes.json.
 * mono/dark always use static themable SVG.
 */
(function () {
  "use strict";

  var STATES = ["idle", "thinking", "writing", "listening", "happy", "alert"];
  var ANIMATED_THEMES = { "ice-blue": true, "blue-body": true };
  var DEFAULT_LABEL = "Sipo, SIPaw assistant";
  var DEFAULT_THEME = "ice-blue";

  var themesCache = null;
  var themesPromise = null;
  var svgCache = {};

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
    return STATES.indexOf(s) >= 0 ? s : "idle";
  }

  function normalizeTheme(t) {
    t = String(t || DEFAULT_THEME).toLowerCase();
    if (t === "ice-blue" || t === "blue-body" || t === "mono" || t === "dark") return t;
    return DEFAULT_THEME;
  }

  function assetBase(el) {
    var b = (el.getAttribute("base") || "").trim();
    if (b) return b.replace(/\/$/, "");
    if (window.SIPO_ASSET_BASE) return String(window.SIPO_ASSET_BASE).replace(/\/$/, "");
    return "assets/mascot/public";
  }

  function loadThemes(base) {
    if (themesCache) return Promise.resolve(themesCache);
    if (themesPromise) return themesPromise;
    themesPromise = fetch(base + "/themes.json", { credentials: "same-origin" })
      .then(function (r) {
        if (!r.ok) throw new Error("themes.json " + r.status);
        return r.json();
      })
      .then(function (j) {
        themesCache = j;
        return j;
      })
      .catch(function (err) {
        themesPromise = null;
        throw err;
      });
    return themesPromise;
  }

  function loadSvg(base, state) {
    if (svgCache[state]) return Promise.resolve(svgCache[state]);
    return fetch(base + "/sipo-" + state + ".svg", { credentials: "same-origin" })
      .then(function (r) {
        if (!r.ok) throw new Error("svg " + state + " " + r.status);
        return r.text();
      })
      .then(function (txt) {
        svgCache[state] = txt;
        return txt;
      });
  }

  function applyThemeVars(host, colors) {
    if (!colors) return;
    Object.keys(colors).forEach(function (role) {
      host.style.setProperty("--sipaw-" + role, colors[role]);
    });
  }

  function clearThemeVars(host) {
    [
      "body",
      "shade",
      "seam",
      "bezel",
      "bezel-line",
      "screen",
      "line",
      "tip",
      "tip-hi",
      "base",
      "ring",
      "arm",
      "arm-shade",
      "accent",
      "on-screen",
      "dim",
      "pencil",
      "eraser",
      "glyph",
    ].forEach(function (role) {
      host.style.removeProperty("--sipaw-" + role);
    });
  }

  class SipoMascot extends HTMLElement {
    static get observedAttributes() {
      return ["state", "theme", "size", "animated", "label", "base"];
    }

    constructor() {
      super();
      this._gen = 0;
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

    get theme() {
      return normalizeTheme(this.getAttribute("theme"));
    }
    set theme(v) {
      this.setAttribute("theme", normalizeTheme(v));
    }

    render() {
      var self = this;
      var gen = ++this._gen;
      var state = this.state;
      var theme = this.theme;
      var size = parseInt(this.getAttribute("size") || "96", 10);
      if (!size || size < 16) size = 96;
      var animated = boolAttr(this, "animated", true);
      var label = this.getAttribute("label") || DEFAULT_LABEL;
      var base = assetBase(this);
      var useAnim = animated && ANIMATED_THEMES[theme] && !reduceMotion();

      this.setAttribute("role", "img");
      this.setAttribute("aria-label", label);

      var style =
        ":host{display:inline-block;line-height:0;vertical-align:middle}" +
        ".wrap{display:block;width:" +
        size +
        "px;height:" +
        size +
        "px}" +
        ".wrap picture,.wrap img{display:block;width:100%;height:100%;object-fit:contain}" +
        ".wrap svg{display:block;width:100%;height:100%}";

      if (useAnim) {
        clearThemeVars(this);
        var webp = base + "/sipo-" + state + "-" + theme + ".webp";
        var gif = base + "/sipo-" + state + "-" + theme + ".gif";
        this._root.innerHTML =
          "<style>" +
          style +
          '</style><div class="wrap"><picture>' +
          '<source type="image/webp" srcset="' +
          webp +
          '">' +
          '<img src="' +
          gif +
          '" alt="" width="' +
          size +
          '" height="' +
          size +
          '" decoding="async">' +
          "</picture></div>";
        var img = this._root.querySelector("img");
        if (img) {
          img.addEventListener("error", function () {
            if (gen !== self._gen) return;
            self._renderSvg(base, state, theme, size, style, gen);
          });
        }
        return;
      }

      this._renderSvg(base, state, theme, size, style, gen);
    }

    _renderSvg(base, state, theme, size, style, gen) {
      var self = this;
      Promise.all([loadThemes(base), loadSvg(base, state)])
        .then(function (pair) {
          if (gen !== self._gen) return;
          var themes = pair[0];
          var svgTxt = pair[1];
          var preset =
            themes && themes.presets && themes.presets[theme] ? themes.presets[theme] : null;
          clearThemeVars(self);
          if (preset && preset.colors) applyThemeVars(self, preset.colors);

          var doc = new DOMParser().parseFromString(svgTxt, "image/svg+xml");
          var svg = doc.documentElement;
          if (!svg || svg.nodeName.toLowerCase() === "parsererror") {
            self._root.innerHTML = "<style>" + style + '</style><div class="wrap"></div>';
            return;
          }
          svg.setAttribute("width", String(size));
          svg.setAttribute("height", String(size));
          svg.setAttribute("aria-hidden", "true");

          self._root.innerHTML = "<style>" + style + "</style>";
          var wrap = document.createElement("div");
          wrap.className = "wrap";
          wrap.appendChild(document.importNode(svg, true));
          self._root.appendChild(wrap);
        })
        .catch(function () {
          if (gen !== self._gen) return;
          self._root.innerHTML = "<style>" + style + '</style><div class="wrap"></div>';
        });
    }
  }

  if (!customElements.get("sipo-mascot")) {
    customElements.define("sipo-mascot", SipoMascot);
  }

  window.Sipo = {
    STATES: STATES,
    DEFAULT_THEME: DEFAULT_THEME,
    setState: function (state, sel) {
      var el = document.querySelector(sel || "sipo-mascot#sipo, sipo-mascot");
      if (el) el.setAttribute("state", normalizeState(state));
      return el;
    },
    setTheme: function (theme, sel) {
      var el = document.querySelector(sel || "sipo-mascot#sipo, sipo-mascot");
      if (el) el.setAttribute("theme", normalizeTheme(theme));
      return el;
    },
  };
})();
