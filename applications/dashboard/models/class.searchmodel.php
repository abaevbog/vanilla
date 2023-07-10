<?php
/**
 * Search model.
 *
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package Dashboard
 * @since 2.0
 */
use Elasticsearch\ClientBuilder;
/**
 * Handles search data.
 */
class SearchModel extends Gdn_Model {

    /** @var array Parameters. */
    protected $_Parameters = array();

    /** @var array SQL. */
    protected $_SearchSql = array();

    /** @var string Mode. */
    protected $_SearchMode = 'match';

    /** @var bool Whether to force the mode. */
    public $ForceSearchMode = '';

    /** @var string Search string. */
    protected $_SearchText = '';
	// Template for the ES query
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
								"highlighted" => true
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
     * @param $Sql
     */
    public function addSearch($Sql) {
        $this->_SearchSql[] = $Sql;
    }

    /** Add the sql to perform a search.
     *
     * @param Gdn_SQLDriver $Sql
     * @param string $Columns a comma seperated list of columns to search on.
     */
    public function addMatchSql($Sql, $Columns, $LikeRelavenceColumn = '') {
        if ($this->_SearchMode == 'like') {
            if ($LikeRelavenceColumn) {
                $Sql->select($LikeRelavenceColumn, '', 'Relavence');
            } else {
                $Sql->select(1, '', 'Relavence');
            }

            $Sql->beginWhereGroup();

            $ColumnsArray = explode(',', $Columns);

            $First = true;
            foreach ($ColumnsArray as $Column) {
                $Column = trim($Column);

                $Param = $this->Parameter();
                if ($First) {
                    $Sql->where("$Column like $Param", null, false, false);
                    $First = false;
                } else {
                    $Sql->orWhere("$Column like $Param", null, false, false);
                }
            }

            $Sql->endWhereGroup();
        } else {
            $Boolean = $this->_SearchMode == 'boolean' ? ' in boolean mode' : '';

            $Param = $this->Parameter();
            $Sql->select($Columns, "match(%s) against($Param{$Boolean})", 'Relavence');
            $Param = $this->Parameter();
            $Sql->where("match($Columns) against ($Param{$Boolean})", null, false, false);
        }
    }

    /**
     *
     *
     * @return string
     */
    public function parameter() {
        $Parameter = ':Search'.count($this->_Parameters);
        $this->_Parameters[$Parameter] = '';
        return $Parameter;
    }

    /**
     *
     */
    public function reset() {
        $this->_Parameters = array();
        $this->_SearchSql = '';
    }


	public function search($Search, $Offset = 0, $Limit = 20) {
        $builder = ClientBuilder::create();
        $builder->setHosts(['https://search-forums-test-vcbpec6ifhorjose2iofhr5dpe.us-east-1.es.amazonaws.com:443']);
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

        $data = $client->search(['index' => 'forum_index_v7', 'body' => $this->ES_QUERY]);
        $result = array();
        // Format received data in the same way as it was returned by SQL
        // to avoid changing views
        foreach ($data['hits']['hits'] as $hit) {
            $formattedEntry = array(
                //'Relavence' => '',
                'PrimaryID' => $hit['_source']['id'],
                'Title' => $hit['_source']['discussionName'],
                'Summary' => implode("...", $hit['highlight']['body']),
                'Format' => 'html',
                //'CategoryID' => '',
                //'Score' => null,
                'Url' => $hit['_source']['url'],
                'DateInserted' => $hit['_source']['date'],
                'UserID' => $hit['_source']['user'],
                'RecordType' => 'Comment'
            );
            array_push($result,$formattedEntry);
        }
        return $result;
    }

    /**
     *
     *
     * @param $Search
     * @param int $Offset
     * @param int $Limit
     * @return array|null
     * @throws Exception
     */
    public function search_old($Search, $Offset = 0, $Limit = 20) {
		echo "Search model";
        // If there are no searches then return an empty array.
        if (trim($Search) == '') {
            return array();
        }

        // Figure out the exact search mode.
        if ($this->ForceSearchMode) {
            $SearchMode = $this->ForceSearchMode;
        } else {
            $SearchMode = strtolower(c('Garden.Search.Mode', 'matchboolean'));
        }

        if ($SearchMode == 'matchboolean') {
            if (strpos($Search, '+') !== false || strpos($Search, '-') !== false) {
                $SearchMode = 'boolean';
            } else {
                $SearchMode = 'match';
            }
        } else {
            $this->_SearchMode = $SearchMode;
        }

        if ($ForceDatabaseEngine = c('Database.ForceStorageEngine')) {
            if (strcasecmp($ForceDatabaseEngine, 'myisam') != 0) {
                $SearchMode = 'like';
            }
        }

        if (strlen($Search) <= 4) {
            $SearchMode = 'like';
        }

        $this->_SearchMode = $SearchMode;

        $this->EventArguments['Search'] = $Search;
        $this->fireEvent('Search');

        if (count($this->_SearchSql) == 0) {
            return array();
        }

        // Perform the search by unioning all of the sql together.
        $Sql = $this->SQL
            ->select()
            ->from('_TBL_ s')
            ->orderBy('s.DateInserted', 'desc')
            ->limit($Limit, $Offset)
            ->GetSelect();

        $Sql = str_replace($this->Database->DatabasePrefix.'_TBL_', "(\n".implode("\nunion all\n", $this->_SearchSql)."\n)", $Sql);

        $this->fireEvent('AfterBuildSearchQuery');

        if ($this->_SearchMode == 'like') {
            $Search = '%'.$Search.'%';
        }

        foreach ($this->_Parameters as $Key => $Value) {
            $this->_Parameters[$Key] = $Search;
        }

        $Parameters = $this->_Parameters;
        $this->reset();
        $this->SQL->reset();
        $Result = $this->Database->query($Sql, $Parameters)->resultArray();

        foreach ($Result as $Key => $Value) {
            if (isset($Value['Summary'])) {
                $Value['Summary'] = Condense(Gdn_Format::to($Value['Summary'], $Value['Format']));
                $Result[$Key] = $Value;
            }

            switch ($Value['RecordType']) {
                case 'Discussion':
                    $Discussion = arrayTranslate($Value, array('PrimaryID' => 'DiscussionID', 'Title' => 'Name', 'CategoryID'));
                    $Result[$Key]['Url'] = DiscussionUrl($Discussion, 1);
                    break;
            }
        }

		
        return $Result;
    }

    /**
     *
     *
     * @param null $Value
     * @return null|string
     */
    public function searchMode($Value = null) {
        if ($Value !== null) {
            $this->_SearchMode = $Value;
        }
        return $this->_SearchMode;
    }
}
