<?php

namespace croxton\imgixer\providers;

use Craft;
use craft\elements\Asset;
use croxton\imgixer\Imgixer;
use croxton\imgixer\AbstractProvider;
use Imgix\UrlBuilder;

class ImgixProvider extends AbstractProvider
{
    /**
     * Generate a standard Imgix URL
     *
     * @access public
     * @param array $source The source config array
     * @param string|Asset $asset The asset URL
     * @param array $params An array of parameters
     * @return string
     */
    public function getUrl($source, $asset, $params) {

        // Unless setup with a custom domain, imgix source urls take the form [source].imgix.net
        if ( ! isset($source['domain']) && ! isset($source['endpoint'])) {
            $source['endpoint'] = $source['handle'] . '.imgix.net';
        } elseif (isset($source['domain'])) {
            $source['endpoint'] = $source['domain'];
        }

        // Image path
        $img = $asset;
        if ( ! is_string($asset) && $asset instanceof Asset) {
            $img = $asset->path;
            // when an image has been modified, ensure a new imgix version is generated
            $params['dm'] = $asset->dateModified->getTimestamp();
        }

        // Prefix img path with subfolder, if defined
        if ( isset($source['subfolder'])) {
            $img = $source['subfolder'] .'/'. $img;
        } elseif ( ! is_string($asset) && $asset instanceof Asset) {
            // Get the filesystem
            $fs = $asset->getVolume()->getFs();
            if (isset($fs->subfolder) && ! empty($fs->subfolder)) {
                $img = ltrim(trim($fs->subfolder, '/') . '/' . $img, '/');
            }
        }

        // Sign the image?
        if ( isset($params['signed'])) {
            $signed = (bool) $params['signed'];
        } else {
            $signed = isset($source['signed']) ? (bool) $source['signed'] : false;
        }

        // Cleanup params
        unset($params['signed'], $params['source']);

        // Merge any default params
        if ( isset($source['defaultParams'])) {
            $params = array_merge($source['defaultParams'], $params);
        }

        $transforms = [];

        foreach ($params as $key => $value) {
            switch($key) {

                // support a custom 'radius' parameter, for simple rounded corners
                // with a transparent background
                case 'radius' :
                    $transforms['mask'] = 'corners';
                    $transforms['corner-radius'] = $value;
                    if ( ! isset($transforms['fm'])) {
                        // widest transparent background support with lowest filesize
                        $transforms['fm'] = 'webp';
                    }
                    break;

                default :
                    $transforms[$key] = $value;
                    break;
            }
        }

        // Sign key
        $key = null;
        if ($signed && isset($source['key']) && ! empty($source['key']))
        {
            $key = $source['key'];
        }

        if ($signed && isset($source['privateKey']) && ! empty($source['privateKey']))
        {
            $key = $source['privateKey'];
        }

        // Build Imgix URL
        return $this->buildImgixUrl($source['endpoint'], $img, $transforms, $key);
    }

    /**
     * Build an Imgix URL
     *
     * @access protected
     * @param string $endpoint The Imgix source domain
     * @param string $img The image path
     * @param array $params An array of Imgix parameters
     * @param string|null $key An optional key used to sign images
     * @return string
     */
    protected function buildImgixUrl($endpoint, $img, $params=array(), $key=null) {
        // build image URL
        $builder = new UrlBuilder($endpoint);
        $builder->setUseHttps(true);

        if ($key !== null)
        {
            $builder->setSignKey($key);
        }

        return $builder->createURL($img, $params);
    }
}