<?php

/**
 * @copyright 2012-2016 silverorange
 */
class SiteAccountSearch
{
    /**
     * @var SiteApplication
     */
    protected $app;

    /**
     * @var SwatUI
     */
    protected $ui;

    public function __construct(SiteApplication $app, SwatUI $ui)
    {
        $this->app = $app;
        $this->ui = $ui;
    }

    public function getJoinClause() {}

    public function getWhereClause()
    {
        // Excluded deleted and cleaned accounts.
        $where = sprintf(
            'Account.delete_date %s %s and %s',
            SwatDB::equalityOperator(null),
            $this->app->db->quote(null, 'date'),
            $this->getCleanedAccountClause()
        );

        foreach ($this->getWhereClauses() as $clause) {
            $where .= $clause->getClause($this->app->db);
        }

        return $where;
    }

    public function getOrderByClause()
    {
        return 'fullname, email';
    }

    protected function getWhereClauses()
    {
        $clauses = [];

        // instance
        $instance_id = $this->app->getInstanceId();
        if ($instance_id === null && $this->ui->hasWidget('search_instance')) {
            $instance_id = $this->ui->getWidget('search_instance')->value;
        }

        if ($instance_id !== null) {
            $clause = new AdminSearchClause('integer:instance');
            $clause->table = 'Account';
            $clause->value = $instance_id;
            $clauses['instance'] = $clause;
        }

        // fullname
        $clause = new AdminSearchClause('fullname');
        $clause->table = 'Account';
        $clause->value = $this->ui->getWidget('search_fullname')->value;
        $clause->operator = AdminSearchClause::OP_CONTAINS;
        $clauses['fullname'] = $clause;

        // email
        $clause = new AdminSearchClause('email');
        $clause->table = 'Account';
        $clause->value = $this->ui->getWidget('search_email')->value;
        $clause->operator = AdminSearchClause::OP_CONTAINS;
        $clauses['email'] = $clause;

        return $clauses;
    }

    protected function getCleanedAccountClause()
    {
        // The only way an account fullname can be null is if we've cleared
        // the data from it with the privacy scripts - we don't ever want to
        // display these accounts in the search results.
        return sprintf(
            'Account.fullname %s %s',
            SwatDB::equalityOperator(null, true),
            $this->app->db->quote(null, 'text')
        );
    }
}
