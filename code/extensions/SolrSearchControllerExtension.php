<?php
/**
 * Extension to provide a search interface when applied to ContentController
 *
 * @package cms
 * @subpackage search
 */
class SolrSearchControllerExtension extends Extension {
	
	private static $allowed_actions = array(
		'SolrSearchForm',
		'solrresults',
	);

	/**
	 * Site search form
	 */
	public function SolrSearchForm() {
		$searchText =  _t('SearchForm.SEARCH', 'Search');

		if($this->owner->request && $this->owner->request->getVar('Search')) {
			$searchText = $this->owner->request->getVar('Search');
		}

		$fields = new FieldList(
			new TextField('Search', false, $searchText)
		);
		$actions = new FieldList(
			new FormAction('solrresults', _t('SearchForm.GO', 'Go'))
		);
		$form = new SolrSearchForm($this->owner, 'SolrSearchForm', $fields, $actions);
		$form->classesToSearch(FulltextSearchable::get_searchable_classes());
		return $form;
	}

	/**
	 * Process and render search results.
	 *
	 * @param array $data The raw request data submitted by user
	 * @param SearchForm $form The form instance that was submitted
	 * @param SS_HTTPRequest $request Request generated for this action
	 */
	public function solrresults($data, $form, $request) {
		$data = array(
			'Results' => $form->getSolrSearchResults(),
			'Query' => $form->getSearchQuery(),
			'Title' => _t('SearchForm.SearchResults', 'Search Results')
		);
		return $this->owner->customise($data)->renderWith(array('Page_results', 'Page'));
	}
}
