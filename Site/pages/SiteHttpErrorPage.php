<?php

/**
 * @copyright 2006-2016 silverorange
 */
class SiteHttpErrorPage extends SitePage
{
    protected $http_status_code;
    protected $uri;

    // init phase

    public function init()
    {
        parent::init();

        if ($this->http_status_code === null) {
            if (isset($_SERVER['REDIRECT_STATUS'])) {
                $this->http_status_code = intval($_SERVER['REDIRECT_STATUS']);
            } else {
                $this->http_status_code = 500;
            }
        }

        $exp = explode('/', $this->app->getBaseHref());
        // shift off the 'http://server' part
        array_shift($exp);
        array_shift($exp);
        array_shift($exp);
        $prefix = '/' . implode('/', $exp);
        $len = mb_strlen($prefix);

        if (strncmp($prefix, $_SERVER['REQUEST_URI'], $len) === 0) {
            $this->uri = mb_substr($_SERVER['REQUEST_URI'], $len);
        } else {
            $this->uri = $_SERVER['REQUEST_URI'];
        }
    }

    /**
     * Sets the HTTP status code for this error page.
     *
     * @param int $status the HTTP status code
     */
    public function setStatus($status)
    {
        $this->http_status_code = (int) $status;
    }

    // build phase

    public function build()
    {
        parent::build();

        $this->sendHttpStatusHeader();
        $this->layout->data->title = $this->getTitle();
        $this->layout->data->site_title = $this->app->config->site->title;

        $this->layout->startCapture('content');
        $this->display();
        $this->layout->endCapture();
    }

    protected function display()
    {
        $p_tag = new SwatHtmlTag('p');
        $p_tag->class = 'summary';
        $p_tag->setContent($this->getSummary());
        $p_tag->display();

        $this->displaySuggestions();

        echo '<p class="debug-info">';

        printf(
            Site::_('HTTP status code: %s') . '<br />',
            SwatString::minimizeEntities($this->http_status_code)
        );

        printf(
            Site::_('URI: %s') . '<br />',
            SwatString::minimizeEntities($this->uri)
        );

        echo '</p>';
    }

    protected function displaySuggestions()
    {
        $suggestions = $this->getSuggestions();

        if (count($suggestions) === 0) {
            return;
        }

        echo '<ul class="suggestions spaced">';

        $li_tag = new SwatHtmlTag('li');
        foreach ($suggestions as $suggestion) {
            $li_tag->setContent($suggestion, 'text/xml');
            $li_tag->display();
        }

        echo '</ul>';
    }

    protected function getSuggestions()
    {
        return ['contact' => SwatString::minimizeEntities(
            Site::_(
                'If you followed a link from our site or elsewhere, ' .
                'please contact us and let us know where you came from ' .
                'so we can do our best to fix it.'
            )
        ), 'typo' => SwatString::minimizeEntities(
            Site::_(
                'If you typed in the address, please double check the ' .
                'spelling.'
            )
        )];
    }

    protected function sendHttpStatusHeader()
    {
        match ($this->http_status_code) {
            400     => header('HTTP/1.0 400 Bad Request'),
            403     => header('HTTP/1.0 403 Forbidden'),
            404     => header('HTTP/1.0 404 Not Found'),
            default => header('HTTP/1.0 500 Internal Server Error'),
        };
    }

    protected function getTitle()
    {
        return match ($this->http_status_code) {
            400     => Site::_('Bad Request'),
            404     => Site::_('Page Not Found'),
            403     => Site::_('Forbidden'),
            default => Site::_('Internal Server Error'),
        };
    }

    protected function getSummary()
    {
        return match ($this->http_status_code) {
            404     => Site::_('Sorry, we couldn’t find the page you were looking for.'),
            403     => Site::_('Sorry, the page you requested is not accessible.'),
            default => Site::_('Sorry, there was a problem loading the page you requested.'),
        };
    }

    // finalize phase

    public function finalize()
    {
        parent::finalize();
        $this->layout->addBodyClass('http-error-page');
    }
}
