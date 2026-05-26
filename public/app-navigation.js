(function () {
    const contentSelector = '#appContent';
    const sameOrigin = (url) => url.origin === window.location.origin;
    const cache = new Map();
    let controller = null;
    let filterTimer = null;

    function content() {
        return document.querySelector(contentSelector);
    }

    function ensureLoader() {
        let loader = document.querySelector('.page-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.className = 'page-loader';
            document.body.appendChild(loader);
        }
        return loader;
    }

    function setLoading(isLoading) {
        const area = content();
        const loader = ensureLoader();
        if (!area) return;

        area.classList.toggle('is-loading', isLoading);
        if (isLoading) {
            area.classList.remove('is-ready');
            loader.classList.remove('is-done');
            loader.classList.add('is-active');
        } else {
            area.classList.remove('is-loading');
            area.classList.add('is-ready');
            loader.classList.remove('is-active');
            loader.classList.add('is-done');
            window.setTimeout(() => loader.classList.remove('is-done'), 240);
        }
    }

    function shouldSkipLink(link, event) {
        if (!link || event.defaultPrevented) return true;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return true;
        if (link.target && link.target !== '_self') return true;
        if (link.hasAttribute('download') || link.dataset.noDynamic !== undefined) return true;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return true;

        const url = new URL(href, window.location.href);
        if (!sameOrigin(url)) return true;
        if (url.pathname.includes('/export')) return true;
        if (url.pathname.startsWith('/casadets/saldos-favor')) return true;
        if (/\.(xlsx|xls|csv|pdf|zip|png|jpg|jpeg|webp)$/i.test(url.pathname)) return true;

        return false;
    }

    function shouldHandleForm(form) {
        if (!form || (form.method || 'get').toLowerCase() !== 'get') return false;
        if (form.dataset.noDynamic !== undefined) return false;
        if (form.enctype && form.enctype !== 'application/x-www-form-urlencoded') return false;
        return true;
    }

    function formUrl(form) {
        const action = form.getAttribute('action') || window.location.pathname;
        const url = new URL(action, window.location.href);
        const data = new FormData(form);
        url.search = '';
        for (const [key, value] of data.entries()) {
            if (value !== null && String(value).trim() !== '') {
                url.searchParams.append(key, value);
            }
        }
        return url;
    }

    function executeScripts(root) {
        const inlineScripts = [];
        root.querySelectorAll('script').forEach((script) => {
            if (script.src) {
                const next = document.createElement('script');
                for (const attr of script.attributes) {
                    next.setAttribute(attr.name, attr.value);
                }
                script.replaceWith(next);
                return;
            }

            inlineScripts.push(script.textContent);
            script.remove();
        });

        if (inlineScripts.length) {
            try {
                new Function(inlineScripts.join('\n'))();
            } catch (error) {
                console.error('No se pudo inicializar la pantalla cargada.', error);
            }
        }
    }

    function refreshBootstrap(root) {
        root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
            bootstrap.Tooltip.getInstance(el)?.dispose();
            new bootstrap.Tooltip(el, { trigger: 'hover' });
        });

        document.querySelectorAll('.offcanvas.show').forEach((el) => {
            bootstrap.Offcanvas.getInstance(el)?.hide();
        });
    }

    function replaceSidebar(doc) {
        const desktop = document.getElementById('sidebarDesktopNav');
        const nextDesktop = doc.getElementById('sidebarDesktopNav');
        if (desktop && nextDesktop) desktop.innerHTML = nextDesktop.innerHTML;

        const mobile = document.getElementById('sidebarMobileNav');
        const nextMobile = doc.getElementById('sidebarMobileNav');
        if (mobile && nextMobile) mobile.innerHTML = nextMobile.innerHTML;
    }

    function render(html, url, push) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const nextContent = doc.querySelector(contentSelector);
        const area = content();

        if (!nextContent || !area) {
            window.location.href = url.href;
            return;
        }

        document.title = doc.title || document.title;
        replaceSidebar(doc);
        area.innerHTML = nextContent.innerHTML;
        executeScripts(area);
        refreshBootstrap(area);

        if (push) history.pushState({ dynamic: true }, '', url.href);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setLoading(false);
    }

    async function navigate(target, options = {}) {
        const url = target instanceof URL ? target : new URL(target, window.location.href);
        if (!sameOrigin(url)) {
            window.location.href = url.href;
            return;
        }

        const push = options.push !== false;
        const key = url.href;

        if (controller) controller.abort();
        controller = new AbortController();
        setLoading(true);

        try {
            if (cache.has(key)) {
                render(cache.get(key), url, push);
                return;
            }

            const res = await fetch(url.href, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html',
                },
                signal: controller.signal,
            });

            if (!res.ok || !res.headers.get('content-type')?.includes('text/html')) {
                window.location.href = url.href;
                return;
            }

            const html = await res.text();
            cache.set(key, html);
            if (cache.size > 15) cache.delete(cache.keys().next().value);
            render(html, url, push);
        } catch (error) {
            if (error.name !== 'AbortError') window.location.href = url.href;
        }
    }

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (shouldSkipLink(link, event)) return;
        event.preventDefault();
        navigate(link.href);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!shouldHandleForm(form)) return;
        event.preventDefault();
        navigate(formUrl(form));
    });

    document.addEventListener('input', (event) => {
        const form = event.target.closest('form[data-dynamic-filter]');
        if (!shouldHandleForm(form)) return;
        window.clearTimeout(filterTimer);
        filterTimer = window.setTimeout(() => navigate(formUrl(form)), 420);
    });

    document.addEventListener('change', (event) => {
        const form = event.target.closest('form[data-dynamic-filter]');
        if (!shouldHandleForm(form)) return;
        window.clearTimeout(filterTimer);
        filterTimer = window.setTimeout(() => navigate(formUrl(form)), 180);
    });

    window.addEventListener('popstate', () => navigate(window.location.href, { push: false }));
})();
