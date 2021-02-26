<?php

namespace croxton\imgixer;

use Craft;
use craft\elements\Asset;
use croxton\imgixer\Imgixer;
use Imgix\UrlBuilder;

abstract class AbstractProvider
{
    /**
     * @var Asset $asset
     */
    protected $asset;

    /**
     * @var string $source
     */
    protected $source;

    /**
     * @var array $source
     */
    protected $params;

    /**
     * Generate a URL
     *
     * @access protected
     * @param array $source The source config array
     * @param string|Asset $asset The asset URL
     * @param array $params An array of parameters
     * @return string
     * @throws \InvalidArgumentException
     */
    abstract public function getUrl($source, $asset, $params);

    /**
     * Build an Imgix URL
     *
     * @param string $domain The Imgix source domain
     * @param string $img The image path
     * @param array $params An array of Imgix parameters
     * @param string|null $key An optional key used to sign images
     * @return string
     */
    protected function buildImgixUrl($domain, $img, $params=array(), $key=null)
    {
        // build image URL
        $builder = new UrlBuilder($domain);
        $builder->setUseHttps(true);

        if ($key !== null)
        {
            $builder->setSignKey($key);
        }

        return $builder->createURL($img, $params);
    }
}
