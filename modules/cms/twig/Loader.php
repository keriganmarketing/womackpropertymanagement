<?php namespace Cms\Twig;

use Event;
use Twig_Source;
use Twig_LoaderInterface;
use Cms\Contracts\CmsObject;
use System\Twig\Loader as LoaderBase;
use Cms\Classes\Partial as CmsPartial;

/**
 * This class implements a Twig template loader for the CMS.
 *
 * @package october\cms
 * @author Alexey Bobkov, Samuel Georges
 */
class Loader extends LoaderBase implements Twig_LoaderInterface
{
    /**
     * @var \Cms\Classes\CmsCompoundObject A CMS object to load the template from.
     */
    protected $obj;

    /**
     * @var array Cache
     */
    protected $fallbackCache = [];

    /**
     * Sets a CMS object to load the template from.
     * @param \Cms\Contracts\CmsObject $obj Specifies the CMS object.
     */
    public function setObject(CmsObject $obj)
    {
        $this->obj = $obj;
    }

    /**
     * Returns the Twig content string.
     * This step is cached internally by Twig.
     */
    public function getSourceContext($name)
    {
        if (!$this->validateCmsObject($name)) {
            return parent::getSourceContext($name);
        }

        $content = $this->obj->getTwigContent();
        $content = ($content != null ? $content : '');

        /*
         * Extensibility
         */
        $dataHolder = (object) ['content' => $content];

        Event::fire('cms.template.processTwigContent', [$this->obj, $dataHolder]);

        return new Twig_Source($dataHolder->content, $name);
    }

    /**
     * Returns the Twig cache key.
     */
    public function getCacheKey($name)
    {
        if (!$this->validateCmsObject($name)) {
            return parent::getCacheKey($name);
        }

        return $this->obj->getTwigCacheKey();
    }

    /**
     * Determines if the content is fresh.
     */
    public function isFresh($name, $time)
    {
        if (!$this->validateCmsObject($name)) {
            return parent::isFresh($name, $time);
        }

        return $this->obj->mtime <= $time;
    }

    /**
     * Returns the file name of the loaded template.
     */
    public function getFilename($name)
    {
        if (!$this->validateCmsObject($name)) {
            return parent::getFilename($name);
        }

        return $this->obj->getFilePath();
    }

    /**
     * Checks that the template exists.
     */
    public function exists($name)
    {
        if (!$this->validateCmsObject($name)) {
            return parent::exists($name);
        }

        return $this->obj->exists;
    }

    /**
     * Internal method that checks if the template name matches
     * the loaded object, with fallback support to partials.
     *
     * @return bool
     */
    protected function validateCmsObject($name)
    {
        if ($name == $this->obj->getFilePath()) {
            return true;
        }

        if ($fallbackObj = $this->findFallbackObject($name)) {
            $this->obj = $fallbackObj;
            return true;
        }

        return false;
    }

    /**
     * Looks up a fallback CMS partial object.
     * @return Cms\Classes\Partial
     */
    protected function findFallbackObject($name)
    {
        if (strpos($name, '::') !== false) {
            return false;
        }

        if (array_key_exists($name, $this->fallbackCache)) {
            return $this->fallbackCache[$name];
        }

        return $this->fallbackCache[$name] = CmsPartial::find($name);
    }
}
