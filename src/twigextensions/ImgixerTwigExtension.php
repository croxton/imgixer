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

use Craft;
use craft\elements\Asset;
use croxton\imgixer\Imgixer;
use Imgix\UrlBuilder;
use yii\base\InvalidArgumentException;
use Twig\Extension\AbstractExtension;


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
class ImgixerTwigExtension extends AbstractExtension
{

    /**
     * An array of Imgix sources
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
     * The default provider to use
     *
     * @var        string
     */
    private $default_provider;

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
        $this->default_provider = 'imgix'; // the default image transformation service provider
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
     *      {{ image.path | imgix({ ar:'16:9', w:1024 }) }}
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
     * Returns an array of Twig functions, used in Twig templates via:
     *
     *      {% set srcset = imgix(image.path, { ar:'1:1', from:400, to:1000 }) %}
     *
     * @return array
     */
    public function getFunctions()
    {
        return [
            new \Twig\TwigFunction('imgix', [$this, 'imgix']),
        ];
    }

    /**
     * Generate src and srcset values, optionally for a range of image sizes
     *
     * @access public
     * @param string $img The asset URL
     * @param array $params An array of Imgix parameters
     * @return string
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
     * Build an image transform URL
     *
     * @access protected
     * @param string|Asset $asset The asset URL
     * @param array $params An array of Imgix parameters
     * @return string|null
     * @throws \InvalidArgumentException
     */
    protected function buildUrl($asset, $params=array())
    {
        // Source
        $source  = isset($params['source']) ? (string) $params['source'] : $this->default_source;
        if ( ! isset($this->sources[$source])) {
            throw new \InvalidArgumentException('The `' .$source . '` Imgix source is not defined in your config.');
        }
        $this->sources[$source]['handle'] = $source;

        // Provider
        $provider  = isset($this->sources[$source]['provider']) ? (string) $this->sources[$source]['provider'] : $this->default_provider;

        // Legacy - if provider is Servd but using an Imgix domain, we can safely use the Imgix provider
        if ($provider === 'servd' && ( isset($this->sources[$source]['domain']) || isset($this->sources[$source]['endpoint']) )) {
            $provider = 'imgix';
        }
        $providerClass = '\croxton\imgixer\providers\\' . ucfirst($provider) .'Provider';

        // Build URL using the selected provider
        return (new $providerClass())->getUrl($this->sources[$source], $asset, $this->formatParams($params, $asset));
    }

    /**
     * Core parameter formatting
     *
     * @param array $params
     * @param string|Asset $asset The asset URL
     * @return array
     */
    protected function formatParams(array $params, Asset|string $asset): array
    {
        if (!$asset instanceof Asset) {
            return $params;
        }

        // Predictable focalpoint crops
        if ( isset($params['fit'], $params['crop']) && $params['fit'] === 'crop' && $params['crop'] === 'focalpoint') {

            $ar = $asset->width / $asset->height;
            if (isset($params['ar'])) {
                // get aspect ratio, if supplied
                $ar = explode(':', $params['ar']);
                if (isset($ar[0],$ar[1])) {
                    $ar = $ar[0] / $ar[1];
                }
            }

            // make sure we have both width and height set, if we only have one
            if (isset($params['w']) && !isset($params['h'])) {
                $params['h'] = (int) $params['w'] / $ar;
            } elseif (isset($params['h']) && !isset($params['w'])) {
                $params['w'] = (int) $params['h'] * $ar;
            } elseif(!isset($params['w'],$params['h'])) {
                $params['w'] = (int) $asset->width;
                $params['h'] = (int) $asset->width / $ar;
            }

            // Populate fp-x fp-y params from the asset, if not supplied
            if (!isset($params['fp-x'])) {
                $params['fp-x'] = $asset->focalPoint['x'] ?? 0.5;
            }
            if (!isset($params['fp-y'])) {
                $params['fp-x'] = $asset->focalPoint['y'] ?? 0.5;
            }
        }

        return $params;
    }
}