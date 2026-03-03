import type { PageProps } from '@/types';
import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';
import type { Config, RouteName } from 'ziggy-js';
import { route } from '../../vendor/tightenco/ziggy';

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
                const { location, ...rawConfig } = ziggyProps;
                const ziggy = Object.assign<Partial<Config>, Partial<Config>>(
                    {
                        url: location,
                        port: null,
                        defaults: {},
                        routes: {},
                    },
                    rawConfig as Partial<Config>,
                ) as Config;

                return route(name, params as any, absolute, ziggy);
            };
            /* eslint-enable */

            return <App {...props} />;
        },
    }),
);
