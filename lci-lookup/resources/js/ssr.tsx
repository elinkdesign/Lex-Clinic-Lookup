import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';
import type { RouteName, Config } from 'ziggy-js';
import { route } from '../../vendor/tightenco/ziggy';
import type { PageProps } from '@/types';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => `${title} - ${appName}`,
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.tsx`,
                import.meta.glob('./Pages/**/*.tsx'),
            ),
        setup: ({ App, props }) => {
            /* eslint-disable */
            // @ts-expect-error
            global.route<RouteName> = (name, params, absolute) => {
                const ziggyProps = (page.props as PageProps).ziggy;
                const { location, ...rest } = ziggyProps;
                const ziggy: Config = {
                    url: location,
                    port: null,
                    defaults: {},
                    routes: {},
                    ...rest
                };
                return route(name, params as any, absolute, ziggy);
            };
            /* eslint-enable */

            return <App {...props} />;
        },
    }),
);
