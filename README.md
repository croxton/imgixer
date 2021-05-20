# Imgixer plugin for Craft CMS 3.x

The most flexible [Imgix](https://imgix.com/) URL generator for Craft CMS.

* Generate Imgix URLs with convenient methods for responsive images.
* *New*: Speed up your templates and control panel by swapping Craft's native image transforms with Imgix rendering.
* *New*: [Servd.host](https://servd.host) users - use Servd's built-in image transforms instead of Imgix.

![Screenshot](resources/img/plugin-logo.png)

## Requirements

This plugin requires Craft CMS 3.0.0-beta.23 or later.

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

             // The imgix source domain.
            'domain'   => 'my-domain.imgix.net',
            
            // The asset provider (defaults to 'imgix')
            'provider'   => '',

            // Optionally specify a subfolder path to prefix the path
            // of generated URLs after the source domain.
            'subfolder' => '', 
            
            // The private Imgix key used to sign images. 
            // Get this from the source details screen in Imgix.com
            'key'   => '12345',
            
            // Set to true if you want images to be signed with your key.
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
            'domain'   => 'another-domain.imgix.net',
            'key'   => '12345',
            'signed'    => true,
            'defaultParams' => array(
                'auto' => 'compress,format',
                'fit' => 'crop',
                'ar' => '16:9',
                'q' => '80'
            )
        ),
        'portraits' => array(
            'domain'   => 'another-domain.imgix.net',
            'key'   => '12345',
            'signed'    => true,
            'defaultParams' => array(
                'auto' => 'compress,format,enhance,redeye',
                'fit' => 'facearea',
                'ar' => '3:4'
            )
        ),
        
        // Optional: define a source to use in place of Craft's native image transforms. This will be used for all transforms used in your templates and in the control panel.
        'assetTransforms' => array(
            'domain'    => 'my-domain.imgix.net',
            'key'       => '12345',
            'signed'    => true
        ),
        
        // Optional: set this to the source you want to use for image transforms 
        'transformSource' => 'assetTransforms'
        
    )
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
   'domain' => 'my-domain.imgix.net',
   'provider' => 'servd',
   'key' => '12345',
   'signed' => true,
   'defaultParams' => array(
       'auto' => 'compress,format',
       'fit' => 'crop',
       'q' => '80'
   )
),
```

### 2. Use Servd's own image transformation service

Servd provides its own image transformation service that supports a subset of Imgix's Rendering API, that nonetheless covers most use cases. This does NOT require an Imgix account, but note that it does consume the Servd resources allocated to your plan.

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

Servd's image transformation service supports the following parameters:

#### fm - output format
Can be one of: webp, png, jpeg, tiff.

#### w - width
Scales image to supplied width while maintaining aspect ratio.

#### h - height
Scales image to supplied height while maintaining aspect ratio.

#### q - quality
(75) - 1-100

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

Png images will run through pngquant.


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

When using Imgix as a proxy you need to provide the absolute URL to the image you want to proxy. You can do that at the template level (if you need to proxy multiple domains), or create a `source` for each proxied domain in your config and pass the proxy domain to the `subfolder`.

So for example you can have a config like this...

```php
return [
   'my-proxy' => array(
      'domain'   => 'my-proxy-source.imgix.net',
      'subfolder' => 'https://www.my-proxied-website.com/',
      'key'   => 'XXXXXXXXXXXX',
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
            'domain'   => 'my-proxy-source.imgix.net',
            'key'   => 'XXXXXXXXXXXX',
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


