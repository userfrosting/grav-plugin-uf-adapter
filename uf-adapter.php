<?php
/**
 * UserFrosting (http://www.userfrosting.com)
 *
 * @link      https://github.com/userfrosting/adapter-grav
 * @copyright Copyright (c) 2013-2017 Alexander Weissman
 * @license   https://github.com/userfrosting/UserFrosting/blob/master/licenses/UserFrosting.md (MIT License)
 */
namespace Grav\Plugin;

use Dotenv\Dotenv;
use Grav\Common\Plugin;
use Grav\Common\Uri;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use UserFrosting\Assets\AssetBundleSchema;
use UserFrosting\Assets\AssetLoader;
use UserFrosting\Assets\AssetManager;
use UserFrosting\Assets\UrlBuilder\AssetUrlBuilder;
use UserFrosting\Assets\UrlBuilder\CompiledAssetUrlBuilder;
use UserFrosting\Config\Config;

/**
 * Class UFAdapterPlugin
 *
 * @package Grav\Plugin
 * @author Alex Weissman (https://alexanderweissman.com)
 */
class UFAdapterPlugin extends Plugin
{
    protected $sprinkles;

    protected $sprinklesPath;

    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onTwigExtensions' => ['onTwigExtensions', 0],
            'onTwigInitialized' => ['onTwigInitialized', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0]
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        $this->sprinklesPath = \UserFrosting\APP_DIR . '/' . \UserFrosting\SPRINKLES_DIR_NAME;

        // Attempt to fetch list of Sprinkles
        $sprinklesFile = file_get_contents($this->sprinklesPath . '/sprinkles.json');
        if ($sprinklesFile === false) {
            die(PHP_EOL . "File 'app/sprinkles/sprinkles.json' not found. Please create a 'sprinkles.json' file and try again." . PHP_EOL);
        }
        $this->sprinkles = json_decode($sprinklesFile)->base;

        // Add core sprinkle
        array_unshift($this->sprinkles, "core");

        $this->registerThemeServices();
    }

    public function onTwigExtensions()
    {
        require_once(__DIR__ . '/classes/UFExtension.php');
        $ufExtension = new UFExtension($this->grav);
        $this->grav['twig']->twig->addExtension($ufExtension);
    }

    public function onTwigInitialized()
    {
        $loadUFTemplatesLast = $this->config->get('plugins.uf-adapter.twig.uf_last');
        $locator = $this->grav['ufLocator'];

        $view = $this->grav['twig'];

        $loader = $view->loader();

        $sprinkles = $loadUFTemplatesLast ? $this->sprinkles : array_reverse($this->sprinkles);

        // Add other Sprinkles' templates namespaces
        foreach ($sprinkles as $sprinkle) {
            if ($path = $locator->findResource('sprinkles://'.$sprinkle.'/templates/', true, false)) {
                if ($loadUFTemplatesLast) {
                    $loader->prependPath($path);
                    $loader->prependPath($path, 'userfrosting');
                } else {
                    $loader->addPath($path);
                    $loader->addPath($path, 'userfrosting');
                }
                $loader->addPath($path, $sprinkle);
            }
        }
    }

    public function onTwigSiteVariables()
    {
        // Get Grav variables
        $gravTwig = $this->grav['twig'];

        // TODO: assign the uf config variables to the Grav plugin's config?
        $ufConfig = $this->grav['ufConfig'];
        
        // Update Grav's global Twig variables with values from UF
        $gravTwig->twig_vars['site'] = array_replace_recursive($gravTwig->twig_vars['site'], $ufConfig['site'],
        [
            'uri'       =>  [
                'public' => $ufConfig['site.uri.main']      // Use the 'main' site URL instead of the one constructed by UF (which would point to the blog subpath)
            ]
        ]);
    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    protected function registerThemeServices()
    {
        $this->grav['ufAssets'] = function ($c) {
            $config = $c['ufConfig'];
            $locator = $c['ufLocator'];

            // Load asset schema
            if ($config['assets.use_raw']) {
                $baseUrl = $config['site.uri.main'] . '/' . $config['assets.raw.path'];
                $removePrefix = \UserFrosting\APP_DIR_NAME . \UserFrosting\DS . \UserFrosting\SPRINKLES_DIR_NAME;
                $aub = new AssetUrlBuilder($locator, $baseUrl, $removePrefix, 'assets');

                $as = new AssetBundleSchema($aub);

                // Load schema for all sprinkles
                foreach ($this->sprinkles as $sprinkle) {
                    $resource = $locator->findResource("sprinkles://$sprinkle/" . $config['assets.raw.schema'], true, true);

                    if (file_exists($resource)) {
                        $as->loadRawSchemaFile($resource);
                    }
                }
            } else {
                $baseUrl = $config['site.uri.main'] . '/' . $config['assets.compiled.path'];
                $aub = new CompiledAssetUrlBuilder($baseUrl);

                $as = new AssetBundleSchema($aub);
                $as->loadCompiledSchemaFile($locator->findResource("build://" . $config['assets.compiled.schema'], true, true));
            }

            $am = new AssetManager($aub, $as);

            return $am;
        };

        $this->grav['ufConfig'] = function ($c) {
            // Grab any relevant dotenv variables from the .env file
            try {
                $dotenv = new Dotenv(\UserFrosting\APP_DIR);
                $dotenv->load();
            } catch (InvalidPathException $e) {
                // Skip loading the environment config file if it doesn't exist.
            }

            // Create and inject new config item
            $config = new Config();

            // Add search paths for all config files.
            foreach ($this->sprinkles as $sprinkle) {
                $config->addPath($this->sprinklesPath . '/' . $sprinkle . '/config');
            }

            // Get configuration mode from environment
            $mode = getenv("UF_MODE") ?: "";
            $config->loadConfigurationFiles($mode);

            // Construct base url from components, if not explicitly specified
            if (!isset($config['site.uri.public'])) {
                $baseUri = $config['site.uri.base'];

                $public = Uri::buildUrl(
                    $baseUri['scheme'],
                    $baseUri['host'],
                    $baseUri['port'],
                    $baseUri['path']
                );

                // Slim\Http\Uri likes to add trailing slashes when the path is empty, so this fixes that.
                $config['site.uri.public'] = trim($public, '/');
            }

            if (isset($config['display_errors'])) {
                ini_set("display_errors", $config['display_errors']);
            }

            // Configure error-reporting
            if (isset($config['error_reporting'])) {
                error_reporting($config['error_reporting']);
            }

            // Configure time zone
            if (isset($config['timezone'])) {
                date_default_timezone_set($config['timezone']);
            }

            return $config;
        };

        $this->grav['ufLocator'] = function ($c) {

            // Build a locator for finding UF resources
            $locator = new UniformResourceLocator(\UserFrosting\ROOT_DIR);
            $locator->addPath('build', '', \UserFrosting\BUILD_DIR_NAME);
            $locator->addPath('sprinkles', '', \UserFrosting\APP_DIR_NAME . '/' . \UserFrosting\SPRINKLES_DIR_NAME);

            foreach ($this->sprinkles as $sprinkle) {
                $sprinklePath = \UserFrosting\APP_DIR_NAME . '/' . \UserFrosting\SPRINKLES_DIR_NAME . \UserFrosting\DS . $sprinkle;
                $locator->addPath('assets', '', $sprinklePath . \UserFrosting\DS . \UserFrosting\ASSET_DIR_NAME);
                $locator->addPath('config', '', $sprinklePath . \UserFrosting\DS . \UserFrosting\CONFIG_DIR_NAME);
                $locator->addPath('templates', '', $sprinklePath . \UserFrosting\DS . \UserFrosting\TEMPLATE_DIR_NAME);
            }

            return $locator;
        };
    }
}
