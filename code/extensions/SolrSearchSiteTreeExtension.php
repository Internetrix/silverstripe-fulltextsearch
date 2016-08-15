<?php

class SolrSearchSiteTreeExtension extends DataExtension {
	
	private static $db = array(
		'SolrKeywords'	=> 'Varchar(255)'
	);
	
	public function updateCMSFields(FieldList $fields){
		
		$fields->addFieldsToTab(
			'Root.Main', 
			ToggleCompositeField::create('SolrSearch', 'Site Search Keywords', array(
				TextField::create('SolrKeywords', 'Keywords')
					->setRightTitle('Boost this page for the above keywords. Keywords must be separated by space.')	
			)),
			'Metadata'		
		);
		
	}

}
