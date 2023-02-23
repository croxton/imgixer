# Imgixer plugin for Craft CMS 4.x

Generate image transformation URLs that work with [Imgix](https://imgix.com), [Imagekit](https://imagekit.io) and [Servd](https://servd.host).

* Generate urls with convenient methods for responsive images.
* Use the same transform parameters with all image providers.
* Speed up your templates and control panel by swapping Craft's native image transforms with Imgix or Imagekit rendering.
* Host with [Servd.host](https://servd.host)? Use Servd’s built-in image transform service.

![Screenshot](resources/img/plugin-logo.png)

## Requirements

This plugin requires Craft CMS 4.0.0 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require croxton/imgixer

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Imgixer.


## Configuring Imgixer

Copy `config.php` into Crafts `config` folder and rename it to `imgixer.php`. 

Define each source with a unique handle. The same Imgix source domain may be referenced more than once, which can be useful if you want to use a different set of default parameters for images in a particular Asset volume, or an arbitrary grouping of images with similar characteristics, or if you have defined your Imgix source as a web proxy and need to reference multiple domains.

```php
<?php
return [
    'sources' => array(
        
        // A unique handle that you can reference in your templates.
        'myHandle' => array(
        
            // Provider: either 'imgix', 'imagekit' or 'servd' (defaults to 'imgix')
            'provider' => 'imgix',

            // The image service endpoint or source domain
            'endpoint'   => 'my-domain.imgix.net',

            // Optionally specify a subfolder path to prefix the path
            // of generated URLs after the source domain.
            'subfolder' => '', 
            
            // The private key used to sign images. 
            // Imgix: get this from the source details screen at https://imgix.com
            // Imagekit: https://imagekit.io/dashboard/developer/api-keys
            // Servd: not required
            'privateKey'   => '12345',
            
            // A public key used to access the image transform API 
            // Imgix: not required 
            // Imagekit: https://imagekit.io/dashboard/developer/api-keys
            // Servd: not required
            'publicKey'   => '12345',
            
            // Set to true if you want images to be signed with your private key.
            'signed'    => true,
            
            // Define any default parameters here:
            'defaultParams' => array(
                'auto' => 'compress,format',
                'fit' => 'crop',
                'ar' => '3:2',
                'step' => '100'
            )
        ),
        'heroBanners' => array(
            'provider' => 'imagekit',
            'endpoint'   => 'https://ik.imagekit.io/render/my-identifier',
            'privateKey'   => '12345',
            'publicKey'   => '67890',
            'signed'    => true,
            'defaultParams' => array(
                'auto' => 'compress,format',
                'fit' => 'crop',
                'ar' => '16:9',
                'q' => '80'
            )
        ),
        'portraits' => array(
            'provider' => 'imgix',
            'endpoint'   => 'another-domain.imgix.net',
            'privateKey'   => '12345',
            'signed'    => true,
            'defaultParams' => array(
                'auto' => 'compress,format,enhance,redeye',
                'fit' => 'facearea',
                'ar' => '3:4'
            )
        ),
        
        // Optional: define a source to use in place of Craft's native image transforms. 
        // This will be used for all transforms used in your templates and in the control panel.
        'assetTransforms' => array(
            'provider' => 'imgix',
            'endpoint' => 'my-domain.imgix.net',
            'privateKey' => '12345',
            'signed' => true
        ),
    ),

    // Optional: set this to the source you want to use in place of Craft's native image transforms. 
    'transformSource' => 'assetTransforms'
];
```

## Using Imgixer

```twig

{% set image = entry.myImage.one() %}

{# Use either as a filter... #}
{{ image | imgix({ ar:'16:9', w:1024 }) }}

{# ...or as a function. #}
{{ set myImageSrc = imgix(image, { ar:'16:9', w:1024 }) }}

{# Optionally, you can pass an image path instead of an asset (this will render the Imgix URL without a date modified parameter (&dm=), meaning the URL won't be automatically updated if you replace the image without renaming it #}
{{ set myImageSrc = imgix(image.path, { ar:'16:9', w:1024 }) }}

{# Create a srcset by defining a range of widths using the `from`, `to` and `step` parameters #}
{{ set myImageSrcset = imgix(image, { ar:'16:9', from:300, to:1600, step:100 }) }}

{# Specify a source handle (by default, Imgixer uses the first source you defined in the config) #}
{{ image | imgix({ ar:'16:9', w:1024, source:'heroBanners' }) }}

{# Example of a responsive <img> #}
<img
  srcset="{{ image | imgix({ ar:'16:9', from:640, to:1600, step:160 }) }}"
  src="{{ image | imgix({ ar:'16:9', w:1024 }) }}"
  alt="">

{# Example of a responsive <picture> where the image proportions change depending on screen width #}
<picture>

    <!-- 21:9 -->
    <source
      media="(min-width: 768px)"
      sizes="100vw"
      srcset="{{ image | imgix({ ar:'21:9', from:800, to:3200, step:160 }) }}">

    <!-- 16:9 -->
    <source
      media="(min-width: 640px)"
      sizes="100vw"
      srcset="{{ image | imgix({ ar:'16:9', from:640, to:1600, step:160 }) }}">

    <!-- 3:2 -->
    <source
      sizes="100vw"
      srcset="{{ image | imgix({ ar:'3:2', from:480, to:1280, step:160 }) }}">

    <!-- older browsers -->
    <img
      src="{{ image | imgix({ ar:'16:9', w:1024 }) }}"
      alt="">

</picture>
```
## Using Imgixer with Servd.host asset sources

There are several ways to use Imgixer with [Servd.host](https://servd.host) asset sources, and benefit from Servd's automatic environment prefixing (generated URLs are prefixed with `local`, `staging` or `production`). 

With either option, you will first need to install [Servd Assets and Helpers](https://github.com/servdhost/craft-asset-storage).

### 1. Using an Imgix Web Folder source
  
* Set up a Web Folder source in Imgix with the base URL set to Servd's CDN URL for your project, e.g. `https://cdn2.assets-servd.host/my-served-project-slug`.

* Recommended: tick the option to use secure URLs and make a note of the key.
  
* Create a source in `imgixer.php` config, adding `servd` as the asset transform provider:

```php
'my-servd-web-folder' => array(
   'provider' => 'servd',
   'endpoint' => 'my-domain.imgix.net',
   'privateKey' => '12345',
   'signed' => true,
   'defaultParams' => array(
       'auto' => 'compress,format',
       'fit' => 'crop',
       'q' => '80'
   )
),
```

### 2. Use Servd's own image transformation service

Servd provides its own image transformation service (https://optimise2.assets-servd.host) that supports a subset of Imgix's Rendering API and covers the majority of use cases. If you are hosting with Servd it may be all you need.

Create a source in `imgixer.php` config, adding `servd` as the asset provider. Do not set a domain:

```php
'my-servd-assets' => array(
   'provider' => 'servd',
   'defaultParams' => array(
       'auto' => 'format',
       'fit' => 'crop',
       'q' => '80'
   )
),
```

## Core parameter set 

**Supported by Imgix, Imagekit and Servd**.

#### fm - output format
Can be one of: webp, png, jpeg | jpg, tiff.

#### w - width
Scales image to supplied width while maintaining aspect ratio.

#### h - height
Scales image to supplied height while maintaining aspect ratio.

#### q - quality
(default 75) - 1-100 - Controls the output quality of lossy file formats.

#### ar - aspect-ratio
(1.0:1.0) - When fit=crop, an aspect ratio such as 16:9 can be supplied, optionally with a height or width. If neither height or width are defined, the original image size will be used.

#### dpr - device-pixel-ratio
(1) - scales requested image dimensions by this multiplier.

#### fit - resize fitting mode
Can be one of: fill, scale, crop, clip, min, max.

#### fill-color
Used when fit is set to fill can be a loosely formatted color such as "red" or "rgb(255,0,0)".

#### crop - resize fitting mode
Can be one of: focalpoint, entropy, any comma separated combination of top, bottom, left right.

##### crop=focalpoint
Uses the fp-x and fp-y parameters to crop as close to the supplied point as possible.

##### crop=entropy
Crops the image around the region with the highest Shannon entropy.

##### crop=top,left (or bottom, right)
Crops the image around the region specified. Supply up to two region identifiers comma separated.

#### fp-x, fp-y - focal point x & y
Percentage, 0 to 1 for where to focus on the image when cropping with focalpoint mode.

#### auto
Can be a comma separated combination of: compress, format.

##### auto=format
If auto includes format, the service will try to determine the ideal format to convert the image to. The rules are:

* If the browser supports it, everything except for gifs is returned as webp.
* If a png is requested and that png has no alpha channel, it will be returned as a jpeg.

##### auto=compress
The compress parameter will try to run post-processed optimizations on the image prior to returning it.

## Extended parameter set

**Supported by Imgix and Imagekit only**. There may be some variation in image output as the behaviour of parameters does not always directly correlate between services.

#### blur
Applies a Gaussian style blur to your image, smoothing out image noise.

Valid values are in the range from 0 to 2000. The default value is 0, which leaves the image unchanged.

#### border
This adds a border to the image. It accepts two parameters - the width of the border and the color of the border: `border=<border-width>,<hex code>`

#### con - contrast
(0) Adjusts the contrast of the image. Valid values are in the range -100 – 100. The default value is 0, which leaves the image unchanged.

Note:
* Imagekit: any value over 0 applies automatic contrast adjustment.

#### fit=facearea
Finds the area containing all faces, or a specific face in an image, and scales it to specified width and height dimensions. Great for thumbnail portraits.

Notes:
* When using Imgix, add `facepad=1.6` to approximate the default face padding provided by Imagekit (face padding is not configurable in Imagekit).
* Imgix will not apply the aspect-ratio (`ar`) parameter when `fit=facearea`, therefore the width and height parameters should always be supplied when using this parameter.

#### fit=fillmax
Resizes the image to fit within the requested width and height dimensions while preserving the original aspect ratio and without discarding any original image data. If the requested width or height exceeds that of the original, the original image remains the same size. Use the `fill-color` parameter to specify the background colour to use.

Note:
* Imgix will not apply the aspect-ratio (`ar`) parameter when `fit=fillmax`, therefore the width and height parameters should always be supplied when using this parameter.

#### fp-z - focal point zoom
Must be a float between 1 and 100, inclusive. The default value is 1, representing the original size of the image, and every full step is the equivalent of a 100% zoom (e.g. `fp-z=2` is the same as viewing the image at 200%). The suggested range is 1 – 10. For the focal point to be set on an image, `fit=crop` and `crop=focalpoint` must also be set.

#### lossless
The lossless parameter enables delivery of lossless images in formats that support lossless compression (JPEG XR and WEBP). Valid values are `true` and `false`.

#### radius
Used to specify the image corner radius in pixels. The background of rounded images will be transparent.

Note:
* Imagekit: if you have specified a border, it will not be rounded.

#### rot
Rotates the image by degrees according to the value specified. Valid values are in the range 0 – 359, and rotation is counter-clockwise with 0 (the default) at the top of the image.

#### sat=-100
Outputs a fully desaturated grayscale image.

#### sharp - sharpen
Sharpens the image using luminance (which only affects the black and white values), providing crisp detail with minimal color artifacts.

Recommended values are in the range 0 – 100. The default value is 0, which leaves the image unchanged.

#### trim
Trims the image to remove a uniform border around the image.

##### trim=auto
The image is trimmed automatically based on the border color.

##### trim=color
Imgix only: the image will trim away the hex color specified by the `trim-color` parameter.

#### trim-tol
The trim tolerance defines how different a color can be before the trim operation stops. 
Imgix requires `trim=color` to be set for this parameter to be applied.

## Service-specific parameter sets
If you wish to be able to switch easily between service providers, try to stick with the core or extended set of parameters listed above. However, the full sets of available parameters for each of the supported image transform services can be found here:

* **Imgix**: https://docs.imgix.com/apis/rendering
* **Imagekit**: https://docs.imagekit.io/features/image-transformations/resize-crop-and-other-transformations
* **Servd**: only supports the core parameter set described above


## Element API

Pass the image as the first argument to the function, and an array of Imgix parameters as the second argument.

```php
<?php

use craft\elements\Entry;
use craft\helpers\UrlHelper;
use croxton\imgixer\twigextensions\ImgixerTwigExtension;

return [
    'endpoints' => [
        'news.json' => function() {
            return [
                'elementType' => Entry::class,
                'criteria' => ['section' => 'news'],
                'transformer' => function(Entry $entry) {
                    $asset = $entry->image->one();
                    return [
                        'title' => $entry->title,
                        'url' => $entry->url,
                        'jsonUrl' => UrlHelper::url("news/{$entry->id}.json"),
                        'summary' => $entry->summary,
                        'image'   => (new ImgixerTwigExtension)->imgix($asset, array(
                            'ar' => '16:9',
                            'w' => 2000,
                            'signed' => true
                        ))
                    ];
                },
            ];
        }
    ]
];
```

## Web proxy

When using Imgix or Imagekit as a proxy you need to provide the absolute URL to the image you want to proxy. You can do that at the template level (if you need to proxy multiple domains), or create a `source` for each proxied domain in your config and pass the proxy domain to the `subfolder`.

So for example you can have a config like this...

```php
return [
   'my-proxy' => array(
      'provider' => 'imgix',
      'endpoint' => 'my-proxy-source.imgix.net',
      'subfolder' => 'https://www.my-proxied-website.com/',
      'privateKey' => 'XXXXXXXXXXXX',
      'defaultParams' => array(
         'auto' => 'compress,format',
         'fit' => 'crop',
         'q' => '80'
      )
   ),
];
```

...and use like this in templates:

```twig
{{'uploads/my-image.jpg' | imgix({ source:'my-proxy', ar:'3:2', w:1200, signed: true }) }}
```

Alternatively, use a config like this...

```php
return [
   'my-proxy' => array(
            'provider' => 'imgix',
            'endpoint' => 'my-proxy-source.imgix.net',
            'privateKey' => 'XXXXXXXXXXXX',
            'defaultParams' => array(
                'auto' => 'compress,format',
                'fit' => 'crop',
                'q' => '80'
            )
        ),
   )
];
```

...and use like this in templates:

```twig
{{'https://www.my-proxied-website.com/uploads/my-image.jpg' | imgix({ source:'my-proxy', ar:'3:2', w:1200, signed: true }) }}
{{'https://www.another-proxied-website.com/uploads/another-image.jpg' | imgix({ source:'my-proxy', ar:'3:2', w:1200, signed: true }) }}

```

### Asset purging
When an image is edited in the control panel using Craft's image editor, Imgixer does NOT automatically issue an API request to the image provider to purge the corresponding URL from it's CDN. From the [Imgix docs](https://docs.imgix.com/setup/purging-assets):

> To absolutely ensure compliance for an updated asset, it is best to give the asset a new path by renaming the file. Purging an asset is only recommended when absolutely necessary.

Please ask your editors to always 'Save as a new asset' when editing an image in the control panel. If the image editor was invoked from an entry, the entry asset field will be automatically updated to link to the new asset.
   


