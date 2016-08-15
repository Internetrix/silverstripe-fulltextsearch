<?php
class SolrSearchForm extends SearchForm {
	
	public function getResults($pageLength = null, $data = null){
	
		return $this->getSolrSearchResults($pageLength, $data);
		
	}
	
	public function getSolrSearchResults($pageLength = null, $data = null){
		
		if(!isset($data) || !is_array($data)) $data = $_REQUEST;
		
		if(!$pageLength) $pageLength = $this->pageLength;
		$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
		
		$keywords = $data['Search'];
		
		$weightSetting = array();
		$weightSetting = array(
				
			'SiteTree_SolrKeywords' 					=> 2.0,
			'SiteTree_Title' 							=> 1.5,
			'SiteTree_Content' 							=> 1.0,
			
		);
		
		$this->extend('updateSolrSearchFields', $weightSetting);
		
		//escape double quote.
		$keywords = str_ireplace('"', '', $keywords);
		//add double quotes for each words.
		if(stripos($keywords, ' ') !== false){
			$keywordsArray = explode(' ', $keywords);
			$keywords = '"' . implode('" "', $keywordsArray) . '"';
		}else{
			$keywords = '"' . $keywords . '"';
		}

		$query = new SearchQuery();
		$query->start($start);
		$query->limit($pageLength);
		$query->search(
			$keywords,
			null,
			$weightSetting
		);
		
		// Needs a value, although it can be false
// 		$query->filter('Page_Title', SearchQuery::$present);
		$results = singleton('SS_Index')->search($query); 
		
		$paginatedMatches = $results->getField('Matches');
		
		return $paginatedMatches;
	}
	
}