<?php

/**
 * @author simon <simon@crcms.cn>
 * @datetime 2018-11-10 19:18
 *
 * @link http://crcms.cn/
 *
 * @copyright Copyright &copy; 2018 Rights Reserved CRCMS
 */

namespace CrCms\Microservice\Foundation;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Log\LogServiceProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Contracts\Foundation\Application as ApplicationContainerContract;

/**
 * Class Application.
 */
class Application extends Container implements ContainerContract, ApplicationContainerContract
{
    /**
     * @var string
     */
    const VERSION = '0.1.0-dev1';

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string
     */
    protected $storagePath;

    /**
     * @var string
     */
    protected $configPath;

    /**
     * @var string
     */
    protected $defaultConfigPath;

    /**
     * @var string
     */
    protected $databasePath;

    /**
     * @var string
     */
    protected $modulePath;

    /**
     * @var string
     */
    protected $resourcePath;

    /**
     * @var string
     */
    protected $environmentPath;

    /**
     * @var string
     */
    protected $environmentFile = '.env';

    /**
     * Indicates if the application has been bootstrapped before.
     *
     * @var bool
     */
    protected $hasBeenBootstrapped = false;

    /**
     * Indicates if the application has "booted".
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * The array of booting callbacks.
     *
     * @var array
     */
    protected $bootingCallbacks = [];

    /**
     * The array of booted callbacks.
     *
     * @var array
     */
    protected $bootedCallbacks = [];

    /**
     * The array of terminating callbacks.
     *
     * @var array
     */
    protected $terminatingCallbacks = [];

    /**
     * All of the registered service providers.
     *
     * @var array
     */
    protected $serviceProviders = [];

    /**
     * The names of the loaded service providers.
     *
     * @var array
     */
    protected $loadedProviders = [];

    /**
     * The deferred services and their providers.
     *
     * @var array
     */
    protected $deferredServices = [];

    /**
     * Application constructor.
     *
     * @param null|string $basePath
     */
    public function __construct(?string $basePath = null)
    {
        if ($basePath) {
            $this->setBasePath($basePath);
        }

        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
        $this->registerCoreContainerAliases();
    }

    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version()
    {
        return static::VERSION;
    }

    /**
     * Register the basic bindings into the container.
     *
     * @return void
     */
    protected function registerBaseBindings()
    {
        static::setInstance($this);

        $this->instance('app', $this);

        $this->instance(Container::class, $this);

        $this->instance(PackageManifest::class, new PackageManifest(
            new Filesystem, $this->basePath(), $this->getCachedPackagesPath()
        ));
    }

    /**
     * Register all of the base service providers.
     *
     * @return void
     */
    protected function registerBaseServiceProviders()
    {
        $this->register(new EventServiceProvider($this));

        $this->register(new LogServiceProvider($this));

        //$this->register(new RoutingServiceProvider($this));
    }

    /**
     * Run the given array of bootstrap classes.
     *
     * @param  string[]  $bootstrappers
     * @return void
     */
    public function bootstrapWith(array $bootstrappers)
    {
        $this->hasBeenBootstrapped = true;

        foreach ($bootstrappers as $bootstrapper) {
            $this['events']->dispatch('bootstrapping: '.$bootstrapper, [$this]);

            $this->make($bootstrapper)->bootstrap($this);

            $this['events']->dispatch('bootstrapped: '.$bootstrapper, [$this]);
        }
    }

    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function hasBeenBootstrapped()
    {
        return $this->hasBeenBootstrapped;
    }

    /**
     * Set the base path for the application.
     *
     * @param  string  $basePath
     * @return $this
     */
    public function setBasePath($basePath)
    {
        $this->basePath = rtrim($basePath, '\/');

        $this->bindPathsInContainer();

        return $this;
    }

    /**
     * Bind all of the application paths in the container.
     *
     * @return void
     */
    protected function bindPathsInContainer()
    {
        $this->instance('path.base', $this->basePath());
        $this->instance('path.lang', $this->langPath());
        $this->instance('path.config', $this->configPath());
        $this->instance('path.public', $this->publicPath());
        $this->instance('path.storage', $this->storagePath());
        $this->instance('path.database', $this->databasePath());
        $this->instance('path.resources', $this->resourcePath());
    }

    /**
     * @return string
     */
    public function basePath()
    {
        return $this->basePath;
    }

    /**
     * @return string
     */
    public function defaultConfigPath($path = ''): string
    {
        if (is_null($this->defaultConfigPath)) {
            $this->defaultConfigPath = realpath(__DIR__.'/../../config');
        }

        return $path ? $this->defaultConfigPath.DIRECTORY_SEPARATOR.$path : $this->defaultConfigPath;
    }

    /**
     * @param string $path
     * @return string
     */
    public function modulePath($path = ''): string
    {
        if (is_null($this->modulePath)) {
            $this->modulePath = $this->basePath.DIRECTORY_SEPARATOR.'modules';
        }

        return $path ? $this->modulePath.DIRECTORY_SEPARATOR.$path : $this->modulePath;
    }

    /**
     * Get the path to the application configuration files.
     *
     * @param string $path Optionally, a path to append to the config path
     *
     * @return string
     */
    public function configPath($path = ''): string
    {
        if (is_null($this->configPath)) {
            $this->configPath = $this->basePath.DIRECTORY_SEPARATOR.'config';
        }

        return $path ? $this->configPath.DIRECTORY_SEPARATOR.$path : $this->configPath;
    }

    /**
     * Get the path to the database directory.
     *
     * @param string $path Optionally, a path to append to the database path
     *
     * @return string
     */
    public function databasePath($path = '')
    {
        if (is_null($this->databasePath)) {
            $this->databasePath = $this->basePath.DIRECTORY_SEPARATOR.'database';
        }

        return $path ? $this->databasePath.DIRECTORY_SEPARATOR.$path : $this->databasePath;
    }

    /**
     * Get the path to the language files.
     *
     * @return string
     */
    public function langPath()
    {
        return $this->resourcePath().DIRECTORY_SEPARATOR.'lang';
    }

    /**
     * Get the path to the public / web directory.
     *
     * @return string
     */
    public function publicPath($path = '')
    {
        return $this->basePath.DIRECTORY_SEPARATOR.'public';
    }

    /**
     * Get the path to the storage directory.
     *
     * @return string
     */
    public function storagePath($path = '')
    {
        if (is_null($this->storagePath)) {
            $this->storagePath = $this->basePath.DIRECTORY_SEPARATOR.'storage';
        }

        return $path ? $this->storagePath.DIRECTORY_SEPARATOR.$path : $this->storagePath;
    }

    /**
     * Get the path to the resources directory.
     *
     * @param string $path
     *
     * @return string
     */
    public function resourcePath($path = '')
    {
        if (is_null($this->resourcePath)) {
            $this->resourcePath = $this->basePath.DIRECTORY_SEPARATOR.'resources';
        }

        return $path ? $this->resourcePath.DIRECTORY_SEPARATOR.$path : $this->resourcePath;
    }

    /**
     * Get the path to the environment file directory.
     *
     * @return string
     */
    public function environmentPath()
    {
        return $this->environmentPath ?: $this->basePath;
    }

    /**
     * Set the environment file to be loaded during bootstrapping.
     *
     * @param string $file
     *
     * @return $this
     */
    public function loadEnvironmentFrom($file)
    {
        $this->environmentFile = $file;

        return $this;
    }

    /**
     * Get the environment file the application is using.
     *
     * @return string
     */
    public function environmentFile()
    {
        return $this->environmentFile ?: '.env';
    }

    /**
     * Get the fully qualified path to the environment file.
     *
     * @return string
     */
    public function environmentFilePath()
    {
        return $this->environmentPath().DIRECTORY_SEPARATOR.$this->environmentFile();
    }

    /**
     * Get or check the current application environment.
     *
     * @param  string|array  $environments
     * @return string|bool
     */
    public function environment(...$environments)
    {
        if (count($environments) > 0) {
            $patterns = is_array($environments[0]) ? $environments[0] : $environments;

            return Str::is($patterns, $this['env']);
        }

        return $this['env'];
    }

    /**
     * Detect the application's current environment.
     *
     * @param  \Closure  $callback
     * @return string
     */
    public function detectEnvironment(\Closure $callback)
    {
        $args = $_SERVER['argv'] ?? null;

        return $this['env'] = (new EnvironmentDetector)->detect($callback, $args);
    }

    /**
     * @return bool
     */
    public function runningInConsole()
    {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
    }

    /**
     * @return bool
     */
    public function runningUnitTests()
    {
        return $this['env'] === 'testing';
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function registerConfiguredProviders()
    {
        $providers = Collection::make($this->config['mount.providers'])
            ->partition(function ($provider) {
                return Str::startsWith($provider, 'Illuminate\\') || Str::startsWith($provider, 'CrCms\\Microservice\\');
            });

        $providers->splice(1, 0, [$this->make(PackageManifest::class)->providers()]);

        (new ProviderRepository($this, new Filesystem, $this->getCachedServicesPath()))
            ->load($providers->collapse()->toArray());
    }

    /**
     * Register a service provider with the application.
     *
     * @param  \Illuminate\Support\ServiceProvider|string  $provider
     * @param  bool   $force
     * @return \Illuminate\Support\ServiceProvider
     */
    public function register($provider, $force = false)
    {
        if (($registered = $this->getProvider($provider)) && ! $force) {
            return $registered;
        }

        // If the given "provider" is a string, we will resolve it, passing in the
        // application instance automatically for the developer. This is simply
        // a more convenient way of specifying your service provider classes.
        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }

        $provider->register();

        // If there are bindings / singletons set as properties on the provider we
        // will spin through them and register them with the application, which
        // serves as a convenience layer while registering a lot of bindings.
        if (property_exists($provider, 'bindings')) {
            foreach ($provider->bindings as $key => $value) {
                $this->bind($key, $value);
            }
        }

        if (property_exists($provider, 'singletons')) {
            foreach ($provider->singletons as $key => $value) {
                $this->singleton($key, $value);
            }
        }

        $this->markAsRegistered($provider);

        // If the application has already booted, we will call this boot method on
        // the provider class so it has an opportunity to do its boot logic and
        // will be ready for any usage by this developer's application logic.
        if ($this->booted) {
            $this->bootProvider($provider);
        }

        return $provider;
    }

    /**
     * Get the registered service provider instance if it exists.
     *
     * @param  \Illuminate\Support\ServiceProvider|string  $provider
     * @return \Illuminate\Support\ServiceProvider|null
     */
    public function getProvider($provider)
    {
        return array_values($this->getProviders($provider))[0] ?? null;
    }

    /**
     * Get the registered service provider instances if any exist.
     *
     * @param  \Illuminate\Support\ServiceProvider|string  $provider
     * @return array
     */
    public function getProviders($provider)
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return Arr::where($this->serviceProviders, function ($value) use ($name) {
            return $value instanceof $name;
        });
    }

    /**
     * Resolve a service provider instance from the class name.
     *
     * @param  string  $provider
     * @return \Illuminate\Support\ServiceProvider
     */
    public function resolveProvider($provider)
    {
        return new $provider($this);
    }

    /**
     * Mark the given provider as registered.
     *
     * @param  \Illuminate\Support\ServiceProvider  $provider
     * @return void
     */
    protected function markAsRegistered($provider)
    {
        $this->serviceProviders[] = $provider;

        $this->loadedProviders[get_class($provider)] = true;
    }

    /**
     * Load and boot all of the remaining deferred providers.
     *
     * @return void
     */
    public function loadDeferredProviders()
    {
        // We will simply spin through each of the deferred providers and register each
        // one and boot them if the application has booted. This should make each of
        // the remaining services available to this application for immediate use.
        foreach ($this->deferredServices as $service => $provider) {
            $this->loadDeferredProvider($service);
        }

        $this->deferredServices = [];
    }

    /**
     * Load the provider for a deferred service.
     *
     * @param  string  $service
     * @return void
     */
    public function loadDeferredProvider($service)
    {
        if (! isset($this->deferredServices[$service])) {
            return;
        }

        $provider = $this->deferredServices[$service];

        // If the service provider has not already been loaded and registered we can
        // register it with the application and remove the service from this list
        // of deferred services, since it will already be loaded on subsequent.
        if (! isset($this->loadedProviders[$provider])) {
            $this->registerDeferredProvider($provider, $service);
        }
    }

    /**
     * Register a deferred provider and service.
     *
     * @param  string  $provider
     * @param  string|null  $service
     * @return void
     */
    public function registerDeferredProvider($provider, $service = null)
    {
        // Once the provider that provides the deferred service has been registered we
        // will remove it from our local list of the deferred services with related
        // providers so that this container does not try to resolve it out again.
        if ($service) {
            unset($this->deferredServices[$service]);
        }

        $this->register($instance = new $provider($this));

        if (! $this->booted) {
            $this->booting(function () use ($instance) {
                $this->bootProvider($instance);
            });
        }
    }

    /**
     * Resolve the given type from the container.
     *
     * (Overriding Container::make)
     *
     * @param  string  $abstract
     * @param  array  $parameters
     * @return mixed
     */
    public function make($abstract, array $parameters = [])
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->deferredServices[$abstract]) && ! isset($this->instances[$abstract])) {
            $this->loadDeferredProvider($abstract);
        }

        return parent::make($abstract, $parameters);
    }

    /**
     * Determine if the given abstract type has been bound.
     *
     * (Overriding Container::bound)
     *
     * @param  string  $abstract
     * @return bool
     */
    public function bound($abstract)
    {
        return isset($this->deferredServices[$abstract]) || parent::bound($abstract);
    }

    /**
     * Determine if the application has booted.
     *
     * @return bool
     */
    public function isBooted()
    {
        return $this->booted;
    }

    /**
     * Boot the application's service providers.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }

        // Once the application has booted we will also fire some "booted" callbacks
        // for any listeners that need to do work after this initial booting gets
        // finished. This is useful when ordering the boot-up processes we run.
        $this->fireAppCallbacks($this->bootingCallbacks);

        array_walk($this->serviceProviders, function ($p) {
            $this->bootProvider($p);
        });

        $this->booted = true;

        $this->fireAppCallbacks($this->bootedCallbacks);
    }

    /**
     * Boot the given service provider.
     *
     * @param  \Illuminate\Support\ServiceProvider  $provider
     * @return mixed
     */
    protected function bootProvider(ServiceProvider $provider)
    {
        if (method_exists($provider, 'boot')) {
            return $this->call([$provider, 'boot']);
        }
    }

    /**
     * Register a new boot listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function booting($callback)
    {
        $this->bootingCallbacks[] = $callback;
    }

    /**
     * Register a new "booted" listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function booted($callback)
    {
        $this->bootedCallbacks[] = $callback;

        if ($this->isBooted()) {
            $this->fireAppCallbacks([$callback]);
        }
    }

    /**
     * Call the booting callbacks for the application.
     *
     * @param  callable[]  $callbacks
     * @return void
     */
    protected function fireAppCallbacks(array $callbacks)
    {
        foreach ($callbacks as $callback) {
            call_user_func($callback, $this);
        }
    }

    /**
     * Determine if middleware has been disabled for the application.
     *
     * @return bool
     */
    public function shouldSkipMiddleware()
    {
        return $this->bound('middleware.disable') &&
               $this->make('middleware.disable') === true;
    }

    /**
     * Get the path to the cached services.php file.
     *
     * @return string
     */
    public function getCachedServicesPath()
    {
        return $_ENV['APP_SERVICES_CACHE'] ?? $this->storagePath('run-cache/services.php');
    }

    /**
     * Get the path to the cached packages.php file.
     *
     * @return string
     */
    public function getCachedPackagesPath()
    {
        return $_ENV['APP_PACKAGES_CACHE'] ?? $this->storagePath('run-cache/packages.php');
    }

    /**
     * Determine if the application configuration is cached.
     *
     * @return bool
     */
    public function configurationIsCached()
    {
        return file_exists($this->getCachedConfigPath());
    }

    /**
     * @return string
     */
    public function getCachedConfigPath(): string
    {
        return $_ENV['APP_CONFIG_CACHE'] ?? $this->storagePath('run-cache/config.php');
    }

    /**
     * @return bool
     */
    public function isDownForMaintenance()
    {
        return file_exists($this->storagePath('framework/down'));
    }

    /**
     * Register a terminating callback with the application.
     *
     * @param \Closure $callback
    /**
     * Register a terminating callback with the application.
     *
     * @param  callable|string  $callback
     * @return $this
     */
    public function terminating($callback)
    {
        $this->terminatingCallbacks[] = $callback;

        return $this;
    }

    /**
     * Terminate the application.
     *
     * @return void
     */
    public function terminate()
    {
        foreach ($this->terminatingCallbacks as $terminating) {
            $this->call($terminating);
        }
    }

    /**
     * Get the service providers that have been loaded.
     *
     * @return array
     */
    public function getLoadedProviders()
    {
        return $this->loadedProviders;
    }

    /**
     * Get the application's deferred services.
     *
     * @return array
     */
    public function getDeferredServices()
    {
        return $this->deferredServices;
    }

    /**
     * Set the application's deferred services.
     *
     * @param  array  $services
     * @return void
     */
    public function setDeferredServices(array $services)
    {
        $this->deferredServices = $services;
    }

    /**
     * Add an array of services to the application's deferred services.
     *
     * @param  array  $services
     * @return void
     */
    public function addDeferredServices(array $services)
    {
        $this->deferredServices = array_merge($this->deferredServices, $services);
    }

    /**
     * Determine if the given service is a deferred service.
     *
     * @param  string  $service
     * @return bool
     */
    public function isDeferredService($service)
    {
        return isset($this->deferredServices[$service]);
    }

    /**
     * Register the core class aliases in the container.
     *
     * @return void
     */
    protected function registerCoreContainerAliases()
    {
        foreach ([
                     'app' => [static::class, \Illuminate\Contracts\Container\Container::class, \Illuminate\Contracts\Foundation\Application::class, \Psr\Container\ContainerInterface::class],
                     'cache' => [\Illuminate\Cache\CacheManager::class, \Illuminate\Contracts\Cache\Factory::class],
                     'cache.store' => [\Illuminate\Cache\Repository::class, \Illuminate\Contracts\Cache\Repository::class],
                     'config' => [\Illuminate\Config\Repository::class, \Illuminate\Contracts\Config\Repository::class],
                     'encrypter' => [\Illuminate\Encryption\Encrypter::class, \Illuminate\Contracts\Encryption\Encrypter::class],
                     'db' => [\Illuminate\Database\DatabaseManager::class],
                     'db.connection' => [\Illuminate\Database\Connection::class, \Illuminate\Database\ConnectionInterface::class],
                     'events' => [\Illuminate\Events\Dispatcher::class, \Illuminate\Contracts\Events\Dispatcher::class],
                     'files' => [\Illuminate\Filesystem\Filesystem::class],
                     'filesystem' => [\Illuminate\Filesystem\FilesystemManager::class, \Illuminate\Contracts\Filesystem\Factory::class],
                     'filesystem.disk' => [\Illuminate\Contracts\Filesystem\Filesystem::class],
                     'filesystem.cloud' => [\Illuminate\Contracts\Filesystem\Cloud::class],
                     'hash' => [\Illuminate\Hashing\HashManager::class],
                     'hash.driver' => [\Illuminate\Contracts\Hashing\Hasher::class],
                     'translator' => [\Illuminate\Translation\Translator::class, \Illuminate\Contracts\Translation\Translator::class],
                     'log' => [\Illuminate\Log\LogManager::class, \Psr\Log\LoggerInterface::class],
                     'queue' => [\Illuminate\Queue\QueueManager::class, \Illuminate\Contracts\Queue\Factory::class, \Illuminate\Contracts\Queue\Monitor::class],
                     'queue.connection' => [\Illuminate\Contracts\Queue\Queue::class],
                     'queue.failer' => [\Illuminate\Queue\Failed\FailedJobProviderInterface::class],
                     'redis' => [\Illuminate\Redis\RedisManager::class, \Illuminate\Contracts\Redis\Factory::class],
                     'validator' => [\Illuminate\Validation\Factory::class, \Illuminate\Contracts\Validation\Factory::class],
                 ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    /**
     * Flush the container of all bindings and resolved instances.
     *
     * @return void
     */
    public function flush()
    {
        parent::flush();

        $this->buildStack = [];
        $this->loadedProviders = [];
        $this->bootedCallbacks = [];
        $this->bootingCallbacks = [];
        $this->deferredServices = [];
        $this->reboundCallbacks = [];
        $this->serviceProviders = [];
        $this->resolvingCallbacks = [];
        $this->afterResolvingCallbacks = [];
        $this->globalResolvingCallbacks = [];
    }
}
