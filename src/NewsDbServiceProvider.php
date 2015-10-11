<?php

namespace Latotzky\Alchemynews;

use Silex\Application;
use Silex\ServiceProviderInterface;

/**
 * A simple alchemyAPI news service provider.
 *
 * @author David Latotzky
 */
class NewsDbServiceProvider implements ServiceProviderInterface
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
        $app['newsdb.factory'] = $app->protect(
            /**
            * @param array $options
            * @return NewsDbService
            */
            function (
                array $options = array()
            ) use ($app) {
                return new \Latotzky\Alchemynews\NewsDbService($app['pdo'], $options);
            }
        );

        $app['newsdb'] = $app->share(
            function (Application $app) {
                return $app['newsdb.factory']();
            }
        );

    }
}
