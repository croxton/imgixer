<?php
/**
 * Imgixer plugin for Craft CMS 3.x
 *
 * Generate Imgix URLs.
 *
 * @link      https://hallmark-design.co.uk
 * @copyright Copyright (c) 2019 Mark Croxton
 */

namespace croxton\imgixer\twigextensions;

use croxton\imgixer\Imgixer;
use croxton\imgixer\Plugin;
use Imgix\UrlBuilder;
use yii\base\InvalidArgumentException;

use Craft;

/**
 * Twig can be extended in many ways; you can add extra tags, filters, tests, operators,
 * global variables, and functions. You can even extend the parser itself with
 * node visitors.
 *
 * http://twig.sensiolabs.org/doc/advanced.html
 *
 * @author    Mark Croxton
 * @package   Imgixer
 * @since     1.0.0
 */
class ImgixerTwigExtension extends \Twig_Extension
{

    /**
     * An array of source URLs and signature keys
     *
     * @var        array
     */
    private $sources;

    /**
     * The default source to use
     *
     * @var        string
     */
    private $default_source;

    /**
     * Constructor
     *
     * @access public
     */
    public function __construct()
    {
        // get from config
        $this->sources = \croxton\imgixer\Imgixer::getInstance()->settings->sources;
        $this->default_source = key($this->sources); // default to the first source defined
    }


    // Public Methods
    // =========================================================================

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'Imgixer';
    }

    /**
     * Returns an array of Twig filters, used in Twig templates via:
     *
     *      {{ 'something' | someFilter }}
     *
     * @return array
     */
    public function getFilters()
    {
        return [
            new \Twig\TwigFilter('imgix', [$this, 'imgix']),
        ];
    }

    /**
     * Add/remove the value of query string parameter that accepts a single value
     *
     * @access public
     * @param string $img The asset URL
     * @param array $params An array of Imgix parameters
     * @return string
     * @throws \InvalidArgumentException
     */
    public function buildUrl($img, $params=array())
    {
        $signed  = isset($params['signed']) ? (bool) $params['signed'] : false;
        $source   = isset($params['source']) ? (string) $params['source'] : $this->default_source;

        // handle snafus
        if ( ! isset($this->sources[$source])) {
            throw new \InvalidArgumentException('The `' .$source . '` Imgix source is not defined in your config.');
        }

        // unless setup with a custom domain, imgix source urls take the form [source].imgix.net
        if ( ! isset($this->sources[$source]['url'])) {
            $this->sources[$source]['url'] = $source . '.imgix.net';
        }

        // prefix img path with subfolder, if defined
        if ( isset($this->sources[$source]['subfolder'])) {
            $img = $this->sources[$source]['subfolder'] .'/'. $img;
        }

        // cleanup params
        unset($params['signed'], $params['source']);

        // merge any default params
        if ( isset($this->sources[$source]['defaultParams'])) {
            $params = array_merge($this->sources[$source]['defaultParams'], $params);
        }

        // build image URL
        $builder = new UrlBuilder($this->sources[$source]['url']);
        $builder->setUseHttps(true);

        if ($signed && isset($this->sources[$source]['key']) && ! empty($this->sources[$source]['key']))
        {
            $builder->setSignKey($this->sources[$source]['key']);
        }

        return $builder->createURL($img, $params);
    }

    /**
     * Add/remove the value of query string parameter that accepts a single value
     *
     * @access public
     * @param string $img The asset URL
     * @param array $params An array of Imgix parameters
     * @return string
     * @throws \InvalidArgumentException
     */
    public function imgix($img, $params=array())
    {
        $srcset = [];
        $from  = isset($params['from']) ? (int) $params['from'] : false;
        $to  = isset($params['to']) ? (int) $params['to'] : false;
        $step  = isset($params['step']) ? (int) $params['step'] : 100;

        unset($params['from'], $params['to'], $params['step']);

        if ($from && $to) {
            foreach (range($from, $to, $step) as $number) {
                $params['w'] = $number;
                if ($src = $this->buildUrl($img, $params)) {
                    $srcset[] =  $src . ' ' .$number . 'w';
                }
            }
        } else {
            $srcset[] = $this->buildUrl($img, $params);
        }

        return implode(',', $srcset);
    }

    /**
     * Returns an array of Twig functions, used in Twig templates via:
     *
     *      {% set srcSet = imgix(image.path, { ar:'1:1', from:400, to:1000 }) %}
     *
    * @return array
     */
    public function getFunctions()
    {
        return [
            new \Twig\TwigFunction('imgix', [$this, 'imgix']),
        ];
    }
}
