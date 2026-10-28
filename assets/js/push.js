/**
 * Web Push subscribe for siPaw (installed PWA + logged-in owners).
 * Exposes window.RRPPush: ensure(), status(), enable().
 */
(function () {
  "use strict";

  var meta = function (name) {
    var el = document.querySelector('meta[name="' + name + '"]');
    return el ? el.getAttribute("content") : "";
  };

  function vapidUrl() {
    return meta("rrp-push-vapid") || "api/push-vapid.php";
  }

  function subscribeUrl() {
    return meta("rrp-push-subscribe") || "api/push-subscribe.php";
  }

  function isStandalone() {
    return (
      window.matchMedia("(display-mode: standalone)").matches ||
      window.navigator.standalone === true
    );
  }

  function isIos() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent);
  }

  function urlBase64ToUint8Array(base64String) {
    var padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
    var raw = atob(base64);
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }

  function supportsPush() {
    return (
      "serviceWorker" in navigator &&
      "PushManager" in window &&
      "Notification" in window
    );
  }

  function fetchVapid() {
    return fetch(vapidUrl(), { credentials: "same-origin" }).then(function (r) {
      return r.json();
    });
  }

  function postSubscription(sub) {
    var json = sub.toJSON();
    return fetch(subscribeUrl(), {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        endpoint: json.endpoint,
        keys: json.keys,
        userAgent: navigator.userAgent,
      }),
    }).then(function (r) {
      return r.json();
    });
  }

  function getRegistration() {
    return navigator.serviceWorker.ready;
  }

  /**
   * @returns {Promise<{
   *   supported:boolean,
   *   configured:boolean,
   *   permission:string,
   *   subscribed:boolean,
   *   standalone:boolean,
   *   ios:boolean,
   *   hint:string
   * }>}
   */
  function status() {
    var base = {
      supported: supportsPush(),
      configured: false,
      permission: typeof Notification !== "undefined" ? Notification.permission : "denied",
      subscribed: false,
      standalone: isStandalone(),
      ios: isIos(),
      hint: "",
    };

    if (!base.supported) {
      base.hint = "This browser does not support Web Push.";
      return Promise.resolve(base);
    }

    if (base.ios && !base.standalone) {
      base.hint =
        "On iPhone, install siPaw first (Share → Add to Home Screen), then open the app and enable notifications.";
    }

    return fetchVapid()
      .then(function (v) {
        base.configured = !!(v && v.configured && v.publicKey);
        if (!base.configured) {
          base.hint =
            "Push is not configured on the server yet (empty VAPID keys). Email and inbox still work. Ask an admin to add vapid keys to sipaw.php.";
          return base;
        }
        return getRegistration().then(function (reg) {
          return reg.pushManager.getSubscription().then(function (sub) {
            base.subscribed = !!sub;
            if (base.permission === "denied") {
              base.hint = isIos()
                ? "Notifications blocked. On iPhone: Settings → Notifications → siPaw → Allow Notifications."
                : "Notifications blocked. Open site settings in your browser and allow notifications for sipaw.nakilab.com, then tap Enable again.";
            } else if (!base.subscribed && base.permission === "granted") {
              base.hint = "Permission granted — finishing subscription…";
            } else if (!base.subscribed) {
              base.hint =
                "Enable notifications so reminders and Lost mode alerts reach you when the app is closed.";
            } else {
              base.hint = "Push notifications are on for this device.";
            }
            return base;
          });
        });
      })
      .catch(function () {
        base.hint = "Could not check push status. Email and inbox still work.";
        return base;
      });
  }

  /**
   * Request permission (if needed) and subscribe; save to API.
   * @returns {Promise<{ok:boolean, error?:string, status?:object}>}
   */
  function enable() {
    if (!supportsPush()) {
      return Promise.resolve({ ok: false, error: "unsupported" });
    }
    if (isIos() && !isStandalone()) {
      return Promise.resolve({
        ok: false,
        error: "ios_not_installed",
        status: {
          hint:
            "Install the app first: Share → Add to Home Screen, open siPaw from your home screen, then enable notifications.",
        },
      });
    }

    return fetchVapid().then(function (v) {
      if (!v || !v.configured || !v.publicKey) {
        return { ok: false, error: "vapid_not_configured" };
      }
      return Notification.requestPermission().then(function (perm) {
        if (perm !== "granted") {
          return { ok: false, error: "permission_denied", permission: perm };
        }
        return getRegistration().then(function (reg) {
          return reg.pushManager
            .subscribe({
              userVisibleOnly: true,
              applicationServerKey: urlBase64ToUint8Array(v.publicKey),
            })
            .then(function (sub) {
              return postSubscription(sub).then(function (res) {
                if (res && res.ok) {
                  return { ok: true };
                }
                return { ok: false, error: (res && res.error) || "save_failed" };
              });
            });
        });
      });
    });
  }

  /**
   * Quiet re-subscribe when already permitted (login / A2HS / page load).
   */
  function ensure() {
    if (!supportsPush()) return Promise.resolve({ ok: false, skipped: true });
    if (typeof Notification === "undefined" || Notification.permission !== "granted") {
      return Promise.resolve({ ok: false, skipped: true });
    }
    if (isIos() && !isStandalone()) {
      return Promise.resolve({ ok: false, skipped: true });
    }

    return fetchVapid().then(function (v) {
      if (!v || !v.configured || !v.publicKey) {
        return { ok: false, skipped: true, error: "vapid_not_configured" };
      }
      return getRegistration().then(function (reg) {
        return reg.pushManager.getSubscription().then(function (existing) {
          var subPromise = existing
            ? Promise.resolve(existing)
            : reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(v.publicKey),
              });
          return subPromise.then(function (sub) {
            return postSubscription(sub).then(function (res) {
              return { ok: !!(res && res.ok) };
            });
          });
        });
      });
    }).catch(function () {
      return { ok: false };
    });
  }

  function renderBanner(el) {
    if (!el) return;
    status().then(function (s) {
      el.hidden = false;
      var title = el.querySelector("[data-push-title]");
      var hint = el.querySelector("[data-push-hint]");
      var btn = el.querySelector("[data-push-enable]");
      var badge = el.querySelector("[data-push-badge]");

      if (title) title.textContent = "Phone notifications";
      if (hint) hint.textContent = s.hint || "";

      if (badge) {
        if (s.subscribed) {
          badge.textContent = "On";
          badge.className = "pill pill-live";
        } else if (!s.configured) {
          badge.textContent = "Server off";
          badge.className = "pill pill-ended";
        } else if (s.permission === "denied") {
          badge.textContent = "Blocked";
          badge.className = "pill pill-lost";
        } else {
          badge.textContent = "Off";
          badge.className = "pill pill-ended";
        }
      }

      if (btn) {
        var canEnable =
          s.supported &&
          s.configured &&
          s.permission !== "denied" &&
          !s.subscribed &&
          !(s.ios && !s.standalone);
        btn.hidden = !canEnable;
        btn.disabled = !canEnable;
      }
    });
  }

  window.RRPPush = {
    status: status,
    enable: enable,
    ensure: ensure,
    renderBanner: renderBanner,
    isStandalone: isStandalone,
    isIos: isIos,
  };
})();
