<?php
/**
 * UserFrosting (http://www.userfrosting.com)
 *
 * @link      https://github.com/userfrosting/adapter-grav
 * @license   https://github.com/userfrosting/UserFrosting/blob/master/licenses/UserFrosting.md (MIT License)
 */
namespace Grav\Plugin;

use Pimple\Container;

/**
 * Extends Twig functionality for adapting with Grav.
 *
 * @author Alex Weissman (https://alexanderweissman.com)
 */
class UFExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface The global container object, which holds all your services.
     */
    protected $services;

    /**
     * Constructor.
     *
     * @param ContainerInterface $services The global container object, which holds all your services.
     */
    public function __construct(Container $services)
    {
        $this->services = $services;
    }

    /**
     * Get the name of this extension.
     *
     * @return string
     */
    public function getName()
    {
        return 'userfrosting/adapter-grav';
    }

    /**
     * Adds custom Twig functions.
     *
     * @return array[\Twig_SimpleFunction]
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('errorLog', function ($obj) {
                error_log(print_r($obj, true));
            })
        );
    }

    /**
     * Adds custom Twig global variables.
     *
     * @return array[mixed]
     */
    public function getGlobals()
    {
        return [
            'ufAssets' => $this->services['ufAssets']
        ];
    }
}
