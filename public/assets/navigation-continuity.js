/* Safe navigation acceleration: prefetch only. Links remain normal browser requests. */
(function () {
  "use strict";

  var prefetched = new Set();
  var timer = null;

  function eligible(anchor) {
    if (!anchor || !anchor.href || anchor.hasAttribute("download")) return null;
    if (anchor.target && anchor.target !== "_self") return null;

    var raw = anchor.getAttribute("href") || "";
    if (!raw || raw === "#" || raw.charAt(0) === "#" || /^(mailto:|tel:|javascript:)/i.test(raw)) return null;

    var url;
    try { url = new URL(anchor.href, window.location.href); } catch (error) { return null; }
    if (url.origin !== window.location.origin || url.href === window.location.href) return null;
    if (!/^\/app(?:\/|$)/.test(url.pathname) && url.pathname !== "/dashboard") return null;
    return url;
  }

  function prefetch(anchor) {
    var url = eligible(anchor);
    if (!url || prefetched.has(url.href)) return;

    prefetched.add(url.href);
    var link = document.createElement("link");
    link.rel = "prefetch";
    link.as = "document";
    link.href = url.href;
    document.head.appendChild(link);
  }

  document.addEventListener("pointerover", function (event) {
    var anchor = event.target.closest && event.target.closest("a[href]");
    if (!eligible(anchor)) return;
    clearTimeout(timer);
    timer = setTimeout(function () { prefetch(anchor); }, 80);
  }, { passive: true });

  document.addEventListener("focusin", function (event) {
    var anchor = event.target.closest && event.target.closest("a[href]");
    prefetch(anchor);
  });

  document.addEventListener("touchstart", function (event) {
    var anchor = event.target.closest && event.target.closest("a[href]");
    prefetch(anchor);
  }, { passive: true });

  document.addEventListener("click", function (event) {
    if (!event.target.closest || !event.target.closest("a[href]")) return;
    document.body.classList.remove("nav-open");
    document.querySelectorAll(".scrim,.mback").forEach(function (node) { node.remove(); });
  }, true);
})();
