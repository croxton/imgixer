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
    private array $sources;

    /**
     * The default source to use
     *
     * @var        string
     */
    private string|int|null $default_source;


    /**
     * The default provider to use
     *
     * @var        string
     */
    private string $default_provider;

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
    public function getName(): string
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
    public function getFilters(): array
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
    public function getFunctions(): array
    {
        return [
            new \Twig\TwigFunction('imgix', [$this, 'imgix']),
        ];
    }

    /**
     * Generate src and srcset values, optionally for a range of image sizes
     *
     * @access public
     * @param string|Asset $img The asset
     * @param array $params An array of Imgix parameters
     * @return string
     */
    public function imgix(Asset|string $img, array $params=array()): string
    {
        // Register source
        $source  = isset($params['source']) ? (string) $params['source'] : $this->default_source;
        if ( ! isset($this->sources[$source])) {
            throw new \InvalidArgumentException('The `' .$source . '` source is not defined in your config.');
        }

        // Merge any default params
        if ( isset($this->sources[$source]['defaultParams'])) {
            $params = array_merge($this->sources[$source]['defaultParams'], $params);
        }

        // Make sure we have a handle
        $this->sources[$source]['handle'] = $source;

        // srcset generation
        $srcset = [];
        $from  = isset($params['from']) ? (int) $params['from'] : false;
        $to  = isset($params['to']) ? (int) $params['to'] : false;
        $step  = isset($params['step']) ? (int) $params['step'] : 100;

        unset($params['from'], $params['to'], $params['step']);

        if ($from && $to) {
            foreach (range($from, $to, $step) as $number) {
                $params['w'] = $number;
                if ($src = $this->buildUrl($this->sources[$source], $img, $params)) {
                    $srcset[] =  $src . ' ' .$number . 'w';
                }
            }
        } else {
            $srcset[] = $this->buildUrl($this->sources[$source], $img, $params);
        }

        return implode(',', $srcset);
    }

    /**
     * Build an image transform URL
     *
     * @access protected
     * @param array $source The source config
     * @param string|Asset $asset The asset URL
     * @param array $params An array of parameters
     * @return string|null
     */
    protected function buildUrl(array $source, Asset|string $asset, array $params=array()): ?string
    {
        // Provider
        $provider  = isset($source['provider']) ? (string) $source['provider'] : $this->default_provider;

        // Legacy - if provider is Servd but using an Imgix domain, we can safely use the Imgix provider
        if ($provider === 'servd' && ( isset($source['domain']) || isset($source['endpoint']) )) {
            $provider = 'imgix';
        }
        $providerClass = '\croxton\imgixer\providers\\' . ucfirst($provider) .'Provider';

        // Build URL using the selected provider
        return (new $providerClass())->getUrl($source, $asset, $this->formatParams($params, $asset));
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
                $params['fp-y'] = $asset->focalPoint['y'] ?? 0.5;
            }
        }

        return $params;
    }
}