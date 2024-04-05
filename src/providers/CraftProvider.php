<?php

namespace croxton\imgixer\providers;

use craft\elements\Asset;
use craft\models\ImageTransform;
use croxton\imgixer\AbstractProvider;
use InvalidArgumentException;
use yii\base\InvalidConfigException;

class CraftProvider extends AbstractProvider
{
    /**
     * Generate a URL
     *
     * @access public
     * @param array $source The source config array
     * @param Asset|string $asset The asset URL
     * @param array $params An array of parameters
     * @return string|null
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public function getUrl(array $source, Asset|string $asset, array $params): ?string
    {
        // Check we have an Asset
        if ( ! $asset instanceof Asset) {
            throw new InvalidArgumentException('The Craft provider must be passed an Asset object.');
        }

        // We'll used Craft's native image transformation service
        return $this->craftTransformUrl($asset, $params);
    }

    /**
     * Generate an image transform URL using Craft's transform service
     *
     * @access protected
     * @param Asset $asset The asset
     * @param array $params An array of parameters
     * @return string|null
     * @throws InvalidConfigException
     */
    protected function craftTransformUrl(Asset $asset, array $params): ?string
    {
        // Only allow a limited set of params that we can map to Craft native params
        $allowedParams = array_flip(array('w', 'h', 'q', 'fm', 'fit', 'crop', 'fp-x', 'fp-y', 'fill-color'));
        $params = array_intersect_key($params, $allowedParams);

        // Map core params to native params
        $mappedParams = [];
        foreach ($params as $key => $value) {
            switch ($key) {

                case 'w' :
                    $mappedParams['width'] = $value;
                    break;

                case 'h' :
                    $mappedParams['height'] = $value;
                    break;

                case 'q' :
                    $mappedParams['quality'] = $value;
                    break;

                case 'fm' :
                    if (in_array($value, ['auto', 'jpg', 'gif', 'png', 'webp', 'avif'])) {
                        $mappedParams['format'] = $value;
                    }
                    break;

                case 'fill-color' :
                    $mappedParams['fill'] = $value;
                    break;

                case 'fit' :
                    if ($value === 'fill' || $value === 'clip' || $value === 'min' || $value === 'max' || $value === 'fillmax') {
                        $mappedParams['mode'] = 'fit';
                    }
                    elseif ($value === 'crop' || $value === 'facearea') {
                        $mappedParams['mode'] = 'crop';
                    }
                    elseif ($value === 'scale') {
                        $mappedParams['mode'] = 'stretch';
                    }
                    break;

                case 'crop' :
                    if ($value === 'top') {
                        $mappedParams['crop'] = 'Top-Center';
                    } elseif ($value === 'top,left') {
                        $mappedParams['crop'] = 'Top-Left';
                    } elseif ($value === 'top,right') {
                        $mappedParams['crop'] = 'Top-Right';
                    } elseif ($value === 'bottom') {
                        $mappedParams['crop'] = 'Bottom-Center';
                    } elseif ($value === 'bottom,left') {
                        $mappedParams['crop'] = 'Bottom-Left';
                    } elseif ($value === 'bottom,right') {
                        $mappedParams['crop'] = 'Bottom-Right';
                    } elseif ($value === 'left') {
                        $mappedParams['crop'] = 'Center-Left';
                    } elseif ($value === 'right') {
                        $mappedParams['crop'] = 'Center-Right';
                    } else {
                        $mappedParams['crop'] = 'Center-Center';
                    }
                    break;

                default :
                    $mappedParams[$key] = $value;
                    break;
            }
        }

        // letterbox fill color
        if (isset($mappedParams['fill'])) {
            $mappedParams['mode'] = 'letterbox';
        }

        // generate a native transform url
        $transform = new ImageTransform($mappedParams);
        return $asset->getUrl($transform,true);

    }
}