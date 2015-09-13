<?php

namespace Latotzky\Alchemynews;

use Silex\Application;
use Silex\ServiceProviderInterface;

/**
 * A simple alchemyAPI news service provider.
 *
 * @author David Latotzky
 */
class AlchemyApiNewsServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritDoc}
     */
    // @codeCoverageIgnoreStart
    public function boot(Application $app)
    {
    }
    // @codeCoverageIgnoreEnd

    /**
     * {@inheritDoc}
     */
    public function register(Application $app)
    {
        $app['alchemyapinews.factory'] = $app->protect(
            function (
                $apikey = null,
                array $options = array()
            ) use ($app) {
                return new AlchemyApiNewsService($apikey, $options);
            }
        );

        $app['alchemyapinews'] = $app->share(
            function (Application $app) {
                foreach ($app['alchemyapinews.defaults'] as $name => $value) {
                    if (!isset($app[$name])) {
                        $app[$name] = $value;
                    }
                }

                return $app['alchemyapinews.factory']($app['alchemyapinews.apikey'], $app['alchemyapinews.options']);
            }
        );

        $app['alchemyapinews.defaults'] = array(
            'alchemyapinews.apikey' => '',
            'alchemyapinews.options' => array()
        );
    }
}
