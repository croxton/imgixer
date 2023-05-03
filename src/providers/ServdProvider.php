<?php

namespace croxton\imgixer\providers;

use Craft;
use craft\elements\Asset;
use craft\helpers\App;
use croxton\imgixer\Imgixer;
use croxton\imgixer\AbstractProvider;
use Imgix\UrlBuilder;
use yii\base\InvalidConfigException;

class ServdProvider extends AbstractProvider
{
    /**
     * Generate a URL
     *
     * @access public
     * @param array $source The source config array
     * @param Asset|string $asset The asset URL
     * @param array $params An array of parameters
     * @return string|null
     * @throws \InvalidArgumentException
     */
    public function getUrl(array $source, Asset|string $asset, array $params): ?string
    {
        // v3 supports custom endpoints
        if ( ! isset($source['domain']) && ! isset($source['endpoint'])) {
            $source['endpoint'] = 'https://optimise2.assets-servd.host'; // default in v2
        } elseif (isset($source['domain'])) {
            $source['endpoint'] = $source['domain'];
        }

        // Check we have an Asset
        if ( is_string($asset) || ! ($asset instanceof Asset)) {
            throw new \InvalidArgumentException('The Servd provider must be passed an Asset object.');
        }

        // Add timestamp
        $params['dm'] = $asset->dateUpdated->getTimestamp();

        // We'll used Servd's own image transformation service
        return $this->servdTransformUrl($source, $asset, $params);
    }

    /**
     * Generate an image transform URL using Servd's transform service
     *
     * @access protected
     * @param array $source The source config array
     * @param Asset $asset The asset
     * @param array $params An array of parameters
     * @return string|null
     * @throws InvalidConfigException
     */
    protected function servdTransformUrl(array $source, Asset $asset, array $params): ?string
    {
        // Use Servd's ImageTransforms class
        if ( ! class_exists('\servd\AssetStorage\AssetsPlatform\ImageTransforms')) {
            return null;
        }
        $imageTransforms = new \servd\AssetStorage\AssetsPlatform\ImageTransforms;

        // Get the filesystem
        $fs = $asset->getVolume()->getFs();

        // Only allow params supported by Servd
        // Based on Serverless Sharp - see https://venveo.github.io/serverless-sharp/docs/usage/parameters
        $allowedParams = array_flip(array('w', 'h', 'q', 'fm', 'auto', 'fit', 'crop', 'fp-x', 'fp-y', 'fill-color', 'dpr', 'ar', 'dm'));
        $params = array_intersect_key($params, $allowedParams);

        // Servd does not support faces / facearea
        // Use the image's focalpoint as a fallback (if defined)
        if (isset($params['crop']) && str_contains($params['crop'], "faces")) {
            $params['fit'] = 'crop';
            $params['crop'] = 'focalpoint';
        }
        if (isset($params['fit']) && str_contains($params['fit'], "facearea")) {
            $params['fit'] = 'crop';
            $params['crop'] = 'focalpoint';
        }

        // Servd does not support fillmax
        if (isset($params['fit']) && str_contains($params['fit'], "fillmax")) {
            $params['fit'] = 'fill';
        }

        // make sure fit="fill" when fill-color is specified
        if (isset($params['fill-color']) && !isset($params['fit'])) {
            $params['fit'] = 'fill';
        }

        // Full path of asset on the CDN platform
        $fullPath = $imageTransforms->getFullPathForAssetAndTransform($asset, $params);
        if ( ! $fullPath) {
            return null;
        }

        // Sign
        $signingKey = $imageTransforms->getKeyForPath($fullPath);
        $params['s'] = $signingKey;

        $normalizedCustomSubfolder = App::parseEnv($fs->customSubfolder);

        // Use a custom URL template if one has been provided
        $customPattern = App::parseEnv($fs->optimiseUrlPattern);
        if (!empty($customPattern)) {
            $settings = \servd\AssetStorage\Plugin::getInstance()->getSettings();
            $variables = [
                "environment" => $settings->getAssetsEnvironment(),
                "projectSlug" => $settings->getProjectSlug(),
                "subfolder" => trim($normalizedCustomSubfolder, "/"),
                "filePath" => $asset->getPath(),
                "params" => '?' . http_build_query($params),
            ];
            $finalUrl = $customPattern;
            foreach ($variables as $key => $value) {
                $finalUrl = str_replace('{{' . $key . '}}', $value, $finalUrl);
            }
            return $finalUrl;
        }

        // Otherwise
        return rtrim($source['endpoint'], '/') . '/' . $fullPath . '&s=' . $signingKey;
    }
}