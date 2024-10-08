<?php

/**
 * Path page decorator.
 *
 * @copyright 2004-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SitePathPage extends SitePageDecorator
{
    /**
     * @var SitePath
     *
     * @see SitePathPage::getPath()
     * @see SitePathPage::setPath()
     */
    protected $path;

    public function __construct(SiteAbstractPage $page)
    {
        parent::__construct($page);
        $this->path = new SitePath();
    }

    /**
     * Sets the path of this page.
     *
     * Note: Ideally, the path would be set in the constructor of this class
     * and would only have a public accessor method. A setter method exists
     * here for backwards compatibility.
     */
    public function setPath(SitePath $path)
    {
        $this->path = $path;
    }

    /**
     * Gets the path of this page.
     *
     * @return SitePath the path of this page
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Whether or not this page has the parent id in its path.
     *
     * @param int $id the parent id to check
     *
     * @return bool true if this page has the given id in its path and false
     *              if it does not
     */
    public function hasParentInPath($id)
    {
        return $this->path->hasId($id);
    }

    // build phase

    protected function buildNavBar()
    {
        parent::buildNavBar();

        if (isset($this->layout->navbar)
            && $this->layout->navbar instanceof SwatNavBar) {
            $navbar = $this->layout->navbar;
            $link = '';
            $first = true;
            foreach ($this->path as $path_entry) {
                if ($first) {
                    $link .= $path_entry->shortname;
                    $first = false;
                } else {
                    $link .= '/' . $path_entry->shortname;
                }

                $navbar->createEntry($path_entry->title, $link);
            }
        }
    }
}
