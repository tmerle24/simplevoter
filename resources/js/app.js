import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { i18n } from './i18n';

createInertiaApp({
    // Kein " - Laravel"-Suffix: der Titel kommt vollständig aus <Head title="...">
    // auf jeder Seite (siehe app.blade.php: <title inertia>SimpleVoter</title>
    // als Fallback).
    title: (title) => title || 'SimpleVoter',
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#bb3245',
    },
});
