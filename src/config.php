<?php
/**
 * Imgixer plugin for Craft CMS 3.x
 *
 * Generate Imgix URLs.
 *
 * @link      https://hallmark-design.co.uk
 * @copyright Copyright (c) 2019 Mark Croxton
 */

/**
 * Imgixer config.php
 *
 * This file exists only as a template for the Imgix settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'imgixer.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */
return [
    'sources' => array(

        // A unique handle that you can reference in your templates.
        'myHandle' => array(

            // The imgix source domain.
            'domain'   => '',

            // Optionally specify a subfolder path to prefix generated URLs.
            'subfolder' => '',

            // The private Imgix key used to sign images.
            // Get this from the source details screen in Imgix.com
            'key'   => '',

            // Define any default parameters here:
            'defaultParams' => []
        ),

    )
];