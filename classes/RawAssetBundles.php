<?php
/**
 * UserFrosting (http://www.userfrosting.com)
 *
 * @link      https://github.com/userfrosting/adapter-grav
 * @license   https://github.com/userfrosting/UserFrosting/blob/master/licenses/UserFrosting.md (MIT License)
 */
namespace Grav\Plugin;

use UserFrosting\Assets\AssetBundles\GulpBundleAssetsRawBundles;
use UserFrosting\Assets\Exception\InvalidBundlesFileException;

/**
 * RawAssetBundles Class
 *
 * Extends GulpBundleAssetsRawBundles with an extend method that merges the referenced asset bundles with special collision logic.
 *
 * @author Alex Weissman (https://alexanderweissman.com)
 */
 class RawAssetBundles extends GulpBundleAssetsRawBundles
 {
    /**
     * Extends the currently loaded bundles with another bundle schema.
     *
     * @param string $filePath
     *
     * @throws \UserFrosting\Support\Exception\FileNotFoundException if file cannot be found.
     * @throws \UserFrosting\Support\Exception\JsonException if file cannot be parsed as JSON.
     * @throws InvalidBundlesFileException if unexpected value encountered.
     */
    public function extend($filePath)
    {
        if (!is_string($filePath)) {
            throw new \InvalidArgumentException("\$filePath must of type string but was " . gettype($filePath));
        }

        // Read file
        $bundlesFile = $this->readSchema($filePath);

        // Process bundles
        if (isset($bundlesFile->bundle)) {
            foreach ($bundlesFile->bundle as $bundleName => $bundle) {
                // Get collision setting.
                $collisionRule = ($bundle->options->sprinkle->onCollision ?: 'replace');

                // Handle CSS bundle if specified.
                if (isset($bundle->styles)) {
                    // Attempt to add CSS bundle
                    try {
                        $standardisedBundle = $this->standardiseBundle($bundle->styles);
                        if (!array_key_exists($bundleName, $this->cssBundles)) {
                            $this->cssBundles[$bundleName] = $standardisedBundle;
                        } else {
                            switch ($collisionRule) {
                                case 'replace':
                                    // Replaces the existing bundle.
                                    $this->cssBundles[$bundleName] = $standardisedBundle;
                                    break;
                                case 'merge':
                                    // Merge with existing bundle.
                                    foreach ($standardisedBundle as $assetPath) {
                                        if (!in_array($assetPath, $this->cssBundles[$bundleName])) {
                                            $this->cssBundles[$bundleName][] = $assetPath;
                                        }
                                    }
                                    break;
                                case 'ignore':
                                    break;
                                case 'error':
                                    throw new \ErrorException("The bundle '$bundleName' is already defined.");
                                    break;
                                default:
                                    throw new \OutOfBoundsException("Invalid value '$collisionRule' provided for 'onCollision' key in bundle '$bundleName'.");
                                    break;
                            }
                        }
                    }
                    catch (\Exception $e) {
                        throw new InvalidBundlesFileException("Encountered issue processing styles property for '$bundleName' for file '$filePath'", 0, $e);
                    }
                }

                // Handle JS bundle if specified.
                if (isset($bundle->scripts)) {
                    // Attempt to add JS bundle
                    try {
                        $standardisedBundle = $this->standardiseBundle($bundle->scripts);
                        if (!array_key_exists($bundleName, $this->jsBundles)) {
                            $this->jsBundles[$bundleName] = $standardisedBundle;
                        } else {
                            switch ($collisionRule) {
                                case 'replace':
                                    // Replaces the existing bundle.
                                    $this->jsBundles[$bundleName] = $standardisedBundle;
                                    break;
                                case 'merge':
                                    // Merge with existing bundle.
                                    foreach ($standardisedBundle as $assetPath) {
                                        if (!in_array($assetPath, $this->jsBundles[$bundleName])) {
                                            $this->jsBundles[$bundleName][] = $assetPath;
                                        }
                                    }
                                    break;
                                case 'ignore':
                                    break;
                                case 'error':
                                    throw new \ErrorException("The bundle '$bundleName' is already defined.");
                                    break;
                                default:
                                    throw new \OutOfBoundsException("Invalid value '$collisionRule' provided for 'onCollision' key in bundle '$bundleName'.");
                                    break;
                            }
                        }
                    }
                    catch (\Exception $e) {
                        throw new InvalidBundlesFileException("Encountered issue processing scripts property for '$bundleName' for file '$filePath'", 0, $e);
                    }
                }
            }
        }
    }
}
