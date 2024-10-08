<?php

/**
 * Base class for database edit pages.
 *
 * @copyright 2008-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class SiteDBEditPage extends SiteEditPage
{
    // process phase

    protected function save(SwatForm $form)
    {
        $transaction = new SwatDBTransaction($this->app->db);

        try {
            $this->saveData($form);
            $transaction->commit();
        } catch (SwatDBException $e) {
            if ($this->app->hasModule('SiteMessagesModule')) {
                $messages = $this->app->getModule('SiteMessagesModule');
                $messages->add($this->getRollbackMessage($form));
            }
            $transaction->rollback();
            $this->handleDBException($e);
        } catch (Throwable $e) {
            $this->handleException($transaction, $e);
        }
    }

    abstract protected function saveData(SwatForm $form);

    protected function getRollbackMessage(SwatForm $form)
    {
        return new SwatMessage(
            Site::_('An error has occurred. The item was not saved.'),
            'system-error'
        );
    }

    protected function handleDBException(SwatDBException $e)
    {
        $e->processAndContinue();
    }

    protected function handleException(
        SwatDBTransaction $transaction,
        Throwable $e
    ) {
        throw $e;
    }
}
