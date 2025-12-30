import './bootstrap';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, Transition } from 'vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

createInertiaApp({
  resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    createApp({
      render: () => h(Transition, { name: 'page', mode: 'out-in' }, () => h(App, props)),
    })
      .use(plugin)
      .mount(el);
  },
  title: (title) => (title ? `${title} - E-Signer` : 'E-Signer'),
});
