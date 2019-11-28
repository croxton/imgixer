# Imgixer plugin for Craft CMS 3.x

Generate Imgix URLs.

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

Define each source with a unique handle as follows. Note that the same Imgix source domain may be referenced more than once, which can be useful if you want to use a different set of default parameters for images in a particular Asset volume, or an arbitrary grouping of images with similar characteristics, or if you have defined your Imgix source as a web proxy and need to reference multiple domains.

```php
<?php
return [
    'sources' => array(
        
        // A unique handle that you can reference in your templates.
        'myHandle' => array(

             // The imgix source domain.
            'domain'   => 'my-domain.imgix.net',

            // Optionally specify a subfolder path to prefix generated URLs.
            'subfolder' => '', 
            
            // The private Imgix key used to sign images. 
            // Get this from the source details screen in Imgix.com
            'key'   => '12345',
            
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
            'subfolder' => 'hero',
            'key'   => '12345',
            'defaultParams' => array(
                'auto' => 'compress,format',
                'fit' => 'crop',
                'ar' => '16:9',
                'q' => '80'
            )
        ),
        'portraits' => array(
            'domain'   => 'another-domain.imgix.net',
            'subfolder' => 'portraits',
            'key'   => '12345',
            'defaultParams' => array(
                'auto' => 'compress,format,enhance,redeye',
                'fit' => 'facearea',
                'ar' => '3:4'
            )
        ),
    )
];
```

## Using Imgixer

```twig

{% set image = entry.myImage.one() %}

{# Use either as a filter... #}
{{ image.path | imgix({ ar:'16:9', w:1024 }) }}

{# ...or as a function. #}
{{ set myImageUrl = imgix(image.path, { ar:'16:9', w:1024 }) }}

{# Create a srcset by defining a range of widths using the `from`, `to` and `step` paramters #}
{{ set myImageUrl = imgix(image.path, { ar:'16:9', from: 300, to:1600, step:100 }) }}

{# Creating signed images #}
{{ image.path | imgix({ ar:'16:9', w:1024, signed: true }) }}

{# Specify a source handle (by default, Imgixer uses the first source you defined in the config) #}
{{ image.path | imgix({ ar:'16:9', w:1024, source: 'heroBanners' }) }}

{# Example of a responsive <img> #}
<img
  srcset="{{ image.path | imgix({ ar:'16:9', from:640, to:1536, step:160 }) }}">
  src="{{ image.path | imgix({ ar:'16:9', w:1024 }) }}"
  alt="">

{# Example of a responsive <picture> where the image proportions change depending on screen width #}
<picture>

    <!-- 21:9 -->
    <source
      media="(min-width: 768px)"
      sizes="100vw"
      srcset="{{ image.path | imgix({ ar:'21:9', from: 768, to:3168, step:160 }) }}">

    <!-- 16:9 -->
    <source
      media="(min-width: 640px)"
      sizes="100vw"
      srcset="{{ image.path | imgix({ ar:'16:9', from:640, to:1536, step:160 }) }}">

    <!-- 3:2 -->
    <source
      sizes="100vw"
      srcset="{{ image.path | imgix({ ar:'3:2', from:480, to:1280, step:160 }) }}">

    <!-- older browsers -->
    <img
      src="{{ image.path | imgix({ ar:'16:9', w:1024 }) }}"
      alt="">

</picture>
```
