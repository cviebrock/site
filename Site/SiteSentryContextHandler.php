<?php

declare(strict_types=1);

use Sentry\Event;
use Sentry\EventHint;

/**
 * A handler to add additional exception context to Sentry.
 *
 * Meant to be used as the `before_send` configuration setting
 * when initializing Sentry, i.e.:
 *
 * ```
 * \Sentry\init([
 *     'dsn'         => '...',
 *     'before_send' => new SiteSentryContextHandler(),
 * ]);
 * ```
 *
 * @copyright 2026 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteSentryContextHandler
{
    public function __invoke(Event $event, ?EventHint $hint): ?Event
    {
        $exception = $hint?->exception;

        if ($exception instanceof SiteException) {
            foreach ($exception->getContext() as $key => $value) {
                $event->setContext($key, $value);
            }

            foreach ($exception->getTags() as $key => $value) {
                $event->setTag($key, $value);
            }
        }

        return $event;
    }
}
