/*
 | Agency OS — Laravel bridge for the approved static prototype.
 |
 | THIS IS NOT A ROUTER. It never intercepts links, never calls preventDefault on a
 | navigation, never swaps DOM content and never touches history. The browser performs
 | ordinary document navigation for every link, so Ctrl+click, middle-click, "open in new
 | tab", Back and Forward all behave exactly as the browser intends.
 |
 | What it does is narrow: replace the four places where the standalone prototype used to
 | fake something Laravel now owns for real — logout, home, role switching and language.
 | Authorization is NOT done here. ApprovedUiController checks the database role on every
 | request; anything in this file is presentation only.
 */
(function () {
  "use strict";

  var cfg = window.APP_LARAVEL_BRIDGE;
  if (!cfg) return;

  /* -------------------------------------------------- logout through Laravel */
  function laravelLogout() {
    var form = document.createElement("form");
    form.method = "POST";
    form.action = cfg.logoutUrl;
    form.style.display = "none";

    var token = document.createElement("input");
    token.type = "hidden";
    token.name = "_token";
    token.value = cfg.csrf;
    form.appendChild(token);

    /* Clear the prototype's compatibility keys so nothing survives the session. */
    try {
      localStorage.removeItem("agencyos.session.v1");
      sessionStorage.removeItem("agencyos.session.v1");
    } catch (e) {}

    document.body.appendChild(form);
    form.submit();
  }

  if (window.APPG) {
    /* The real role comes from the database. Prototype role-switching must never
       elevate it, so the switcher becomes a plain trip home. */
    APPG.switchRole = function () {
      window.location.assign(cfg.homeUrl);
    };
    APPG.logout = laravelLogout;
  }

  /* Any leftover static logout control routes through Laravel's CSRF-protected POST. */
  document.addEventListener("click", function (event) {
    var el = event.target.closest ? event.target.closest("[data-logout], .js-logout") : null;
    if (!el) return;
    event.preventDefault();
    laravelLogout();
  });

  /* ------------------------------------------------ language switch (POST /locale) */
  document.addEventListener("click", function (event) {
    var el = event.target.closest ? event.target.closest("[data-set-lang]") : null;
    if (!el || !cfg.localeUrl) return;

    var lang = el.getAttribute("data-set-lang");
    if (lang !== "ar" && lang !== "en") return;

    event.preventDefault();

    var form = document.createElement("form");
    form.method = "POST";
    form.action = cfg.localeUrl;
    form.style.display = "none";

    var token = document.createElement("input");
    token.type = "hidden";
    token.name = "_token";
    token.value = cfg.csrf;
    form.appendChild(token);

    var locale = document.createElement("input");
    locale.type = "hidden";
    locale.name = "locale";
    locale.value = lang;
    form.appendChild(locale);

    document.body.appendChild(form);
    form.submit();
  });

  /* Reflect the language Laravel actually decided, so the toggle cannot disagree
     with the rendered direction. Display only — the session is the authority. */
  document.addEventListener("DOMContentLoaded", function () {
    var host = document.querySelector("[data-active][class*='language'], .language-switch");
    if (host) host.setAttribute("data-active", cfg.locale);

    document.querySelectorAll("[data-set-lang]").forEach(function (el) {
      el.setAttribute("aria-current", el.getAttribute("data-set-lang") === cfg.locale ? "true" : "false");
    });
  });

  /* Close the mobile drawer when a link is taken, so the page you just opened is
     not hidden behind an open sidebar. The navigation itself is untouched. */
  document.addEventListener("click", function (event) {
    var link = event.target.closest ? event.target.closest(".sidebar a, .nav a") : null;
    if (!link) return;
    document.body.classList.remove("nav-open");
    var scrim = document.querySelector(".scrim");
    if (scrim) scrim.remove();
  });
})();
