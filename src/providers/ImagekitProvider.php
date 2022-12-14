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
        if ( ! isset($source['domain']) && ! isset($source['endpoint'])) {
            $source['endpoint'] = 'https://ik.imagekit.io/render/' . $source['handle'];
        } elseif (isset($source['domain'])) {
            $source['endpoint'] = $source['domain'];
        }

        // Keys
        if ( ! isset($source['privateKey']) && isset($source['key'])) {
            $source['privateKey'] = $source['key'];
        }
        if ( ! isset($source['publicKey'], $source['privateKey'])) {
            throw new \InvalidArgumentException('The `' .$source['handle'] . '` keys are not defined in your config.');
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
            if (isset($fs->subfolder) && ! empty($fs->subfolder)) {
                $img = ltrim(trim($fs->subfolder, '/') . '/' . $img, '/');
            }

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
        $transformsPost = [];

        ksort($params);
        foreach ($params as $key => $value) {
            switch($key) {

                case 'ar' :
                    $transforms[$key] = str_replace(':', '-', $value);
                    break;

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

                case 'blur' :
                    // Imgix: 0-2000, Imagekit 0-100
                    $transforms['bl'] = (int)($value / 10);
                    if ($transforms['bl'] > 100) {
                        $transforms['bl'] = 100;
                    }
                    break;

                case 'border' :
                    $transformsPost['b'] = str_replace([',', ' '], ['_', ''], $value);
                    break;

                case 'con' :
                    if ($value > 0) {
                        $transforms['e-contrast'] = null;
                    }
                    break;

                case 'crop' :
                    if (str_contains($value, "faces")) {
                        $transforms['fo'] = 'face';
                    } elseif (str_contains($value, "entropy")) {
                        $transforms['fo'] = 'entropy';
                    } elseif (str_contains($value, "focalpoint")) {
                        // we need to resize the image first...
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
                        // ...then extract on second chained transform
                        $transforms['cm-extract'] = null;
                    } else {
                      // top, left, bottom etc
                      $transforms['fo'] = str_replace([',', ' '], ['_', ''], $value);
                    }
                    break;

                case 'cs' :
                    // typically the user wants to use the original image's
                    // color profile if this value is specified
                    if ($value === 'adobergb1998') {
                        $transforms['cp'] = true;
                    }
                    break;

                case "fill-color" :
                    $transforms['bg'] = $value;
                    break;

                case 'fit' :

                    if ($value === 'clip') {
                        $transforms['c-at_max'] = null;
                    }

                    if ($value === 'crop') {
                      $transforms['c-maintain_ratio'] = null;
                    }

                    if ($value === 'facearea') {
                        $transforms['c-maintain_ratio'] = null;
                        $transforms['fo'] = 'face';
                    }

                    if ($value === 'fill') {
                      $transforms['cm-pad_resize'] = null;
                    }

                    if ($value === 'fillmax') {
                        if ((isset($params['w']) && $params['w'] > $asset->width) || (isset($params['h']) && $params['h'] > $asset->height)) {
                            $transforms['cm-pad_extract'] = null;
                        } else {
                            $transforms['cm-pad_resize'] = null;
                        }
                    }

                    if ($value === 'max') {
                      $transforms['c-at_max'] = null;
                    }
                    if ($value === 'min') {
                      $transforms['c-at_min'] = null;
                    }

                    if ($value === 'scale') {
                        $transforms['c-force'] = null;
                    }

                    break;

                case 'fm' :
                    if ( ! ( isset($transforms['f']) && $transforms['f'] === 'auto') ) {
                        $imagekitFormats = ['jpg', 'jpeg', 'webp', 'avif', 'png'];
                        if (in_array($value, $imagekitFormats, true)) {
                            $transforms['f'] = $value;
                        } else {
                            $transforms['f'] = 'auto';
                        }
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

                case 'lossless' :
                    $transforms['lo'] = $value;
                    break;

                case 'radius' :
                    $transforms['r'] = $value;
                    break;

                case 'rot' :
                    $transforms['rt'] = $value;
                    break;

                case 'sat' :
                    // grayscale support (approximates Imgix desaturation)
                    if ($value === -100) {
                        $transforms['e-grayscale'] = null;
                    }
                    break;

                case "sharp" :
                    $transforms['e-sharpen'] = $value;
                    break;

                case 'trim' :
                    if (str_contains($value, 'auto'))   {
                        $transforms['t'] = true;
                    }
                    break;

                case 'trim-tol' :
                    $transforms['t'] = $value;
                    break;

                default :
                    $transforms[$key] = str_replace([',', ' '], ['_', ''], $value);
                    break;
          }
        }

        // aspect ratio - if set, remove height OR width parameter otherwise it
        // overrides the aspect ratio, which is a different behaviour to Imgix
        if (isset($transforms['ar'], $transforms['w'], $transforms['h'])) {
            // remove whichever is the smaller dimension
            if ($transforms['w'] > $transforms['h']) {
                unset($transforms['h']);
            } else {
                unset($transforms['w']);
            }
        }

        // Build an Imagekit URL
        $imageKit = new ImageKit(
            $source['publicKey'],
            $source['privateKey'],
            $source['endpoint']
        );

        return $imageKit->url([
            'path' => '/' . $img,
            'transformation' => array($transformsPre, $transforms, $transformsPost),
            'transformationPosition' => 'query',
            'seoFriendly' => true,
            'signed' => $signed
        ]);
    }
}