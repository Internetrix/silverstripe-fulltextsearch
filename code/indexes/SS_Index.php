<?php
/**
 * Please using this format to name this class for each project.
 * 
 * SS_project-name_index
 * 
 * becuase all website's solr index should be in /tmp/solr-index/ folder
 * 
 * @author Jason Zhang, Yuchen Liu
 */
class SS_Index extends SolrIndex {
	function init() {
		
		$this->addClass('SiteTree');
		
		$this->addClass('SiteTree');
		$this->addFulltextField('SolrKeywords');
		$this->addFulltextField('Title');
		$this->addFulltextField('Content');
// 		$this->addAllFulltextFields();
		$this->addFilterField('ShowInSearch');
		
		$this->extend('updateSolrSearchIndex');
		
	}
	
	/**
	 * @return SolrService
	 */
	public function getService()
	{
		if (!$this->service) {
			$this->service = Solr::service(get_class($this));
		}
		return $this->service;
	}
	
	public function getTemplatesPath()
	{
		$globalOptions = Solr::solr_options();
		return $this->templatesPath ? $this->templatesPath : $globalOptions['templatespath'];
	}
	
	/**
	 * @return String Absolute path to the configuration default files,
	 * e.g. solrconfig.xml.
	 */
	public function getExtrasPath()
	{
		$globalOptions = Solr::solr_options();
		return $this->extrasPath ? $this->extrasPath : $globalOptions['extraspath'];
	}
	
	/**
	 * @param SearchQuery $query
	 * @param integer $offset
	 * @param integer $limit
	 * @param  Array $params Extra request parameters passed through to Solr
	 * @return ArrayData Map with the following keys:
	 *  - 'Matches': ArrayList of the matched object instances
	 */
	public function search(SearchQuery $query, $offset = -1, $limit = -1, $params = array()) {
		$service = $this->getService();
	
		SearchVariant::with(count($query->classes) == 1 ? $query->classes[0]['class'] : null)->call('alterQuery', $query, $this);
	
		$q = array();
		$fq = array();
	
		// Build the search itself
	
		foreach ($query->search as $search) {
			$text = $search['text'];
			preg_match_all('/"[^"]*"|\S+/', $text, $parts);
	
			$fuzzy = $search['fuzzy'] ? '~' : '';
	
			foreach ($parts[0] as $part) {
				$fields = (isset($search['fields'])) ? $search['fields'] : array();
				if(isset($search['boost'])) $fields = array_merge($fields, array_keys($search['boost']));
				if ($fields) {
					$searchq = array();
					foreach ($fields as $field) {
						$boost = (isset($search['boost'][$field])) ? '^' . $search['boost'][$field] : '';
						$searchq[] = "{$field}:".$part.$fuzzy.$boost;
					}
					$q[] = '+('.implode(' OR ', $searchq).')';
				}
				else {
					$q[] = '+'.$part.$fuzzy;
				}
			}
		}
	
		// Filter by class if requested
	
		$classq = array();
	
		foreach ($query->classes as $class) {
			if (!empty($class['includeSubclasses'])) $classq[] = 'ClassHierarchy:'.$class['class'];
			else $classq[] = 'ClassName:'.$class['class'];
		}
	
		if ($classq) $fq[] = '+('.implode(' ', $classq).')';
	
		// Filter by filters
	
		foreach ($query->require as $field => $values) {
			$requireq = array();
	
			foreach ($values as $value) {
				if ($value === SearchQuery::$missing) {
					$requireq[] = "(*:* -{$field}:[* TO *])";
				}
				else if ($value === SearchQuery::$present) {
					$requireq[] = "{$field}:[* TO *]";
				}
				else if ($value instanceof SearchQuery_Range) {
					$start = $value->start; if ($start === null) $start = '*';
					$end = $value->end; if ($end === null) $end = '*';
					$requireq[] = "$field:[$start TO $end]";
				}
				else {
					$requireq[] = $field.':"'.$value.'"';
				}
			}
	
			$fq[] = '+('.implode(' ', $requireq).')';
		}
	
		foreach ($query->exclude as $field => $values) {
			$excludeq = array();
			$missing = false;
	
			foreach ($values as $value) {
				if ($value === SearchQuery::$missing) {
					$missing = true;
				}
				else if ($value === SearchQuery::$present) {
					$excludeq[] = "{$field}:[* TO *]";
				}
				else if ($value instanceof SearchQuery_Range) {
					$start = $value->start; if ($start === null) $start = '*';
					$end = $value->end; if ($end === null) $end = '*';
					$excludeq[] = "$field:[$start TO $end]";
				}
				else {
					$excludeq[] = $field.':"'.$value.'"';
				}
			}
	
			$fq[] = ($missing ? "+{$field}:[* TO *] " : '') . '-('.implode(' ', $excludeq).')';
		}
	
		if(!headers_sent() && !Director::isLive()) {
			if ($q) header('X-Query: '.implode(' ', $q));
			if ($fq) header('X-Filters: "'.implode('", "', $fq).'"');
		}
	
		if ($offset == -1) $offset = $query->start;
		if ($limit == -1) $limit = $query->limit;
		if ($limit == -1) $limit = SearchQuery::$default_page_size;
	
		$params = array_merge($params, array('fq' => implode(' ', $fq)));
	
		$res = $service->search(
				$q ? implode(' ', $q) : '*:*',
				$offset,
				$limit,
				$params,
				Apache_Solr_Service::METHOD_POST
		);
	
		$results = new ArrayList();
		if($res->getHttpStatus() >= 200 && $res->getHttpStatus() < 300) {
			foreach ($res->response->docs as $doc) {
				$result = DataObject::get_by_id($doc->ClassName, $doc->ID);
				if($result) {
					$results->push($result);
	
					// Add highlighting (optional)
					$docId = $doc->_documentid;
					if($res->highlighting && $res->highlighting->$docId) {
						// TODO Create decorator class for search results rather than adding arbitrary object properties
						// TODO Allow specifying highlighted field, and lazy loading
						// in case the search API needs another query (similar to SphinxSearchable->buildExcerpt()).
						$combinedHighlights = array();
						foreach($res->highlighting->$docId as $field => $highlights) {
							$combinedHighlights = array_merge($combinedHighlights, $highlights);
						}
	
						// Remove entity-encoded U+FFFD replacement character. It signifies non-displayable characters,
						// and shows up as an encoding error in browsers.
						$result->Excerpt = DBField::create_field(
								'HTMLText',
								str_replace(
										'&#65533;',
										'',
										implode(' ... ', $combinedHighlights)
								)
						);
					}
				}
			}
			$numFound = $res->response->numFound;
		} else {
			$numFound = 0;
		}
	
		$ret = array();
		$ret['AllMatches'] = $results;
		$ret['NumFound'] = $numFound;
		$ret['Matches'] = new PaginatedList($results);
		$ret['Matches']->setLimitItems(false);
		// Tell PaginatedList how many results there are
		$ret['Matches']->setTotalItems($numFound);
		// Results for current page start at $offset
		$ret['Matches']->setPageStart($offset);
		// Results per page
		$ret['Matches']->setPageLength($limit);
	
		// Include spellcheck and suggestion data. Requires spellcheck=true in $params
		if(isset($res->spellcheck)) {
			// Expose all spellcheck data, for custom handling.
			$ret['Spellcheck'] = $res->spellcheck;
	
			// Suggestions. Requires spellcheck.collate=true in $params
			if(isset($res->spellcheck->suggestions->collation)) {
				// The collation, including advanced query params (e.g. +), suitable for making another query programmatically.
				$ret['Suggestion'] = $res->spellcheck->suggestions->collation;
	
				// A human friendly version of the suggestion, suitable for 'Did you mean $SuggestionNice?' display.
				$ret['SuggestionNice'] = $this->getNiceSuggestion($res->spellcheck->suggestions->collation);
	
				// A string suitable for appending to an href as a query string.
				// For example <a href="http://example.com/search?q=$SuggestionQueryString">$SuggestionNice</a>
				$ret['SuggestionQueryString'] = $this->getSuggestionQueryString($res->spellcheck->suggestions->collation);
			}
		}
	
		return new ArrayData($ret);
	}
}