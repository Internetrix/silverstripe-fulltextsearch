<?php
/**
 * every 5 minutes crontab should run 'php /rootpath/account/public_html/framework/cli-script.php dev/tasks/SolrReIndexCrontab' 
 * 
 * @author Jason Zhang
 */
class SolrReIndexCrontab extends BuildTask
{
	protected $title 		= 'Re-index solr if any page updated for crontab for solr 6';
	protected $description 	= 'If any page is updated in the recent 5 minutes (can be set in SolrReIndexCrontab.php), re-index solr for version 6.';
	
	/**
	 * must be 5 (minutes)
	 * @var integer
	 */
	protected $gap = 5;
	
	public function run($request) {

		increase_time_limit_to();
		
		$domain = Director::absoluteBaseURL();
		
		$classesToCheck = array(
// 			'File',
			'SiteTree'		
		);
		
		$doReindex 	= false;
		$msg		= 'No re-index required.';
		
		$gap1 = $this->gap;
		$gap2 = $this->gap * 2;
		
		$gapOneMinus = date('Y-m-d H:i:s', strtotime("-{$gap1} minutes"));	// 5  minutes ageo
		$gapTwoMinus = date('Y-m-d H:i:s', strtotime("-{$gap2} minutes"));	// 10 minutes ageo
		
		foreach ($classesToCheck as $ClassName){
			
			//get the recent updated page
			//records recently updated with 5 minutes
			$recentUpdatedDL = $ClassName::get()->where("\"LastEdited\" > '{$gapOneMinus}'")->sort('"LastEdited" DESC');
			
			//skip re-index if there is any page update within 5 minutes. 
			//Because the admin might update other pages.
			if($recentUpdatedDL && $recentUpdatedDL->Count()){
				continue;
			}
			
			//records recently updated with 10 minutes
			$recent2UpdatedDL = $ClassName::get()->where("\"LastEdited\" >= '{$gapTwoMinus}'")->sort('"LastEdited" DESC');
			
			//DO re-index if there is any page is updated between -10 minutes and -5 minutes
			if($recent2UpdatedDL && $recent2UpdatedDL->Count()){
				$doReindex = true;
			}
		}

		if($doReindex){
			$root_path = BASE_PATH;
			
			try {
				system("php {$root_path}/framework/cli-script.php dev/tasks/Solr_Reindex");
				
				//tmp notification msg.
// 				mail('logs@internetrix.com.au', $domain . ' solr re-index - done', 'solr re-index - done');
				$this->extend('afterSolrReindexSuccess', $domain);
				
				$msg = 'Solr has been re-indexed.';
			}		
			catch (Exception $e) {
// 				mail('errors@internetrix.com.au', $domain . ' SolrReIndexCrontab.php error', 'SolrReIndexCrontab.php error');
				$this->extend('afterSolrReindexFailed', $domain);
				
				$msg = 'system error';
			}
		}
		
		DB::alteration_message("Job Done. {$msg}");
	}
	
	

}
