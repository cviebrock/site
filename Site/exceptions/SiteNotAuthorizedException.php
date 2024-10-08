<?php

/**
 * Thrown when page is not authorized when http auth is used.
 *
 * @copyright 2011-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteNotAuthorizedException extends SiteException
{
    /**
     * Creates a new not authorized exception.
     *
     * @param string $message the message of the exception
     * @param int    $code    the code of the exception
     */
    public function __construct($message = null, $code = 0)
    {
        parent::__construct($message, $code);
        $this->title = Site::_('Not Authorized');
        $this->http_status_code = 401;
    }
}
