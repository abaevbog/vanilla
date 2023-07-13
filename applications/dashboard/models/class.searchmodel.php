<?php
/**
 * Search model.
 *
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 * @package Dashboard
 * @since 2.0
 */
use Elasticsearch\ClientBuilder;
/**
 * Handles search data.
 */
class SearchModel extends Gdn_Model {

    /** @var array Parameters. */
    protected $_Parameters = [];

    /** @var array SQL. */
    protected $_SearchSql = [];

    /** @var string Mode. */
    protected $_SearchMode = 'match';

    /** @var bool Whether to force the mode. */
    public $ForceSearchMode = '';

    /** @var string Search string. */
    protected $_SearchText = '';

	public $ES_QUERY = array(
		"size" => 20,
		"from" => 0,
		"highlight" => array(
			"fields" => array(
				"body" => array(
					"pre_tags" => array("<strong>"),
					"post_tags" => array("</strong>"),
					"number_of_fragments" => 2,
					"fragment_size" => 100
				)
			)
		),
		"collapse" => array(
			"field" => "discussionId"
		),
		"query" => array(
			"function_score" => array(
				"query" => array(
					"bool" => array(
						"should" => array(),
						"must" => array()
					)
				),
				"functions" => array(
					array(
						"filter" => array(
							"term" => array(
								"endorsed" => true
							)
						),
						"weight" => 15
					),
					array(
						"filter" => array(
							"range" => array(
								"date" => array(
									"gte" => "now-3M"
								)
							)
						),
						"weight" => 10
					),
					array(
						"filter" => array(
							"range" => array(
								"date" => array(
									"gte" => "now-1y",
									"lt" => "now-3M"
								)
							)
						),
						"weight" => 8
					),
					array(
						"filter" => array(
							"range" => array(
								"date" => array(
									"gte" => "now-1y",
									"lt" => "now-3y"
								)
							)
						),
						"weight" => 7
					),
					array(
						"filter" => array(
							"range" => array(
								"date" => array(
									"gte" => "now-3y",
									"lt" => "now-5y"
								)
							)
						),
						"weight" => 5
					)
				),
				"score_mode" => "sum",
				"boost_mode" => "sum"
			)
		)
	);

    /**
     *
     *
     * @param $sql
     */
    public function addSearch($sql) {
        $this->_SearchSql[] = $sql;
    }

    /** Add the sql to perform a search.
     *
     * @param Gdn_SQLDriver $sql
     * @param string $columns a comma seperated list of columns to search on.
     */
    public function addMatchSql($sql, $columns, $likeRelevanceColumn = '') {
        if ($this->_SearchMode == 'like') {
            if ($likeRelevanceColumn) {
                $sql->select($likeRelevanceColumn, '', 'Relevance');
            } else {
                $sql->select(1, '', 'Relevance');
            }

            $sql->beginWhereGroup();

            $columnsArray = explode(',', $columns);

            $first = true;
            foreach ($columnsArray as $column) {
                $column = trim($column);

                $param = $this->parameter();
                if ($first) {
                    $sql->where("$column like $param", null, false, false);
                    $first = false;
                } else {
                    $sql->orWhere("$column like $param", null, false, false);
                }
            }

            $sql->endWhereGroup();
        } else {
            $boolean = $this->_SearchMode == 'boolean' ? ' in boolean mode' : '';

            $param = $this->parameter();
            $sql->select($columns, "match(%s) against($param{$boolean})", 'Relevance');
            $param = $this->parameter();
            $sql->where("match($columns) against ($param{$boolean})", null, false, false);
        }
    }

    /**
     *
     *
     * @return string
     */
    public function parameter() {
        $parameter = ':Search'.count($this->_Parameters);
        $this->_Parameters[$parameter] = '';
        return $parameter;
    }

    /**
     *
     */
    public function reset() {
        $this->_Parameters = [];
        $this->_SearchSql = '';
    }

	public function search($Search, $Offset = 0, $Limit = 20) {
        $builder = ClientBuilder::create();
        $builder->setHosts(c('ES_HOST'));
        $client = $builder->build();
        // Adjust site and limit for pagination
        $this->ES_QUERY['size'] = $Limit;
        $this->ES_QUERY['from'] = $Offset;

        // Fetch all phrases between quotes
        preg_match_all('/"([^"]+)"/', $Search, $matches);

        // For every phrase, add a "must" condition for body
        // and a "should" condition for discussionTitle
        for ($i = 0; $i < count($matches[0]); $i++) {
            $phrase_match = $matches[0][$i];
            if(!is_null($phrase_match)) {
                array_push($this->ES_QUERY['query']['function_score']['query']['bool']['must'], array("match_phrase" => array("body" => $phrase_match)));
                array_push($this->ES_QUERY['query']['function_score']['query']['bool']['should'], array("match" => array("discussionName" => $phrase_match)));
                // Remove phrase from general keyword search
                $Search = str_replace($phrase_match, '', $Search);
            }
        }

        $Search = trim($Search);

        // Add "should" conditions for keyword search
        if($Search != "") {
            array_push($this->ES_QUERY['query']['function_score']['query']['bool']['should'], array("match" => array("discussionName" => $Search)));
            array_push($this->ES_QUERY['query']['function_score']['query']['bool']['should'], array("match" => array("body" => $Search)));
        }

        $data = $client->search(['index' => c('ES_INDEX'), 'body' => $this->ES_QUERY]);
        $result = array();
		
        // Format received data in the same way as it was returned by SQL
        // to avoid changing views
        foreach ($data['hits']['hits'] as $hit) {
			// Generate a discussion link if it was the first comment
			// or a comment link otherwise
			$record_id = $hit['_source']['id'];
			if (strpos($record_id, "D_") !== false) {
				$url = "https://forums.zotero.org/discussion/" . str_replace("D_", "", $record_id);
			}
			else {
				$url = "https://forums.zotero.org/discussion/comment/$record_id/#Comment_$record_id";
			}
            $formattedEntry = array(
                'Title' => $hit['_source']['discussionName'],
                'Summary' => implode("...", $hit['highlight']['body']),
                'Url' => $url,
                'DateInserted' => $hit['_source']['date'],
                'UserID' => $hit['_source']['user']
            );
            array_push($result,$formattedEntry);
        }
        return $result;
    }

    /**
     *
     *
     * @param $search
     * @param int $offset
     * @param int $limit
     * @return array|null
     * @throws Exception
     */
    public function search_old($search, $offset = 0, $limit = 20) {
        // If there are no searches then return an empty array.
        if (trim($search) == '') {
            return [];
        }

        // Figure out the exact search mode.
        if ($this->ForceSearchMode) {
            $searchMode = $this->ForceSearchMode;
        } else {
            $searchMode = strtolower(c('Garden.Search.Mode', 'matchboolean'));
        }

        if ($searchMode == 'matchboolean') {
            if (strpos($search, '+') !== false || strpos($search, '-') !== false) {
                $searchMode = 'boolean';
            } else {
                $searchMode = 'match';
            }
        } else {
            $this->_SearchMode = $searchMode;
        }

        if ($forceDatabaseEngine = c('Database.ForceStorageEngine')) {
            if (strcasecmp($forceDatabaseEngine, 'myisam') != 0) {
                $searchMode = 'like';
            }
        }

        if (strlen($search) <= 4) {
            $searchMode = 'like';
        }

        $this->_SearchMode = $searchMode;

        $this->EventArguments['Search'] = $search;
        $this->fireEvent('Search');

        if (count($this->_SearchSql) == 0) {
            return [];
        }

        // Perform the search by unioning all of the sql together.
        $sql = $this->SQL
            ->select()
            ->from('_TBL_ s', false)
            ->orderBy('s.DateInserted', 'desc')
            ->limit($limit, $offset)
            ->getSelect();

        $sql = str_replace($this->Database->DatabasePrefix.'_TBL_', "(\n".implode("\nunion all\n", $this->_SearchSql)."\n)", $sql);

        $this->fireEvent('AfterBuildSearchQuery');

        if ($this->_SearchMode == 'like') {
            $search = '%'.$search.'%';
        }

        foreach ($this->_Parameters as $key => $value) {
            $this->_Parameters[$key] = $search;
        }

        $parameters = $this->_Parameters;
        $this->reset();
        $this->SQL->reset();
        $result = $this->Database->query($sql, $parameters)->resultArray();

        foreach ($result as $key => $value) {
            if (isset($value['Summary'])) {
                $value['Summary'] = condense(Gdn_Format::to($value['Summary'], $value['Format']));
                // We just converted it to HTML. Make sure everything downstream knows it.
                // Taking this HTML and feeding it into the Rich Format for example, would be invalid.
                $value['Format'] = 'Html';
                $result[$key] = $value;
            }

            switch ($value['RecordType']) {
                case 'Comment':
                    $comment = arrayTranslate($value, ['PrimaryID' => 'CommentID', 'CategoryID']);
                    $result[$key]['Url'] = commentUrl($comment);
                    break;
                case 'Discussion':
                    $discussion = arrayTranslate($value, ['PrimaryID' => 'DiscussionID', 'Title' => 'Name', 'CategoryID']);
                    $result[$key]['Url'] = discussionUrl($discussion, 1);
                    break;
            }
        }

        return $result;
    }

    /**
     *
     *
     * @param null $value
     * @return null|string
     */
    public function searchMode($value = null) {
        if ($value !== null) {
            $this->_SearchMode = $value;
        }
        return $this->_SearchMode;
    }
}
