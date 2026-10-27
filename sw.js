/* SIPAW service worker.
 * Pages (PHP/HTML) are ALWAYS network-first: they carry the session and CSRF token,
 * and a cached copy showed owners stale chats and broke login ("Session expired").
 * Only static files are cached (stale-while-revalidate). Offline → offline.html.
 */
const CACHE = "sipaw-static-v3";
const OFFLINE = "./offline.html";
const PRECACHE = [
  OFFLINE,
  "./assets/css/sipaw.css",
  "./assets/css/sipo-app.css",
  "./assets/js/app.js",
  "./assets/js/push.js",
  "./assets/icons/icon-192.png",
  "./manifest.webmanifest",
];
const STATIC_RE = /\.(css|js|png|jpe?g|gif|webp|svg|woff2?|webmanifest)$/i;

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  if (req.method !== "GET") return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.includes("/api/") || url.pathname.includes("/uploads/") || url.pathname.includes("/cron/")) {
    return; // always network
  }

  if (req.mode === "navigate") {
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
    return;
  }

  if (!STATIC_RE.test(url.pathname)) return;
  event.respondWith(
    caches.open(CACHE).then((cache) =>
      cache.match(req).then((cached) => {
        const fetched = fetch(req)
          .then((res) => {
            if (res && res.ok) cache.put(req, res.clone());
            return res;
          })
          .catch(() => cached);
        return cached || fetched;
      })
    )
  );
});

self.addEventListener("push", (event) => {
  let data = {
    title: "SIPAW",
    body: "You have a new alert.",
    url: "./si-chat.php",
    icon: "./assets/icons/icon-192.png",
    badge: "./assets/icons/icon-192.png",
  };
  try {
    if (event.data) {
      const parsed = event.data.json();
      if (parsed && typeof parsed === "object") {
        data = Object.assign(data, parsed);
      }
    }
  } catch (e) {
    try {
      const text = event.data && event.data.text();
      if (text) data.body = text;
    } catch (e2) {}
  }

  const openUrl = data.url || "./si-chat.php";
  event.waitUntil(
    self.registration.showNotification(data.title || "SIPAW", {
      body: data.body || "",
      icon: data.icon || "./assets/icons/icon-192.png",
      badge: data.badge || "./assets/icons/icon-192.png",
      data: { url: openUrl },
      renotify: true,
      tag: openUrl.indexOf("lost-alert") !== -1 ? "rrp-lost" : "rrp-alert",
      requireInteraction: openUrl.indexOf("lost-alert") !== -1,
    })
  );
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || "./si-chat.php";
  const abs = new URL(target, self.location.origin).href;

  event.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((clientList) => {
      for (let i = 0; i < clientList.length; i++) {
        const client = clientList[i];
        if (client.url && "focus" in client) {
          if (typeof client.navigate === "function") {
            return client.navigate(abs).then((c) => (c && c.focus ? c.focus() : client.focus()));
          }
          return client.focus();
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(abs);
      }
    })
  );
});
