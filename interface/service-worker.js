const CACHE_NAME = "trace-shell-20260915";
const SHELL = [
  "/",
  "/index.html",
  "/dashboard.html",
  "/manifests.html",
  "/manifest.html",
  "/manifest-edit.html",
  "/import.html",
  "/division.html",
  "/work.html",
  "/summary.html",
  "/history.html",
  "/occurrences.html",
  "/products.html",
  "/dalas.html",
  "/dala.html",
  "/dala-edit.html",
  "/dala-actions.html",
  "/settings.html",
  "/alerts.html",
  "/emergency.html",
  "/css/styles.css",
  "/css/light-theme.css",
  "/css/auth.css",
  "/css/dalas-layout-fix.css",
  "/js/aplicacao.js",
];

async function cacheResponse(request, response) {
  if (!response || !response.ok || response.type === "opaque") return response;
  const cache = await caches.open(CACHE_NAME);
  await cache.put(request, response.clone());
  return response;
}

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(async (cache) => {
      await Promise.all(
        SHELL.map(async (path) => {
          try {
            await cache.add(path);
          } catch (_) {
            // A instalação não pode falhar se uma tela opcional não estiver disponível.
          }
        }),
      );
      await self.skipWaiting();
    }),
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then(async (names) => {
      await Promise.all(
        names
          .filter((name) => name.startsWith("trace-shell-") && name !== CACHE_NAME)
          .map((name) => caches.delete(name)),
      );
      await self.clients.claim();
    }),
  );
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method !== "GET" || url.origin !== self.location.origin) return;
  if (url.pathname.startsWith("/api/")) return;

  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request)
        .then((response) => cacheResponse(request, response))
        .catch(async () => (await caches.match(request)) || caches.match("/index.html")),
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      const refresh = fetch(request)
        .then((response) => cacheResponse(request, response))
        .catch(() => cached);
      return cached || refresh;
    }),
  );
});
