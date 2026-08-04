(function () {
    'use strict';

    var leaving = false;
    var delay = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 85;

    function progressElement() {
        var element = document.getElementById('sl-nav-progress');
        if (element) return element;

        element = document.createElement('div');
        element.id = 'sl-nav-progress';
        element.setAttribute('aria-hidden', 'true');
        document.body.appendChild(element);
        return element;
    }

    function begin() {
        if (leaving) return false;
        leaving = true;

        document.body.classList.remove('sl-nav-entering');
        document.body.classList.add('sl-nav-leaving');
        progressElement().classList.add('is-running');
        return true;
    }

    function reset() {
        leaving = false;
        document.body.classList.remove('sl-nav-leaving');
        document.body.classList.add('sl-nav-entering');

        var progress = document.getElementById('sl-nav-progress');
        if (progress) {
            progress.classList.remove('is-running');
            progress.classList.add('is-finishing');
            window.setTimeout(function () {
                progress.classList.remove('is-finishing');
            }, 220);
        }

        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                document.body.classList.remove('sl-nav-entering');
            });
        });
    }

    function isEligibleLink(anchor, event) {
        if (!anchor || !anchor.href) return false;
        if (anchor.hasAttribute('download') || anchor.dataset.noSmooth !== undefined) return false;
        if (anchor.target && anchor.target !== '_self') return false;
        if (event && (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)) return false;

        var raw = anchor.getAttribute('href') || '';
        if (!raw || raw === '#' || raw.charAt(0) === '#') return false;
        if (/^(mailto:|tel:|javascript:)/i.test(raw)) return false;

        var url;
        try { url = new URL(anchor.href, window.location.href); } catch (error) { return false; }
        if (url.origin !== window.location.origin) return false;
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return false;

        return true;
    }

    function navigate(url, options) {
        options = options || {};
        if (!url) return;
        if (!begin()) return;

        window.setTimeout(function () {
            if (options.replace) window.location.replace(url);
            else window.location.assign(url);
        }, delay);
    }

    document.addEventListener('click', function (event) {
        var anchor = event.target.closest && event.target.closest('a[href]');
        if (!isEligibleLink(anchor, event)) return;

        event.preventDefault();
        navigate(anchor.href);
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.noSmooth !== undefined || form.target) return;
        if (!form.checkValidity()) return;

        var action;
        try { action = new URL(form.action || window.location.href, window.location.href); } catch (error) { return; }
        if (action.origin !== window.location.origin) return;
        if (!begin()) return;

        event.preventDefault();

        var submitter = event.submitter;
        if (submitter && submitter.name) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = submitter.name;
            hidden.value = submitter.value;
            hidden.dataset.smoothSubmitter = 'true';
            form.appendChild(hidden);
        }

        window.setTimeout(function () {
            HTMLFormElement.prototype.submit.call(form);
        }, delay);
    }, true);

    window.addEventListener('pageshow', reset);
    window.addEventListener('pagehide', function () {
        var progress = document.getElementById('sl-nav-progress');
        if (progress) progress.classList.add('is-finishing');
    });

    document.addEventListener('DOMContentLoaded', reset, { once: true });

    window.SkySmooth = {
        navigate: navigate,
        begin: begin,
        reset: reset
    };
}());
