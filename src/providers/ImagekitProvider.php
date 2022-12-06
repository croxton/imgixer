<?php

namespace croxton\imgixer\providers;

use Craft;
use craft\elements\Asset;
use croxton\imgixer\Imgixer;
use croxton\imgixer\AbstractProvider;
use Imgix\UrlBuilder;
use ImageKit\ImageKit;

class ImagekitProvider extends AbstractProvider
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

        // Unless setup with a custom domain, Imagekit source urls take the form https://ik.imagekit.io/render/[source]
        if ( ! isset($source['endpoint'])) {
            $source['endpoint'] = 'https://ik.imagekit.io/render/' . $source['handle'];
        }

        // Keys
        if ( ! isset($source['public_key'], $source['private_key'])) {
            throw new \InvalidArgumentException('The `' .$source . '` keys are not defined in your config.');
        }

        // Image path
        $img = $asset;
        if ( ! is_string($asset) && $asset instanceof Asset) {
            $img = $asset->path;
            // when an image has been modified, ensure a new version is generated
            $params['dm'] = $asset->dateModified->getTimestamp();
        }

        // Prefix img path with subfolder, if defined
        if ( isset($source['subfolder'])) {
            $img = $source['subfolder'] .'/'. $img;
        } elseif ( ! is_string($asset) && $asset instanceof Asset) {
            // Get the filesystem
            $fs = $asset->getVolume()->getFs();
            $img = ltrim(trim($fs->subfolder, '/') . '/' . $img, '/');

            // Do we have a Servd filesystem?
            if (get_class($fs) === 'servd\AssetStorage\AssetsPlatform\Fs') {
                // remove the project slug prefix
                $img = explode('/', $img);
                array_shift($img);
                $img = implode('/', $img);
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

        // Map the formatting of our standard set of parameters to Imagekit
        $transformsPre = [];
        $transforms = [];

        // Focalpoint - we need to resize the image first
        if (isset($params['crop']) && $params['crop'] === 'focalpoint') {
            if (isset($params['w'])) {
                $resizeWidth = $params['w'];
                if (isset($params['fp-z'])) {
                    $resizeWidth = $params['w'] * $params['fp-z'];
                }
                $transformsPre['w'] = $resizeWidth;
            } elseif (isset($params['h'])) {
                $resizeHeight = $params['h'];
                if (isset($params['fp-z'])) {
                    $resizeHeight = $params['h'] * $params['fp-z'];
                }
                $transformsPre['h'] = $resizeHeight;
            }
        }

        ksort($params);
        foreach ($params as $key => $value) {
            switch($key) {
                case 'auto' :
                    $autoValues = array_map('trim', explode(',', $value));
                    foreach ($autoValues as $auto) {
                        if ($auto === 'format') {
                            $transforms['f'] = 'auto';
                        }
                        if ($auto === 'enhance') {
                            // enhance contrast
                            $transforms['e-contrast'] = null;
                            // unsharp mask
                            $transforms['e-usm'] = '2-2-0.8-0.024';
                        }
                    }
                    break;
                case 'ar' :
                    $transforms[$key] = str_replace(':', '-', $value);
                    break;

                case 'crop' :
                    if (str_contains($value, "faces")) {
                      $transforms['fo'] = 'face';
                    } elseif (str_contains($value, "entropy")) {
                      $transforms['fo'] = 'entropy';
                    } elseif (str_contains($value, "focalpoint")) {
                      $transforms['cm-extract'] = null;
                    } else {
                      // top, left, bottom etc
                      $transforms['fo'] = str_replace([',', ' '], ['_', ''], $value);
                    }
                    break;

                case "fill-color" :
                    $transforms['bg'] = $value;
                    break;

                case 'fit' :
                    if ($value === 'scale') {
                      $transforms['c-force'] = null;
                    }
                    if ($value === 'crop') {
                      $transforms['c-maintain_ratio'] = null;
                    }
                    if ($value === 'fill') {
                      $transforms['cm-pad_resize'] = null;
                    }
                    if ($value === 'clip') {
                      $transforms['c-at_max'] = null;
                    }
                    if ($value === 'max') {
                      $transforms['c-at_max'] = null;
                    }
                    if ($value === 'fillmax') {
                        if ($params['w'] && $params['w'] > $asset->width || $params['h'] && $params['h'] > $asset->height) {
                            $transforms['cm-pad_extract'] = null;
                        } else {
                            $transforms['cm-pad_resize'] = null;
                        }
                    }
                    if ($value === 'min') {
                      $transforms['c-at_min'] = null;
                    }
                    if ($value === 'facearea') {
                      $transforms['c-maintain_ratio'] = null;
                      $transforms['fo'] = 'face';
                    }
                    break;

                case 'fp-x' :
                    // calculate pixel x position relative to image width
                    $zoom = 1;
                    if (isset($params['fp-z'])) {
                        $zoom = $params['fp-z'];
                    }
                    $imageWidth = $params['w'] * $zoom;
                    $xc = (int) ($value * $imageWidth);

                    // ImageKit will return original image if the requested extract exceeds max width of image
                    if ($xc + $params['w'] / 2 > $imageWidth) {
                        $xc = (int) ($imageWidth - $params['w'] / 2);
                    }
                    $transforms['xc'] = $xc;
                    break;

                case 'fp-y' :
                    // calculate pixel y position relative to image height
                    $zoom = 1;
                    if (isset($params['fp-z'])) {
                        $zoom = $params['fp-z'];
                    }
                    $imageHeight = $params['w'] * ($asset->height / $asset->width) * $zoom;
                    $yc = (int) ($value * $imageHeight);

                    // ImageKit will return original image if the requested extract exceeds max height of image
                    if ($yc + $params['h'] / 2 > $imageHeight) {
                        $yc = (int) ($imageHeight - $params['h'] / 2);
                    }

                    $transforms['yc'] = $yc;
                    break;

                default :
                    $transforms[$key] = $value;
          }
        }

        // @TODO: aspect ratio - if set, remove height OR width parameter otherwise it overrides the value

        // Build an Imagekit URL
        $imageKit = new ImageKit(
            $source['public_key'],
            $source['private_key'],
            $source['endpoint']
        );

        return $imageKit->url([
            'path' => '/' . $img,
            'transformation' => array($transformsPre, $transforms),
            'transformationPosition' => 'query',
            'seoFriendly' => true,
            'signed' => $signed
        ]);
    }
}