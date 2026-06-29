import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue } from 'laravel-vue-i18n';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18nVue, {
                lang: props.initialPage.props.locale ?? 'en',
                fallbackLang: 'en',
                resolve: async (lang) => {
                    // The laravel-vue-i18n Vite plugin compiles our PHP lang
                    // files (lang/en, lang/es) to lang/php_{lang}.json — the only
                    // lang JSON that exists. Resolving the un-prefixed name threw
                    // "Object.assign(...)[s] is not a function" on every page boot.
                    const langs = import.meta.glob('../../lang/*.json');
                    return await langs[`../../lang/php_${lang}.json`]();
                },
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
