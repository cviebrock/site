<?php

/**
 * Edit page for Articles.
 *
 * @copyright 2005-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteArticleEdit extends AdminDBEdit
{
    protected $parent;
    protected $edit_article;

    // init phase

    protected function initInternal()
    {
        parent::initInternal();

        $this->ui->mapClassPrefixToPath('Site', 'Site');
        $this->ui->loadFromXML($this->getUiXml());

        $this->initArticle();

        $this->parent = SiteApplication::initVar('parent');

        $form = $this->ui->getWidget('edit_form');
        $form->addHiddenField('parent', $this->parent);

        if ($this->id === null) {
            $this->ui->getWidget('shortname_field')->visible = false;
        }
    }

    protected function initArticle()
    {
        $class_name = SwatDBClassMap::get(SiteArticle::class);
        $this->edit_article = new $class_name();
        $this->edit_article->setDatabase($this->app->db);

        if ($this->id !== null) {
            if (!$this->edit_article->load($this->id)) {
                throw new AdminNotFoundException(
                    sprintf(
                        Site::_('Article with id "%s" not found.'),
                        $this->id
                    )
                );
            }
        }
    }

    protected function getUiXml()
    {
        return __DIR__ . '/edit.xml';
    }

    // process phase

    protected function validate(): void
    {
        $shortname = $this->ui->getWidget('shortname');
        $title = $this->ui->getWidget('title');

        if ($this->id === null && $shortname->value === null) {
            $new_shortname = $this->generateShortname($title->value);
            $shortname->value = $new_shortname;
        } elseif (!$this->validateShortname($shortname->value)) {
            $message = new SwatMessage(
                Site::_('Shortname already exists and must be unique.'),
                'error'
            );

            $shortname->addMessage($message);
        }
    }

    protected function validateShortname($shortname)
    {
        $valid = true;

        $class_name = SwatDBClassMap::get(SiteArticle::class);
        $article = new $class_name();
        $article->setDatabase($this->app->db);

        if ($article->loadByShortname($shortname)) {
            if ($article->id !== $this->edit_article->id
                && $article->getInternalValue('parent') == $this->parent) {
                $valid = false;
            }
        }

        return $valid;
    }

    protected function saveDBData(): void
    {
        $now = new SwatDate();
        $now->toUTC();

        if ($this->id === null) {
            $this->edit_article->createdate = $now->getDate();
        }

        $this->edit_article->parent = $this->parent;
        $this->edit_article->modified_date = $now->getDate();

        $this->saveArticle();

        if (isset($this->app->memcache)) {
            $this->app->memcache->flushNs('article');
        }

        $message = new SwatMessage(
            sprintf(
                Site::_('“%s” has been saved.'),
                $this->edit_article->title
            )
        );

        $this->app->messages->add($message);
    }

    protected function saveArticle()
    {
        $values = $this->ui->getValues(['title', 'shortname', 'bodytext', 'description', 'enabled', 'visible', 'searchable']);

        $this->edit_article->title = $values['title'];
        $this->edit_article->shortname = $values['shortname'];
        $this->edit_article->bodytext = $values['bodytext'];
        $this->edit_article->description = $values['description'];
        $this->edit_article->enabled = $values['enabled'];
        $this->edit_article->visible = $values['visible'];
        $this->edit_article->searchable = $values['searchable'];

        $this->edit_article->save();
    }

    // build phase

    protected function loadDBData()
    {
        $this->ui->setValues($this->edit_article->getAttributes());

        $this->parent = $this->edit_article->getInternalValue('parent');
        $form = $this->ui->getWidget('edit_form');
        $form->addHiddenField('parent', $this->parent);
    }

    protected function buildNavBar()
    {
        if ($this->id !== null) {
            $navbar_rs = SwatDB::executeStoredProc(
                $this->app->db,
                'getArticleNavBar',
                [$this->id]
            );

            foreach ($navbar_rs as $elem) {
                $this->navbar->addEntry(new SwatNavBarEntry(
                    $elem->title,
                    'Article/Index?id=' . $elem->id
                ));
            }
        } elseif ($this->parent !== null) {
            $navbar_rs = SwatDB::executeStoredProc(
                $this->app->db,
                'getArticleNavBar',
                [$this->parent]
            );

            foreach ($navbar_rs as $elem) {
                $this->navbar->addEntry(new SwatNavBarEntry(
                    $elem->title,
                    'Article/Index?id=' . $elem->id
                ));
            }
        }

        parent::buildNavBar();
    }
}
