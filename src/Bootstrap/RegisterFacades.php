<?php

namespace CrCms\Microservice\Bootstrap;

use Illuminate\Support\Facades\Facade;
use CrCms\Microservice\Foundation\AliasLoader;
use Illuminate\Contracts\Foundation\Application;
use CrCms\Microservice\Foundation\PackageManifest;

class RegisterFacades
{
    /**
     * Bootstrap the given application.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     *
     * @return void
     */
    public function bootstrap(Application $app)
    {
        Facade::clearResolvedInstances();

        Facade::setFacadeApplication($app);

        AliasLoader::getInstance(array_merge(
            $app->make('config')->get('mount.aliases', []),
            $app->make(PackageManifest::class)->aliases()
        ))->register();
    }
}
