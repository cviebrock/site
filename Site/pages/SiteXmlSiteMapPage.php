<?php

require_once 'Site/pages/SitePage.php';
require_once 'Site/layouts/SiteXmlSiteMapLayout.php';
require_once 'Site/dataobjects/SiteArticleWrapper.php';

/**
 * A generated XML Site Map
 *
 * @package   Site
 * @copyright 2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       http://www.sitemaps.org/
 */
class SiteXmlSiteMapPage extends SitePage
{
	// {{{ protected properties

	protected $priority_paths = array();

	// }}}
	// {{{ public function displayPath()

	public function displayPath($path, $last_modified = null, $frequency = null,
		$priority = null)
	{
		echo "<url>\n";

		printf("<loc>%s</loc>\n",
			htmlspecialchars($this->app->getBaseHref().$path));

		if ($last_modified !== null)
			printf("<lastmod>%s</lastmod>\n",
				$last_modified->getDate());

		if ($frequency !== null)
			printf("<changefreq>%s</changefreq>\n",
				$frequency);

		if ($priority !== null)
			printf("<priority>%s</priority>\n",
				$priority);
		elseif (array_key_exists($path, $this->priority_paths))
			printf("<priority>%s</priority>\n",
				$this->priority_paths[$path]);

		echo "</url>\n";
	}

	// }}}
	// {{{ public function build()

	public function build()
	{
		$this->layout->startCapture('site_map');
		$this->displaySiteMap();
		$this->layout->endCapture();
	}

	// }}}
	// {{{ protected function addPriorityPath()

	protected function addPriorityPath($path, $priority = 1)
	{
		$this->priority_paths[$path] = $priority;
	}

	// }}}
	// {{{ protected function displaySiteMap()

	protected function displaySiteMap()
	{
		$articles = $this->queryArticles();
		$this->displayArticles($articles);
	}

	// }}}
	// {{{ protected function displayArticles()

	protected function displayArticles($articles, $path = null)
	{
		foreach ($articles as $article) {
			if ($path === null)
				$article_path = $article->shortname;
			else
				$article_path = $path.'/'.$article->shortname;

			$this->displayPath($article_path, $article->modified_date, 'weekly');

			if (count($article->sub_articles) > 0)
				$this->displayArticles($article->sub_articles, $article_path);
		}
	}

	// }}}
	// {{{ protected function queryArticles()

	protected function queryArticles()
	{
		$wrapper = SwatDBClassMap::get('SiteArticleWrapper');

		$sql = 'select id, shortname, modified_date
			from Article
			where parent is null and enabled = %1$s and show = %1$s
			order by displayorder, title';

		$sql = sprintf($sql, $this->app->db->quote(true, 'boolean'));

		$articles = SwatDB::query($this->app->db, $sql, $wrapper);

		return $articles;
	}

	// }}}
	// {{{ protected function createLayout()

	protected function createLayout()
	{
		return new SiteXmlSiteMapLayout($this->app);
	}

	// }}}
}

?>