<?php

/**
 * An exception in Site package.
 *
 * @copyright 2004-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteException extends SwatException
{
    public $title;
    public $http_status_code = 500;

    public function __construct($message = null, $code = 0)
    {
        if (is_object($message) && ($message instanceof PEAR_Error)) {
            $error = $message;
            $message = $error->getMessage();
            $message .= "\n" . $error->getUserInfo();
            $code = $error->getCode();
        }

        parent::__construct($message, $code);
    }
}
