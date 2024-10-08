<?php

/**
 * Page to manage pending comments on posts.
 *
 * @copyright 2009-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class SiteCommentIndex extends AdminPage
{
    public const SHOW_UNAPPROVED = 1;
    public const SHOW_ALL = 2;
    public const SHOW_ALL_SPAM = 3;
    public const SHOW_SPAM = 4;

    /**
     * @var SiteCommentDisplay
     */
    protected $comment_display;

    /**
     * @var string
     */
    protected $where_clause;

    /**
     * @var array
     */
    protected $comments;

    // init phase

    protected function initInternal()
    {
        parent::initInternal();

        $this->ui->loadFromXML($this->getUiXml());

        $this->comment_display = $this->getCommentDisplayWidget();

        $this->ui->getWidget('comment_replicator')->add(
            $this->comment_display
        );

        $visibility = $this->ui->getWidget('search_visibility');
        $visibility->addOptionsByArray($this->getVisibilityOptions());
        $visibility->value = $this->getDefaultVisibilityValue();

        $this->processSearchUi();

        $this->initComments();
        $this->initCommentReplicator();
    }

    protected function getUiXml()
    {
        return __DIR__ . '/index.xml';
    }

    protected function getTable()
    {
        return 'Comment';
    }

    protected function initComments()
    {
        $pager = $this->ui->getWidget('pager');
        $pager->total_records = $this->getCommentCount();

        $comments = $this->getComments(
            $pager->page_size,
            $pager->current_record
        );

        $this->comments = [];
        foreach ($comments as $comment) {
            $this->comments[$comment->id] = $comment;
        }

        // init result message
        $visibility = $this->ui->getWidget('search_visibility')->value;
        $this->ui->getWidget('results_message')->content = match ($visibility) {
            self::SHOW_ALL => $pager->getResultsMessage(
                Site::_('comment'),
                Site::_('comments')
            ),
            self::SHOW_ALL_SPAM => $pager->getResultsMessage(
                Site::_('comment (including spam)'),
                Site::_('comments (including spam)')
            ),
            self::SHOW_SPAM => $pager->getResultsMessage(
                Site::_('spam comment'),
                Site::_('spam comments')
            ),
            default => $pager->getResultsMessage(
                Site::_('pending comment'),
                Site::_('pending comments')
            ),
        };
    }

    abstract protected function getCommentDisplayWidget();

    abstract protected function getCommentCount();

    abstract protected function getComments($limit = null, $offset = null);

    protected function initCommentReplicator()
    {
        $this->comment_display->setApplication($this->app);

        $replicator = $this->ui->getWidget('comment_replicator');
        $replicator->replication_ids = array_keys($this->comments);
    }

    protected function processSearchUi()
    {
        $search_frame = $this->ui->getWidget('search_frame');
        $search_frame->init();
        $search_frame->process();

        $form = $this->ui->getWidget('search_form');
        if ($form->isProcessed()) {
            $this->saveState();
        }

        if ($this->hasState()) {
            $this->loadState();
        }

        $this->ui->getWidget('pager')->init();
        $this->ui->getWidget('pager')->process();
    }

    protected function getWhereClause()
    {
        if ($this->where_clause === null) {
            $where = '1 = 1';

            $keywords = $this->ui->getWidget('search_keywords')->value;
            if (trim($keywords) != '') {
                $clause = new AdminSearchClause('bodytext', $keywords);
                $clause->table = $this->getTable();
                $clause->operator = AdminSearchClause::OP_CONTAINS;
                $where .= $clause->getClause($this->app->db, 'and');
            }

            $where .= $this->getAuthorWhereClause();

            $visibility = $this->ui->getWidget('search_visibility')->value;
            switch ($visibility) {
                default:
                case self::SHOW_UNAPPROVED :
                    $where .= sprintf(
                        ' and status = %s and spam = %s',
                        $this->app->db->quote(
                            SiteComment::STATUS_PENDING,
                            'integer'
                        ),
                        $this->app->db->quote(false, 'boolean')
                    );

                    break;

                case self::SHOW_ALL :
                    $where .= sprintf(
                        ' and spam = %s',
                        $this->app->db->quote(false, 'boolean')
                    );

                    break;

                case self::SHOW_ALL_SPAM :
                    // no extra where needed
                    break;

                case self::SHOW_SPAM :
                    $where .= sprintf(
                        ' and spam = %s',
                        $this->app->db->quote(true, 'boolean')
                    );

                    break;
            }

            $this->where_clause = $where;
        }

        return $this->where_clause;
    }

    protected function getAuthorWhereClause()
    {
        $where = '';

        $author = $this->ui->getWidget('search_author')->value;
        if (trim($author) != '') {
            $fullname_clause = new AdminSearchClause('fullname', $author);
            $fullname_clause->table = $this->getTable();
            $fullname_clause->operator = AdminSearchClause::OP_CONTAINS;

            $where = $fullname_clause->getClause($this->app->db, 'and');
        }

        return $where;
    }

    protected function getDefaultVisibilityValue()
    {
        return self::SHOW_ALL;
    }

    protected function getVisibilityOptions()
    {
        return [self::SHOW_UNAPPROVED => Site::_('Pending Comments'), self::SHOW_ALL => Site::_('All Comments'), self::SHOW_ALL_SPAM => Site::_('All Comments, Including Spam'), self::SHOW_SPAM => Site::_('Spam Only')];
    }

    // process phase

    /**
     * Clears a saved search state.
     */
    protected function clearState()
    {
        if ($this->hasState()) {
            unset($this->app->session->{$this->getKey()});
        }
    }

    protected function saveState()
    {
        $search_form = $this->ui->getWidget('search_form');
        $search_state = $search_form->getDescendantStates();
        $this->app->session->{$this->getKey()} = $search_state;
    }

    /**
     * Loads a saved search state for this page.
     *
     * @return bool true if a saved state exists for this page and false if
     *              it does not
     *
     * @see SitePostComments::hasState()
     */
    protected function loadState()
    {
        $return = false;

        $search_form = $this->ui->getWidget('search_form');

        if ($this->hasState()) {
            $search_form->setDescendantStates(
                $this->app->session->{$this->getKey()}
            );

            $return = true;
        }

        return $return;
    }

    /**
     * Checks if this search page has stored search information.
     *
     * @return bool true if this page has stored search information and
     *              false if it does not
     */
    protected function hasState()
    {
        return isset($this->app->session->{$this->getKey()});
    }

    protected function getKey()
    {
        return $this->source . '_search_state';
    }

    // build phase

    protected function buildInternal()
    {
        parent::buildInternal();
        $this->buildMessages();
        $this->buildSearchForm();
        $this->buildCommentReplicator();
    }

    protected function buildCommentReplicator()
    {
        $comment_replicator = $this->ui->getWidget('comment_replicator');
        foreach ($comment_replicator->replication_ids as $id) {
            $comment_display = $comment_replicator->getWidget('comment', $id);
            $comment_display->setComment($this->comments[$id]);
        }
    }

    protected function buildSearchForm()
    {
        $form = $this->ui->getWidget('search_form', true);
        $form->action = $this->source;
    }
}
