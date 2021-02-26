<?php

namespace croxton\imgixer\providers;

use Craft;
use craft\elements\Asset;
use croxton\imgixer\Imgixer;
use croxton\imgixer\AbstractProvider;
use Imgix\UrlBuilder;

class ServdProvider extends AbstractProvider
{
    /**
     * Generate a URL
     *
     * @access protected
     * @param array $source The source config array
     * @param string|Asset $asset The asset URL
     * @param array $params An array of parameters
     * @return strin|null
     * @throws \InvalidArgumentException
     */
    public function getUrl($source, $asset, $params) {

        // Check we have an Asset
        if ( is_string($asset) || ! ($asset instanceof Asset)) {
            throw new \InvalidArgumentException('The Servd provider must be passed an Asset object.');
        }

        // Add timestamp
        $params['dm'] = $asset->dateUpdated->getTimestamp();

        // Check we have a domain
        if ( isset($source['domain'])) {
            // Yes - we'll assume we're using an Imgix web folder or proxy source
            return $this->servdImgixUrl($source, $asset, $params);
        } else {
            // No - We'll used Servd's own image transformation service
            return $this->servdTransformUrl($source, $asset, $params);
        }
    }

    /**
     * Generate src and srcset values, optionally for a range of image sizes
     *
     * @access public
     * @param array $source The source config array
     * @param Asset $asset The asset
     * @param array $params An array of parameters
     * @return string|null
     */
    protected function servdTransformUrl($source, Asset $asset, $params) {

        // Use Servd's ImageTransforms class
        if ( ! class_exists('\servd\AssetStorage\AssetsPlatform\ImageTransforms')) {
            return null;
        }
        $imageTransforms = new \servd\AssetStorage\AssetsPlatform\ImageTransforms;

        // Get the volume
        $volume = $asset->getVolume();

        // merge any default params
        if ( isset($source['defaultParams'])) {
            $params = array_merge($source['defaultParams'], $params);
        }

        // if 'ar' param set and either 'h' or 'w',
        // calculate the corresponding dimension if it is missing
        if (isset($params['ar'])) {
            $ar = 0;
            $ar = array_map('intval', explode(':', $params['ar']));
            if ( isset($ar[0]) && isset($ar[1])) {
                $ar = $ar[0]/$ar[1];
            }
            if ($ar > 0) {
                if ( isset($params['w']) && ! isset($params['h']) ) {
                    $params['h'] = intval($params['w']) / $ar;
                } elseif ( isset($params['h']) && ! isset($params['w']) ) {
                    $params['w'] = intval($params['h']) * $ar;
                }
            }
        }

        // Only allow params supported by Servd
        $allowedParams = array_flip(array('w', 'h', 'q', 'fm', 'auto', 'fit', 'crop', 'fp-x', 'fp-y', 'fill-color', 'dpr', 'dm'));
        $params = array_intersect_key($params, $allowedParams);

        // Full path of asset on the CDN platform
        $fullPath = $imageTransforms->getFullPathForAssetAndTransform($asset, $params);
        if ( ! $fullPath) {
            return null;
        }

        // Sign
        $signingKey = $imageTransforms->getKeyForPath($fullPath);
        $params['s'] = $signingKey;

        // Use a custom URL template if one has been provided
        $customPattern = Craft::parseEnv($volume->optimiseUrlPattern);
        if (!empty($customPattern)) {
            $settings = \servd\AssetStorage\Plugin::getInstance()->getSettings();
            $variables = [
                "environment" => $settings->getAssetsEnvironment(),
                "projectSlug" => $settings->getProjectSlug(),
                "subfolder" => trim($volume->customSubfolder, "/"),
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
        return 'https://optimise2.assets-servd.host/' . $fullPath . '&s=' . $signingKey;
    }

    /**
     * Generate src and srcset values, optionally for a range of image sizes
     *
     * @access public
     * @param array $source The source config array
     * @param Asset $asset The asset
     * @param array $params An array of parameters
     * @return string|null
     */
    protected function servdImgixUrl($source, Asset $asset, $params) {

        // Get the full path to the iamhe
        $img = $asset->path;
        $volume = $asset->getVolume();
        $img = ltrim(trim($volume->subfolder, '/') . '/' . $img, '/');

        // Sign the image?
        if ( isset($params['signed'])) {
            $signed = (bool) $params['signed'];
        } else {
            $signed = isset($source['signed']) ? (bool) $source['signed'] : false;
        }

        // Cleanup params
        unset($params['signed'], $params['source']);

        // merge any default params
        if ( isset($source['defaultParams'])) {
            $params = array_merge($source['defaultParams'], $params);
        }

        // Sign key
        $key = null;
        if ($signed && isset($source['key']) && ! empty($source['key']))
        {
            $key = $source['key'];
        }

        // Build Imgix URL
        return $this->buildImgixUrl($source['domain'], $img, $params, $key);
    }
}