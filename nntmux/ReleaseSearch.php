<?php
namespace nntmux;

use nntmux\db\DB;

class ReleaseSearch
{
	const FULLTEXT = 0;
	const LIKE     = 1;
	const SPHINX   = 2;

	/***
	 * @var DB
	 */
	public $pdo;

	/**
	 * Array where keys are the column name, and value is the search string.
	 * @var array
	 */
	private $searchOptions;

	/**
	 * Sets the string to join the releases table to the release search table if using full text.
	 * @var string
	 */
	private $fullTextJoinString;

	/**
	 * @param DB $settings
	 */
	public function __construct(DB $settings)
	{
		switch (NN_RELEASE_SEARCH_TYPE) {
			case self::LIKE:
				$this->fullTextJoinString = '';
				break;
			case self::SPHINX:
				$this->fullTextJoinString = 'INNER JOIN releases_se rse ON rse.id = r.id';
				break;
			case self::FULLTEXT:
			default:
			$this->fullTextJoinString = 'INNER JOIN release_search_data rs on rs.releases_id = r.id';
				break;
		}

		$this->sphinxQueryOpt = ';limit=10000;maxmatches=10000;sort=relevance;mode=extended';
		$this->pdo = ($settings instanceof DB ? $settings : new DB());
	}

	/**
	 * Create part of a SQL query for searching releases.
	 *
	 * @param array $options   Array where keys are the column name, and value is the search string.
	 * @param bool  $forceLike Force a "like" search on the column.
	 *
	 * @return string
	 */
	public function getSearchSQL(array $options = [], $forceLike = false): string
	{
		$this->searchOptions = $options;

		if ($forceLike) {
			return $this->likeSQL();
		}

		switch (NN_RELEASE_SEARCH_TYPE) {
			case self::LIKE:
				$SQL = $this->likeSQL();
				break;
			case self::SPHINX:
				$SQL = $this->sphinxSQL();
				break;
			case self::FULLTEXT:
			default:
				$SQL = $this->fullTextSQL();
				break;
		}
		return $SQL;
	}

	/**
	 * Returns the string for joining the release search table to the releases table.
	 * @return string
	 */
	public function getFullTextJoinString(): string
	{
		return $this->fullTextJoinString;
	}

	/**
	 * Create SQL sub-query for full text searching.
	 *
	 * @return string
	 */
	private function fullTextSQL(): string
	{
		$return = '';
		foreach ($this->searchOptions as $columnName => $searchString) {
			$searchWords = '';

			// At least 1 search term needs to be mandatory.
			$words = explode(' ', (!preg_match('/[+!^]/', $searchString) ? '+' : '') . $searchString);
			foreach ($words as $word) {
				$word = str_replace("'", "\\'", str_replace(['!', '^'], '+', trim($word, "\n\t\r\0\x0B- ")));

				if ($word !== '' && $word !== '-' && strlen($word) > 1) {
					$searchWords .= ($word . ' ');
				}
			}
			$searchWords = trim($searchWords);
			if ($searchWords !== '') {
				$return .= sprintf(" AND MATCH(rs.%s) AGAINST('%s' IN BOOLEAN MODE)", $columnName, $searchWords);
			}

		}
		// If we didn't get anything, try the LIKE method.
		if ($return === '') {
			return $this->likeSQL();
		}
		return $return;
	}

	/**
	 * Create SQL sub-query for standard search.
	 *
	 * @return string
	 */
	private function likeSQL()
	{
		$return = '';
		foreach ($this->searchOptions as $columnName => $searchString) {
			$wordCount = 0;
			$words = explode(' ', $searchString);
			foreach ($words as $word) {
				if ($word !== '') {
					$word = trim($word, "-\n\t\r\0\x0B ");
					if ($wordCount === 0 && (strpos($word, '^') === 0)) {
						$return .= sprintf(' AND r.%s %s', $columnName, $this->pdo->likeString(substr($word, 1), false));
					} else if (strpos($word, '--') === 0) {
						$return .= sprintf(' AND r.%s NOT %s', $columnName, $this->pdo->likeString(substr($word, 2)));
					} else {
						$return .= sprintf(' AND r.%s %s', $columnName, $this->pdo->likeString($word));
					}
					$wordCount++;
				}
			}
		}
		return $return;
	}

	/**
	 * Create SQL sub-query using sphinx full text search.
	 *
	 * @return string
	 */
	private function sphinxSQL()
	{
		$searchQuery = $fullReturn = '';

		foreach ($this->searchOptions as $columnName => $searchString) {
			$searchWords = '';
			$words = explode(' ', $searchString);
			foreach ($words as $word) {
				$word = str_replace("'", "\\'", trim($word, "\n\t\r\0\x0B "));
				if ($word !== '') {
					$searchWords .= ($word . ' ');
				}
			}
			$searchWords = rtrim($searchWords, "\n\t\r\0\x0B ");
			if ($searchWords !== '') {
				$searchQuery .= sprintf('@%s %s ',
					$columnName,
					$searchWords
				);
			}
		}
		if ($searchQuery !== '') {
			$fullReturn = sprintf("AND (rse.query = '@@relaxed %s')", trim($searchQuery) . $this->sphinxQueryOpt);
		} else {
			$fullReturn = $this->likeSQL();
		}
		return $fullReturn;
	}
}
