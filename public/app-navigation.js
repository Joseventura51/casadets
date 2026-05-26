(function () {
    const contentSelector = '#appContent';
    const sameOrigin = (url) => url.origin === window.location.origin;
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

    function localToday() {
        const date = new Date();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${date.getFullYear()}-${month}-${day}`;
    }

    function formUrl(form) {
        const action = form.getAttribute('action') || window.location.pathname;
        const url = new URL(action, window.location.href);
        url.search = '';

        if (form.dataset.defaultToday !== undefined) {
            const today = localToday();
            form.querySelectorAll('input[type="date"][name="desde"], input[type="date"][name="hasta"]').forEach((input) => {
                if (!input.value) input.value = today;
            });
        }

        const data = new FormData(form);
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
        if (event.target.matches('input[type="date"], select, input[type="checkbox"], input[type="radio"]')) return;
        window.clearTimeout(filterTimer);
        filterTimer = window.setTimeout(() => navigate(formUrl(form)), 520);
    });

    document.addEventListener('change', (event) => {
        const form = event.target.closest('form[data-dynamic-filter]');
        if (!shouldHandleForm(form)) return;
        window.clearTimeout(filterTimer);
        filterTimer = window.setTimeout(() => navigate(formUrl(form)), 180);
    });

    window.addEventListener('popstate', () => navigate(window.location.href, { push: false }));
})();
