<?php

/**
 * Base class for a concrete page.
 *
 * @copyright 2004-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SitePage extends SiteAbstractPage
{
    /**
     * Creates a concrete page object which may optionally be decorated.
     *
     * Note: Ideally, the source string would be passed as the third parameter
     * of this method. The source string is set separately using
     * {@link SitePage::setSource} to maintain backwards compatibility.
     *
     * @param SiteLayout $layout    optional
     * @param array      $arguments optional. Additional arguments passed to this
     *                              page. See
     *                              {@link SiteAbstractPage::getArgument()} and
     *                              {@link SiteAbstractPage::getArgumentMap()}.
     */
    public function __construct(
        SiteApplication $app,
        ?SiteLayout $layout = null,
        array $arguments = []
    ) {
        $this->app = $app;
        $this->layout = $layout ?? $this->createLayout();
        $this->arguments = $arguments;
    }

    protected function createLayout()
    {
        return new SiteLayout($this->app, SiteDefaultTemplate::class);
    }

    // build phase

    public function build()
    {
        $this->buildTitle();
        $this->buildMetaDescription();

        if (isset($this->layout->navbar)) {
            $this->buildNavBar();
        }

        $this->buildContent();
    }

    protected function buildTitle() {}

    protected function buildMetaDescription() {}

    protected function buildContent() {}

    protected function buildNavBar() {}
}
