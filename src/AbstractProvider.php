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
     * Generate a URL for the provider
     *
     * @access public
     * @param array $source The source config array
     * @param string|Asset $asset The asset URL
     * @param array $params An array of parameters
     * @return string
     * @throws \InvalidArgumentException
     */
    abstract public function getUrl($source, $asset, $params);
}
