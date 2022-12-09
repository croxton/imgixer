<?php
/**
 * Imgixer plugin for Craft CMS 3.x
 *
 * Generate Imgix URLs for image transforms.
 *
 * @link      https://hallmark-design.co.uk
 * @copyright Copyright (c) 2019 Mark Croxton
 */

namespace croxton\imgixer\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\errors\AssetTransformException;
use craft\helpers\Assets;
use craft\helpers\UrlHelper;
use craft\helpers\ArrayHelper;
use craft\helpers\Image;
use craft\models\ImageTransform;
use craft\events\GetAssetThumbUrlEvent;

use croxton\imgixer\models\SettingsModel;
use croxton\imgixer\twigextensions\ImgixerTwigExtension;

class UrlService extends Component
{
    public static $transformKeyMap = [
        'width'   => 'w',
        'height'  => 'h',
        'quality' => 'q',
        'format'  => 'fm',
    ];

    // Public Methods
    // =========================================================================

    /**
     * @param Asset $asset
     * @param ImageTransform|string|array|null $transform
     *
     * @return string|null
     * @throws AssetTransformException
     */
    public function getUrl(Asset $asset, $transform)
    {
        $assetExt = $asset->getExtension();

        if (empty($transform)) {
            $transform = new ImageTransform([
                'height' => $asset->height,
                'width' => $asset->width,
                'interlace' => 'line',
            ]);
        }

        // Look up asset transform handle
        if (is_string($transform)) {
            $assetTransforms = Craft::$app->getAssetTransforms();
            $transform = $assetTransforms->getTransformByHandle($transform);
        }

        // If array, convert to an AssetTransform model
        if (is_array($transform)) {
            $transform = new AssetTransform($transform);
        }

        // If image is a SVG, bail out
        $format = empty($transform['format']) ? $assetExt : $transform['format'];
        if ($format === 'svg') {
            return null;
        }

        // Build Imgix url
        return $this->getTransformUrl($asset, $transform);
    }

    /**
     * @param \craft\events\DefineAssetThumbUrlEvent $event
     *
     * @return string|null
     */
    public function getThumbUrl(\craft\events\DefineAssetThumbUrlEvent $event)
    {
        $url = $event->url;
        $asset = $event->asset;
        $assetExt = $asset->getExtension();

        if (Image::canManipulateAsImage($assetExt)) {
            $transform = new ImageTransform([
                'width' => $event->width,
                'height' => $event->height,
                'interlace' => 'line',
            ]);

            // If image is a SVG, bail out
            $format = empty($transform['format']) ? $assetExt : $transform['format'];
            if ($format === 'svg') {
                return null;
            }

            // Build Imgix url
            $url = $this->getTransformUrl($asset, $transform);
        }

        return $url;
    }

    /**
     * @param Asset $asset
     * @param AssetTransform|null $transform
     * @see https://craftcms.com/docs/3.x/image-transforms.html
     *
     * @return string|null
     */
    public function getTransformUrl(Asset $asset, $transform)
    {
        $url = null;
        $params = [];

        $transformSource = \croxton\imgixer\Imgixer::getInstance()->settings->transformSource;

        if ($asset && $transformSource) {

            $params['source'] = $transformSource;

            if ($transform) {

                // Map transform properties
                foreach (self::$transformKeyMap as $key => $value) {
                    if ( ! empty($transform[$key])) {
                        $params[$value] = $transform[$key];
                    }
                }

                // Imgix 'auto' compression/format settings
                $auto = [];
                if (empty($params['q'])) {
                    $auto[] = 'compress';
                }
                if (empty($params['fm'])) {
                    $auto[] = 'format';
                }
                if ( ! empty($auto)) {
                    $params['auto'] = implode(',', $auto);
                }

                // Interlaced images
                if (property_exists($transform, 'interlace')) {
                    if (($transform->interlace != 'none')
                        && (!empty($params['fm']))
                        && ($params['fm'] == 'jpg')
                    ){
                        $params['fm'] = 'pjpg';
                    }
                }

                // Fit mode
                switch ($transform->mode) {

                    case 'fit':
                        $params['fit'] = 'clip';
                        break;

                    case 'stretch':
                        $params['fit'] = 'scale';
                        break;

                    default:
                        $params['fit'] = 'crop';
                        break;
                }

                // Position (if we're cropping)
                if ($params['fit'] === 'crop') {

                    // Default position
                    if (empty($transform->position)) {
                        $transform->position = 'center-center';
                    }

                    $crop = [];

                    // Focal point
                    $fp = $asset->getFocalPoint();

                    if ( ! empty($fp)) {
                        $params['fp-x'] = $fp['x'];
                        $params['fp-y'] = $fp['y'];
                        $crop[] = 'focalpoint';
                        $params['crop'] = implode(',', $crop);
                    } elseif (preg_match('/(top|center|bottom)-(left|center|right)/', $transform->position)) {

                        // Imgix defaults to 'center'
                        $filteredCrop = explode('-', $transform->position);
                        $filteredCrop = array_diff($filteredCrop, ['center']);
                        $crop[] = $filteredCrop;

                        if ( ! empty($crop) && $transform->position !== 'center-center') {
                            $params['crop'] = implode(',', $crop);
                        }
                    }
                }

                // If allowUpscale is disabled use max-w and max-h instead
                if ( Craft::$app->config->general->upscaleImages === false) {
                    if ($params['fit'] === 'crop') {
                        $params['fit'] = 'min';
                    }
                    if ($params['fit'] === 'clip') {
                        $params['fit'] = 'max';
                    }
                }

            } else {
                // No transform, set default auto values
                $params['auto'] = 'format,compress';
            }

            // Build the URL for the image using the specified image service
            $url = (new ImgixerTwigExtension)->imgix($asset, $params);
        }

        return $url;
    }
}