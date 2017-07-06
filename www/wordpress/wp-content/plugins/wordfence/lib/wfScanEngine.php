<?php
require_once('wordfenceClass.php');
require_once('wordfenceHash.php');
require_once('wfAPI.php');
require_once('wordfenceScanner.php');
require_once('wfIssues.php');
require_once('wfDB.php');
require_once('wfUtils.php');
class wfScanEngine {
	public $api = false;
	private $dictWords = array();
	private $forkRequested = false;

	//Beginning of serialized properties on sleep
	private $hasher = false;
	private $jobList = array();
	private $i = false;
	private $wp_version = false;
	private $apiKey = false;
	private $startTime = 0;
	public $maxExecTime = false; //If more than $maxExecTime has elapsed since last check, fork a new scan process and continue
	private $publicScanEnabled = false;
	private $fileContentsResults = false;
	/**
	 * @var bool|wordfenceScanner
	 */
	private $scanner = false;
	private $scanQueue = array();
	/**
	 * @var bool|wordfenceURLHoover
	 */
	private $hoover = false;
	private $scanData = array();
	private $statusIDX = array(
			'core' => false,
			'plugin' => false,
			'theme' => false,
			'unknown' => false
			);
	private $userPasswdQueue = "";
	private $passwdHasIssues = wfIssues::STATUS_SECURE;
	private $suspectedFiles = false; //Files found with the ".suspected" extension
	private $gsbMultisiteBlogOffset = 0;
	private $updateCheck = false;
	private $pluginRepoStatus = array();

	/**
	 * @var wordfenceDBScanner
	 */
	private $dbScanner;

	/**
	 * @var wfScanKnownFilesLoader
	 */
	private $knownFilesLoader;
	
	private $metrics = array();
	
	private $checkHowGetIPsRequestTime = 0;

	public static function testForFullPathDisclosure($url = null, $filePath = null) {
		if ($url === null && $filePath === null) {
			$url = includes_url('rss-functions.php');
			$filePath = ABSPATH . WPINC . '/rss-functions.php';
		}

		$response = wp_remote_get($url);
		$html = wp_remote_retrieve_body($response);
		return preg_match("/" . preg_quote(realpath($filePath), "/") . "/i", $html);
	}

	public static function isDirectoryListingEnabled($url = null) {
		if ($url === null) {
			$uploadPaths = wp_upload_dir();
			$url = $uploadPaths['baseurl'];
		}

		$response = wp_remote_get($url);
		return !is_wp_error($response) && ($responseBody = wp_remote_retrieve_body($response)) &&
			stripos($responseBody, '<title>Index of') !== false;
	}
	
	public static function refreshScanNotification($issuesInstance = null) {
		if ($issuesInstance === null) {
			$issuesInstance = new wfIssues();
		}
		
		$message = wfConfig::get('lastScanCompleted', false);
		if ($message === false || empty($message)) {
			$n = wfNotification::getNotificationForCategory('wfplugin_scan');
			if ($n !== null) {
				$n->markAsRead();
			}
		}
		else if ($message == 'ok') {
			$issueCount = $issuesInstance->getIssueCount();
			if ($issueCount) {
				new wfNotification(null, wfNotification::PRIORITY_HIGH_WARNING, "<a href=\"" . network_admin_url('admin.php?page=WordfenceScan') . "\">{$issueCount} issue" . ($issueCount == 1 ? '' : 's') . ' found in most recent scan</a>', 'wfplugin_scan');
			}
			else {
				$n = wfNotification::getNotificationForCategory('wfplugin_scan');
				if ($n !== null) {
					$n->markAsRead();
				}
			}
		}
		else {
			$failureType = wfConfig::get('lastScanFailureType');
			if ($failureType == 'duration') {
				new wfNotification(null, wfNotification::PRIORITY_HIGH_WARNING, '<a href="' . network_admin_url('admin.php?page=WordfenceScan') . '">Scan aborted due to duration limit</a>', 'wfplugin_scan');
			}
			else if ($failureType == 'versionchange') {
				//No need to create a notification
			}
			else {
				$trimmedError = substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '');
				new wfNotification(null, wfNotification::PRIORITY_HIGH_WARNING, '<a href="' . network_admin_url('admin.php?page=WordfenceScan') . '">Scan failed: ' . esc_html($trimmedError) . '</a>', 'wfplugin_scan');
			}
		}
	}

	public function __sleep(){ //Same order here as above for properties that are included in serialization
		return array('hasher', 'jobList', 'i', 'wp_version', 'apiKey', 'startTime', 'maxExecTime', 'publicScanEnabled', 'fileContentsResults', 'scanner', 'scanQueue', 'hoover', 'scanData', 'statusIDX', 'userPasswdQueue', 'passwdHasIssues', 'suspectedFiles', 'dbScanner', 'knownFilesLoader', 'metrics', 'checkHowGetIPsRequestTime', 'gsbMultisiteBlogOffset', 'updateCheck', 'pluginRepoStatus');
	}
	public function __construct(){
		$this->startTime = time();
		$this->recordMetric('scan', 'start', $this->startTime);
		$this->maxExecTime = self::getMaxExecutionTime();
		$this->i = new wfIssues();
		$this->cycleStartTime = time();
		$this->wp_version = wfUtils::getWPVersion();
		$this->apiKey = wfConfig::get('apiKey');
		$this->api = new wfAPI($this->apiKey, $this->wp_version);
		include('wfDict.php'); //$dictWords
		$this->dictWords = $dictWords;
		$this->jobList[] = 'checkSpamvertized';
		$this->jobList[] = 'checkSpamIP';
		$this->jobList[] = 'checkGSB_init';
		$this->jobList[] = 'checkGSB_main';
		$this->jobList[] = 'checkGSB_finish';
		$this->jobList[] = 'checkHowGetIPs_init';
		$this->jobList[] = 'checkHowGetIPs_main';
		$this->jobList[] = 'knownFiles_init';
		$this->jobList[] = 'knownFiles_main';
		$this->jobList[] = 'knownFiles_finish';
		foreach (array('knownFiles', 'checkReadableConfig', 'fileContents', 'suspectedFiles',
			         // 'wpscan_fullPathDisclosure', 'wpscan_directoryListingEnabled',
			         'posts', 'comments', 'passwds', 'dns', 'diskSpace', 'oldVersions', 'suspiciousAdminUsers') as $scanType) {
			if (wfConfig::get('scansEnabled_' . $scanType)) {
				if (method_exists($this, 'scan_' . $scanType . '_init')) {
					foreach (array('init', 'main', 'finish') as $op) {
						$this->jobList[] = $scanType . '_' . $op;
					};
				} else if (method_exists($this, 'scan_' . $scanType)) {
					$this->jobList[] = $scanType;
				}
			}
		}
	}
	public function deleteNewIssues(){
		$this->i->deleteNew();
	}
	public function __wakeup(){
		$this->cycleStartTime = time();
		$this->api = new wfAPI($this->apiKey, $this->wp_version);
		include('wfDict.php'); //$dictWords
		$this->dictWords = $dictWords;
	}
	public function go(){
		try {
			self::checkForKill();
			$this->doScan();
			wfConfig::set('lastScanCompleted', 'ok');
			wfConfig::set('lastScanFailureType', false);
			self::checkForKill();
			//updating this scan ID will trigger the scan page to load/reload the results.
			$this->i->setScanTimeNow();
			//scan ID only incremented at end of scan to make UI load new results
			$this->emailNewIssues();
			$this->recordMetric('scan', 'duration', (time() - $this->startTime));
			$this->recordMetric('scan', 'memory', wfConfig::get('wfPeakMemory', 0, false));
			$this->submitMetrics();
			
			wfScanEngine::refreshScanNotification($this->i);
		}
		catch (wfScanEngineDurationLimitException $e) {
			wfConfig::set('lastScanCompleted', $e->getMessage());
			wfConfig::set('lastScanFailureType', 'duration');
			$this->i->setScanTimeNow();
			
			$this->emailNewIssues(true);
			$this->recordMetric('scan', 'duration', (time() - $this->startTime));
			$this->recordMetric('scan', 'memory', wfConfig::get('wfPeakMemory', 0, false));
			$this->submitMetrics();
			
			wfScanEngine::refreshScanNotification($this->i);
			throw $e;
		}
		catch (wfScanEngineCoreVersionChangeException $e) {
			wfConfig::set('lastScanCompleted', $e->getMessage());
			wfConfig::set('lastScanFailureType', 'versionchange');
			$this->i->setScanTimeNow();
			
			$this->recordMetric('scan', 'duration', (time() - $this->startTime));
			$this->recordMetric('scan', 'memory', wfConfig::get('wfPeakMemory', 0, false));
			$this->submitMetrics();
			
			wfScanEngine::refreshScanNotification($this->i);
			throw $e;
		}
		catch(Exception $e) {
			wfConfig::set('lastScanCompleted', $e->getMessage());
			wfConfig::set('lastScanFailureType', 'general');
			$this->recordMetric('scan', 'duration', (time() - $this->startTime));
			$this->recordMetric('scan', 'memory', wfConfig::get('wfPeakMemory', 0, false));
			$this->recordMetric('scan', 'failure', $e->getMessage());
			$this->submitMetrics();
			
			wfScanEngine::refreshScanNotification($this->i);
			throw $e;
		}
	}
	public function checkForDurationLimit() {
		static $timeLimit = false;
		if ($timeLimit === false) {
			$timeLimit = intval(wfConfig::get('scan_maxDuration'));
			if ($timeLimit < 1) {
				$timeLimit = WORDFENCE_DEFAULT_MAX_SCAN_TIME;
			}
		}
		
		if ((time() - $this->startTime) > $timeLimit){
			$error = 'The scan time limit of ' . wfUtils::makeDuration($timeLimit) . ' has been exceeded and the scan will be terminated. This limit can be customized on the options page. <a href="http://docs.wordfence.com/en/Scan_time_limit" target="_blank" rel="noopener noreferrer">Get More Information</a>';
			$this->addIssue('timelimit', 1, md5($this->startTime), md5($this->startTime), 'Scan Time Limit Exceeded', $error, array());
			$summary = $this->i->getSummaryItems();
			$this->status(1, 'info', '-------------------');
			$this->status(1, 'info', "Scan interrupted. Scanned " . $summary['totalFiles'] . " files, " . $summary['totalPlugins'] . " plugins, " . $summary['totalThemes'] . " themes, " . ($summary['totalPages'] + $summary['totalPosts']) . " pages, " . $summary['totalComments'] . " comments and " . $summary['totalRows'] . " records in " . wfUtils::makeDuration(time() - $this->startTime, true) . ".");
			if($this->i->totalIssues  > 0){
				$this->status(10, 'info', "SUM_FINAL:Scan interrupted. You have " . $this->i->totalIssues . " new issue" . ($this->i->totalIssues == 1 ? "" : "s") . " to fix. See below.");
			} else {
				$this->status(10, 'info', "SUM_FINAL:Scan interrupted. No problems found prior to stopping.");
			}
			throw new wfScanEngineDurationLimitException($error);
		}
	}
	public function checkForCoreVersionChange() {
		$startVersion = wfConfig::get('wfScanStartVersion');
		$currentVersion = wfUtils::getWPVersion(true);
		if (version_compare($startVersion, $currentVersion) != 0) {
			throw new wfScanEngineCoreVersionChangeException("Aborting scan because WordPress updated from version {$startVersion} to {$currentVersion}. The scan will be reattempted later.");
		}
	}
	public function shouldFork() {
		static $lastCheck = 0;
		
		if (time() - $this->cycleStartTime > $this->maxExecTime) {
			return true;
		}
		
		if ($lastCheck > time() - $this->maxExecTime) {
			return false;
		}
		$lastCheck = time();
		
		$this->checkForCoreVersionChange();
		wfIssues::updateScanStillRunning();
		self::checkForKill();
		$this->checkForDurationLimit();
		
		return false;
	}
	public function forkIfNeeded(){
		wfIssues::updateScanStillRunning();
		$this->checkForCoreVersionChange();
		self::checkForKill();
		$this->checkForDurationLimit();
		if(time() - $this->cycleStartTime > $this->maxExecTime){
			wordfence::status(4, 'info', "Forking during hash scan to ensure continuity.");
			$this->fork();
		}
	}
	public function fork(){
		wordfence::status(4, 'info', "Entered fork()");
		if(wfConfig::set_ser('wfsd_engine', $this, true, wfConfig::DONT_AUTOLOAD)){
			wordfence::status(4, 'info', "Calling startScan(true)");
			self::startScan(true);
		} //Otherwise there was an error so don't start another scan.
		exit(0);
	}
	public function emailNewIssues($timeLimitReached = false){
		$this->i->emailNewIssues($timeLimitReached);
	}
	public function submitMetrics() {
		if (wfConfig::get('other_WFNet', true)) {
			$this->api->call('record_scan_metrics', array(), array('metrics' => $this->metrics));
		}
	}
	private function doScan(){
		if (wfConfig::get('lowResourceScansEnabled')) {
			$isFork = ($_GET['isFork'] == '1' ? true : false);
			wfConfig::set('lowResourceScanWaitStep', !wfConfig::get('lowResourceScanWaitStep'));
			if ($isFork && wfConfig::get('lowResourceScanWaitStep')) {
				sleep($this->maxExecTime / 2);
				$this->fork(); //exits
			}
		}
		
		while(sizeof($this->jobList) > 0){
			self::checkForKill();
			$jobName = $this->jobList[0];
			$callback = array($this, 'scan_' . $jobName);
			if (is_callable($callback)) {
				call_user_func($callback);
			}
			array_shift($this->jobList); //only shift once we're done because we may pause halfway through a job and need to pick up where we left off
			self::checkForKill();
			if($this->forkRequested){
				$this->fork();
			} else {
				$this->forkIfNeeded();  
			}
		}
		$summary = $this->i->getSummaryItems();
		$this->status(1, 'info', '-------------------');
		$this->status(1, 'info', "Scan Complete. Scanned " . $summary['totalFiles'] . " files, " . $summary['totalPlugins'] . " plugins, " . $summary['totalThemes'] . " themes, " . ($summary['totalPages'] + $summary['totalPosts']) . " pages, " . $summary['totalComments'] . " comments and " . $summary['totalRows'] . " records in " . wfUtils::makeDuration(time() - $this->startTime, true) . ".");
		
		$ignoredText = '';
		if ($this->i->totalIgnoredIssues > 0) {
			$ignoredText = ' ' . $this->i->totalIgnoredIssues . ' ignored issue' . ($this->i->totalIgnoredIssues == 1 ? ' was' : 's were') . ' also detected.';
		}
		
		if($this->i->totalIssues  > 0){
			$this->status(10, 'info', "SUM_FINAL:Scan complete. You have " . $this->i->totalIssues . " new issue" . ($this->i->totalIssues == 1 ? "" : "s") . " to fix.{$ignoredText} See below.");
		} else {
			$this->status(10, 'info', "SUM_FINAL:Scan complete. Congratulations, no new problems found.{$ignoredText}");
		}
		return;
	}
	public function getCurrentJob(){
		return $this->jobList[0];
	}
	private function scan_checkSpamIP(){
		if(wfConfig::get('isPaid')){
			if(wfConfig::get('checkSpamIP')){
				$this->statusIDX['checkSpamIP'] = wfIssues::statusStart("Checking if your site IP is generating spam");
				$result = $this->api->call('check_spam_ip', array(), array(
					'siteURL' => site_url()
					));
				$haveIssues = wfIssues::STATUS_SECURE;
				if(!empty($result['haveIssues']) && is_array($result['issues']) ){
					foreach($result['issues'] as $issue){
						$added = $this->addIssue($issue['type'], $issue['level'], $issue['ignoreP'], $issue['ignoreC'], $issue['shortMsg'], $issue['longMsg'], $issue['data']);
						if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
						else if ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC) { $haveIssues = wfIssues::STATUS_IGNORED; }
					}
				}
				wfIssues::statusEnd($this->statusIDX['checkSpamIP'], $haveIssues);
			} else {
				wfIssues::statusDisabled("Skipping check if your IP is generating spam");
			}

		} else {
			wfIssues::statusPaidOnly("Checking if your IP is generating spam is for paid members only");
			sleep(2);
		}
	}
	
	private function scan_checkGSB_init() {
		if (wfConfig::get('isPaid')) {
			$this->statusIDX['checkGSB'] = wfIssues::statusStart("Checking if your site is on a domain blacklist");
			$h = new wordfenceURLHoover($this->apiKey, $this->wp_version);
			$h->cleanup();
		}
	}
	
	private function scan_checkGSB_main() {
		if (wfConfig::get('isPaid')) {
			if (is_multisite()) {
				global $wpdb;
				$h = new wordfenceURLHoover($this->apiKey, $this->wp_version, false, true);
				$blogIDs = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM {$wpdb->blogs} WHERE blog_id > %d ORDER BY blog_id ASC", $this->gsbMultisiteBlogOffset)); //Can't use wp_get_sites or get_sites because they return empty at 10k sites
				foreach ($blogIDs as $id) {
					$homeURL = get_home_url($id);
					$h->hoover($id, $homeURL);
					$siteURL = get_site_url($id);
					if ($homeURL != $siteURL) {
						$h->hoover($id, $siteURL);
					}
					
					if ($this->shouldFork()) {
						$this->gsbMultisiteBlogOffset = $id;
						$this->forkIfNeeded();
					}
				}
			}
		}
	}
	
	private function scan_checkGSB_finish() {
		if (wfConfig::get('isPaid')) {
			if (is_multisite()) {
				$h = new wordfenceURLHoover($this->apiKey, $this->wp_version, false, true);
				$badURLs = $h->getBaddies();
				if ($h->errorMsg) {
					$this->status(4, 'info', "Error checking domain blacklists: " . $h->errorMsg);
					wfIssues::statusEnd($this->statusIDX['checkGSB'], wfIssues::STATUS_FAILED);
					return;
				}
				$h->cleanup();
			}
			else {
				$urlsToCheck = array(array(wfUtils::wpHomeURL(), wfUtils::wpSiteURL()));
				$badURLs = $this->api->call('check_bad_urls', array(), array('toCheck' => json_encode($urlsToCheck))); //Skipping the separate prefix check since there are just two URLs
				$finalResults = array();
				foreach ($badURLs as $file => $badSiteList) {
					if (!isset($finalResults[$file])) {
						$finalResults[$file] = array();
					}
					foreach ($badSiteList as $badSite) {
						$finalResults[$file][] = array(
							'URL' => $badSite[0],
							'badList' => $badSite[1]
						);
					}
				}
				$badURLs = $finalResults;
			}
			
			$haveIssues = wfIssues::STATUS_SECURE;
			if (is_array($badURLs) && count($badURLs) > 0) {
				foreach ($badURLs as $id => $badSiteList) {
					foreach ($badSiteList as $badSite) {
						$url = $badSite['URL'];
						$badList = $badSite['badList'];
						$data = array('badURL' => $url);
						
						if ($badList == 'goog-malware-shavar') {
							if (is_multisite()) {
								$shortMsg = 'The multisite blog with ID ' . intval($id) . ' is listed on Google\'s Safe Browsing malware list.';
								$data['multisite'] = intval($id);
							}
							else {
								$shortMsg = 'Your site is listed on Google\'s Safe Browsing malware list.';
							}
							$longMsg = "The URL " . esc_html($url) . " is on the malware list. More info available at <a href=\"http://safebrowsing.clients.google.com/safebrowsing/diagnostic?site=" . urlencode($url) . "&client=googlechrome&hl=en-US\" target=\"_blank\" rel=\"noopener noreferrer\">Google Safe Browsing diagnostic page</a>.";
							$data['gsb'] = $badList;
						}
						else if ($badList == 'googpub-phish-shavar') {
							if (is_multisite()) {
								$shortMsg = 'The multisite blog with ID ' . intval($id) . ' is listed on Google\'s Safe Browsing phishing list.';
								$data['multisite'] = intval($id);
							}
							else {
								$shortMsg = 'Your site is listed on Google\'s Safe Browsing phishing list.';
							}
							$longMsg = "The URL " . esc_html($url) . " is on the phishing list. More info available at <a href=\"http://safebrowsing.clients.google.com/safebrowsing/diagnostic?site=" . urlencode($url) . "&client=googlechrome&hl=en-US\" target=\"_blank\" rel=\"noopener noreferrer\">Google Safe Browsing diagnostic page</a>.";
							$data['gsb'] = $badList;
						}
						else if ($badList == 'wordfence-dbl') {
							if (is_multisite()) {
								$shortMsg = 'The multisite blog with ID ' . intval($id) . ' is listed on the Wordfence domain blacklist.';
								$data['multisite'] = intval($id);
							}
							else {
								$shortMsg = 'Your site is listed on the Wordfence domain blacklist.';
							}
							$longMsg = "The URL " . esc_html($url) . " is on the blacklist.";
							$data['gsb'] = $badList;
						}
						else {
							if (is_multisite()) {
								$shortMsg = 'The multisite blog with ID ' . intval($id) . ' is listed on a domain blacklist.';
								$data['multisite'] = intval($id);
							}
							else {
								$shortMsg = 'Your site is listed on a domain blacklist.';
							}
							$longMsg = "The URL is: " . esc_html($url);
							$data['gsb'] = 'unknown';
						}
						
						$added = $this->addIssue('checkGSB', 1, 'checkGSB', 'checkGSB' . $url, $shortMsg, $longMsg, $data);
						if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
						else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
					}
				}
			}
			
			wfIssues::statusEnd($this->statusIDX['checkGSB'], $haveIssues);
		} else {
			wfIssues::statusPaidOnly("Checking if your site is on a domain blacklist is for paid members only");
			sleep(2);
		}
	}
	
	private function scan_checkHowGetIPs_init() {
		if (wfConfig::get('scansEnabled_checkHowGetIPs')) {
			$this->statusIDX['checkHowGetIPs'] = wfIssues::statusStart("Checking for the most secure way to get IPs");
			$this->checkHowGetIPsRequestTime = time();
			wfUtils::requestDetectProxyCallback();
		}
		else {
			wfIssues::statusDisabled("Skipping scan for misconfigured How does Wordfence get IPs");
		}
	}
	
	private function scan_checkHowGetIPs_main() {
		if (!defined('WORDFENCE_CHECKHOWGETIPS_TIMEOUT')) { define('WORDFENCE_CHECKHOWGETIPS_TIMEOUT', 30); }
		
		if (wfConfig::get('scansEnabled_checkHowGetIPs')) {
			$haveIssues = wfIssues::STATUS_SECURE;
			$existing = wfConfig::get('howGetIPs', '');
			$recommendation = wfConfig::get('detectProxyRecommendation', '');
			while (empty($recommendation) && (time() - $this->checkHowGetIPsRequestTime) < WORDFENCE_CHECKHOWGETIPS_TIMEOUT) {
				sleep(1);
				$this->forkIfNeeded();
				$recommendation = wfConfig::get('detectProxyRecommendation', '');
			}
			
			if ($recommendation == 'DEFERRED') { 
				//Do nothing
				$haveIssues = wfIssues::STATUS_SKIPPED;
			}
			else if (empty($recommendation)) {
				$haveIssues = wfIssues::STATUS_FAILED;
			}
			else if ($recommendation == 'UNKNOWN') {
				$added = $this->addIssue('checkHowGetIPs', 2, 'checkHowGetIPs', 'checkHowGetIPs' . $recommendation . WORDFENCE_VERSION, "Unable to accurately detect IPs", 'Wordfence was unable to validate a test request to your website. This can happen if your website is behind a proxy that does not use one of the standard ways to convey the IP of the request or it is unreachable publicly. IP blocking and live traffic information may not be accurate. <a href="https://docs.wordfence.com/en/Misconfigured_how_get_IPs_notice " target="_blank" rel="noopener noreferrer">Get More Information</a>', array());
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
				else if ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC) { $haveIssues = wfIssues::STATUS_IGNORED; }
			}
			else if (!empty($existing) && $existing != $recommendation) {
				$extraMsg = '';
				if ($recommendation == 'REMOTE_ADDR') {
					$extraMsg = ' For maximum security use PHP\'s built in REMOTE_ADDR.';
				}
				else if ($recommendation == 'HTTP_X_FORWARDED_FOR') {
					$extraMsg = ' This site appears to be behind a front-end proxy, so using the X-Forwarded-For HTTP header will resolve to the correct IPs.';
				}
				else if ($recommendation == 'HTTP_X_REAL_IP') {
					$extraMsg = ' This site appears to be behind a front-end proxy, so using the X-Real-IP HTTP header will resolve to the correct IPs.';
				}
				else if ($recommendation == 'HTTP_CF_CONNECTING_IP') {
					$extraMsg = ' This site appears to be behind Cloudflare, so using the Cloudflare "CF-Connecting-IP" HTTP header will resolve to the correct IPs.';
				}
				
				$added = $this->addIssue('checkHowGetIPs', 2, 'checkHowGetIPs', 'checkHowGetIPs' . $recommendation . WORDFENCE_VERSION, "'How does Wordfence get IPs' is misconfigured", 'A test request to this website was detected on a different value for this setting. IP blocking and live traffic information may not be accurate. <a href="https://docs.wordfence.com/en/Misconfigured_how_get_IPs_notice " target="_blank" rel="noopener noreferrer">Get More Information</a>' . $extraMsg, array('recommendation' => $recommendation));
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
				else if ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC) { $haveIssues = wfIssues::STATUS_IGNORED; }
			}
			
			wfIssues::statusEnd($this->statusIDX['checkHowGetIPs'], $haveIssues);
		}
	}

	private function scan_checkReadableConfig() {
		$haveIssues = wfIssues::STATUS_SECURE;
		$status = wfIssues::statusStart("Check for publicly accessible configuration files, backup files and logs");

		$backupFileTests = array(
//			wfCommonBackupFileTest::createFromRootPath('.user.ini'),
//			wfCommonBackupFileTest::createFromRootPath('.htaccess'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.php.bak'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.php.swo'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.php.save'),
			new wfCommonBackupFileTest(home_url('%23wp-config.php%23'), ABSPATH . '#wp-config.php#'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.php~'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.old'),
			wfCommonBackupFileTest::createFromRootPath('.wp-config.php.swp'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.bak'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.save'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.php_bak'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.php.swp'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.php.old'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.php.original'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.php.orig'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.txt'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.original'),
			wfCommonBackupFileTest::createFromRootPath('wp-config.orig'),
			wfCommonBackupFileTest::createFromRootPath('searchreplacedb2.php'),
			new wfCommonBackupFileTest(content_url('/debug.log'), WP_CONTENT_DIR . '/debug.log', array(
				'headers' => array(
					'Range' => 'bytes=0-700',
				),
			)),
		);
//		$userIniFilename = ini_get('user_ini.filename');
//		if ($userIniFilename && $userIniFilename !== '.user.ini') {
//			$backupFileTests[] = wfCommonBackupFileTest::createFromRootPath($userIniFilename);
//		}


		/** @var wfCommonBackupFileTest $test */
		foreach ($backupFileTests as $test) {
			$pathFromRoot = (strpos($test->getPath(), ABSPATH) === 0) ? substr($test->getPath(), strlen(ABSPATH)) : $test->getPath();
			if ($test->fileExists() && $test->isPubliclyAccessible()) {
				$key = "configReadable" . bin2hex($test->getUrl());
				$added = $this->addIssue(
					'configReadable',
					2,
					$key,
					$key,
					'Publicly accessible config, backup, or log file found: ' . esc_html($pathFromRoot),
					'<a href="' . $test->getUrl() . '" target="_blank" rel="noopener noreferrer">' . $test->getUrl() . '</a> is publicly
					accessible and may expose sensitive information about your site. Files such as this one are commonly
					checked for by scanners such as WPScan and should be removed or made inaccessible.',
					array(
						'url'       => $test->getUrl(),
						'file'      => $pathFromRoot,
						'canDelete' => true,
					)
				);
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
				else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
			}
		}

		wfIssues::statusEnd($status, $haveIssues);
	}

	private function scan_wpscan_fullPathDisclosure() {
		$file = realpath(ABSPATH . WPINC . "/rss-functions.php");
		if (!$file) {
			return;
		}

		$haveIssues = wfIssues::STATUS_SECURE;
		$status = wfIssues::statusStart("Checking if your server discloses the path to the document root");
		$testPage = includes_url() . basename($file);

		if (self::testForFullPathDisclosure($testPage, $file)) {
			$key  = 'wpscan_fullPathDisclosure' . $testPage;
			$added = $this->addIssue(
				'wpscan_fullPathDisclosure',
				2,
				$key,
				$key,
				'Web server exposes the document root',
				'Full Path Disclosure (FPD) vulnerabilities enable the attacker to see the path to the webroot/file. e.g.:
				 /home/user/htdocs/file/. Certain vulnerabilities, such as using the load_file() (within a SQL Injection)
				 query to view the page source, require the attacker to have the full path to the file they wish to view.',
				array('url' => $testPage)
			);
			if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
			else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
		}

		wfIssues::statusEnd($status, $haveIssues);
	}

	private function scan_wpscan_directoryListingEnabled() {
		$this->statusIDX['wpscan_directoryListingEnabled'] = wfIssues::statusStart("Checking to see if directory listing is enabled");

		$uploadPaths = wp_upload_dir();
		$enabled = self::isDirectoryListingEnabled($uploadPaths['baseurl']);

		$haveIssues = wfIssues::STATUS_SECURE;
		if ($enabled) {
			$added = $this->addIssue(
				'wpscan_directoryListingEnabled',
				2,
				'wpscan_directoryListingEnabled',
				'wpscan_directoryListingEnabled',
				"Directory listing is enabled",
				"Directory listing provides an attacker with the complete index of all the resources located inside of the directory. The specific risks and consequences vary depending on which files are listed and accessible, but it is recommended that you disable it unless it is needed.",
				array(
					'url' => $uploadPaths['baseurl'],
				)
			);
			if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
			else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
		}
		wfIssues::statusEnd($this->statusIDX['wpscan_directoryListingEnabled'], $haveIssues);
	}

	private function scan_checkSpamvertized(){
		if(wfConfig::get('isPaid')){
			if(wfConfig::get('spamvertizeCheck')){
				$this->statusIDX['spamvertizeCheck'] = wfIssues::statusStart("Checking if your site is being Spamvertised");
				$result = $this->api->call('spamvertize_check', array(), array(
					'siteURL' => site_url()
					));
				$haveIssues = wfIssues::STATUS_SECURE;
				if($result['haveIssues'] && is_array($result['issues']) ){
					foreach($result['issues'] as $issue){
						$added = $this->addIssue($issue['type'], $issue['level'], $issue['ignoreP'], $issue['ignoreC'], $issue['shortMsg'], $issue['longMsg'], $issue['data']);
						if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
						else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
					}
				}
				wfIssues::statusEnd($this->statusIDX['spamvertizeCheck'], $haveIssues);
			} else {
				wfIssues::statusDisabled("Skipping check if your site is being spamvertized");
			}

		} else {
			wfIssues::statusPaidOnly("Check if your site is being Spamvertized is for paid members only");
			sleep(2);
		}
	}
	private function scan_knownFiles_init(){
		$this->status(1, 'info', "Contacting Wordfence to initiate scan");
		$response = $this->api->call('log_scan', array(), array());
		$baseWPStuff = array( '.htaccess', 'index.php', 'license.txt', 'readme.html', 'wp-activate.php', 'wp-admin', 'wp-app.php', 'wp-blog-header.php', 'wp-comments-post.php', 'wp-config-sample.php', 'wp-content', 'wp-cron.php', 'wp-includes', 'wp-links-opml.php', 'wp-load.php', 'wp-login.php', 'wp-mail.php', 'wp-pass.php', 'wp-register.php', 'wp-settings.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php');
		$baseContents = scandir(ABSPATH);
		if(! is_array($baseContents)){
			throw new Exception("Wordfence could not read the contents of your base WordPress directory. This usually indicates your permissions are so strict that your web server can't read your WordPress directory.");
		}
		
		$includeInKnownFilesScan = array();
		$scanOutside = wfConfig::get('other_scanOutside');
		if ($scanOutside) {
			wordfence::status(2, 'info', "Including files that are outside the WordPress installation in the scan.");
			$includeInKnownFilesScan[] = ''; //Ends up as a literal ABSPATH
		}
		else {
			foreach ($baseContents as $file) { //Only include base files less than a meg that are files.
				if($file == '.' || $file == '..'){ continue; }
				$fullFile = rtrim(ABSPATH, '/') . '/' . $file;
				if (in_array($file, $baseWPStuff) || (@is_file($fullFile) && @is_readable($fullFile) && (!wfUtils::fileTooBig($fullFile)))) {
					$includeInKnownFilesScan[] = $file;
				}
			}
		}

		$this->status(2, 'info', "Getting plugin list from WordPress");
		$knownFilesPlugins = $this->getPlugins();
		$this->status(2, 'info', "Found " . sizeof($knownFilesPlugins) . " plugins");
		$this->i->updateSummaryItem('totalPlugins', sizeof($knownFilesPlugins));

		$this->status(2, 'info', "Getting theme list from WordPress");
		$knownFilesThemes = $this->getThemes();
		$this->status(2, 'info', "Found " . sizeof($knownFilesThemes) . " themes");
		$this->i->updateSummaryItem('totalThemes', sizeof($knownFilesThemes));

		$malwarePrefixesHash = (isset($response['malwarePrefixes']) ? wfUtils::hex2bin($response['malwarePrefixes']) : '');
		$this->hasher = new wordfenceHash(strlen(ABSPATH), ABSPATH, $includeInKnownFilesScan, $knownFilesThemes, $knownFilesPlugins, $this, $malwarePrefixesHash);
	}
	private function scan_knownFiles_main(){
		$this->hasher->run($this); //Include this so we can call addIssue and ->api->
		$this->i->updateSummaryItem('totalData', wfUtils::formatBytes($this->hasher->totalData));
		$this->i->updateSummaryItem('totalFiles', $this->hasher->totalFiles);
		$this->i->updateSummaryItem('totalDirs', $this->hasher->totalDirs);
		$this->suspectedFiles = $this->hasher->getSuspectedFiles();
		$this->hasher = false;
	}
	private function scan_knownFiles_finish(){
	}
	private function scan_fileContents_init(){
		$this->statusIDX['infect'] = wfIssues::statusStart('Scanning file contents for infections and vulnerabilities');
		$this->statusIDX['GSB'] = wfIssues::statusStart('Scanning files for URLs on a domain blacklist');
		$this->scanner = new wordfenceScanner($this->apiKey, $this->wp_version, ABSPATH);
		$this->status(2, 'info', "Starting scan of file contents");
	}
	private function scan_fileContents_main(){
		$this->fileContentsResults = $this->scanner->scan($this);
	}
	private function scan_fileContents_finish(){
		$this->status(2, 'info', "Done file contents scan");
		if($this->scanner->errorMsg){
			throw new Exception($this->scanner->errorMsg);
		}
		$this->scanner = null;
		$haveIssues = wfIssues::STATUS_SECURE;
		$haveIssuesGSB = wfIssues::STATUS_SECURE;
		foreach($this->fileContentsResults as $issue){
			$this->status(2, 'info', "Adding issue: " . $issue['shortMsg']);
			$added = $this->addIssue($issue['type'], $issue['severity'], $issue['ignoreP'], $issue['ignoreC'], $issue['shortMsg'], $issue['longMsg'], $issue['data']);
			
			if (isset($issue['data']['gsb'])) {
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssuesGSB = wfIssues::STATUS_PROBLEM; }
				else if ($haveIssuesGSB != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssuesGSB = wfIssues::STATUS_IGNORED; }
			}
			else {
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
				else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
			}
		}
		$this->fileContentsResults = null;
		wfIssues::statusEnd($this->statusIDX['infect'], $haveIssues);
		wfIssues::statusEnd($this->statusIDX['GSB'], $haveIssuesGSB);
	}

	private function scan_suspectedFiles() {
		$haveIssues = wfIssues::STATUS_SECURE;
		$status = wfIssues::statusStart("Scanning for publicly accessible quarantined files");
		
		if (is_array($this->suspectedFiles) && count($this->suspectedFiles) > 0) {
			foreach ($this->suspectedFiles as $file) {
				wordfence::status(4, 'info', "Testing accessibility of: $file");
				$test = wfPubliclyAccessibleFileTest::createFromRootPath($file);
				if ($test->fileExists() && $test->isPubliclyAccessible()) {
					$key = "publiclyAccessible" . bin2hex($test->getUrl());
					$added = $this->addIssue(
						'publiclyAccessible',
						2,
						$key,
						$key,
						'Publicly accessible quarantined file found: ' . esc_html($file),
						'<a href="' . $test->getUrl() . '" target="_blank" rel="noopener noreferrer">' . $test->getUrl() . '</a> is publicly
					accessible and may expose source code or sensitive information about your site. Files such as this one are commonly
					checked for by scanners and should be removed or made inaccessible.',
						array(
							'url'       => $test->getUrl(),
							'file'      => $file,
							'canDelete' => true,
						)
					);
					
					if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
					else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
				}
			}
		}
		
		wfIssues::statusEnd($status, $haveIssues);
	}

	private function scan_posts_init() {
		$this->statusIDX['posts'] = wfIssues::statusStart('Scanning posts for URLs on a domain blacklist');
		$blogsToScan = self::getBlogsToScan('posts');
		$this->scanQueue = '';
		$wfdb = new wfDB();
		$this->hoover = new wordfenceURLHoover($this->apiKey, $this->wp_version);
		foreach($blogsToScan as $blog){
			$q1 = $wfdb->querySelect("select ID from " . $blog['table'] . " where post_type IN ('page', 'post') and post_status = 'publish'");
			foreach($q1 as $idRow){
				$this->scanQueue .= pack('LL', $blog['blog_id'], $idRow['ID']);
			}
		}
	}
	private function scan_posts_main() {
		global $wpdb;
		$wfdb = new wfDB();
		while (strlen($this->scanQueue) > 0) {
			$segment = substr($this->scanQueue, 0, 8);
			$this->scanQueue = substr($this->scanQueue, 8);
			$elem = unpack('Lblog/Lpost', $segment);
			$queueSize = strlen($this->scanQueue) / 8;
			if ($queueSize > 0 && $queueSize % 1000 == 0) {
				wordfence::status(2, 'info', "Scanning posts with {$queueSize} left to scan.");
			}
			
			$blogID = $elem['blog'];
			$postID = $elem['post'];
			
			$blogs = self::getBlogsToScan('posts', $blogID);
			$blog = array_shift($blogs);
			
			$prefix = $wpdb->get_blog_prefix($blogID);
			$table = "{$prefix}posts";
			
			$row = $wfdb->querySingleRec("select ID, post_title, post_type, post_date, post_content from {$table} where ID = %d", $postID);
			$this->hoover->hoover($blogID . '-' . $row['ID'], $row['post_title'] . ' ' . $row['post_content'], wordfenceURLHoover::standardExcludedHosts());
			if (preg_match('/(?:<[\s\n\r\t]*script[\r\s\n\t]+.*>|<[\s\n\r\t]*meta.*refresh)/i', $row['post_title'])) {
				$this->addIssue('postBadTitle', 1, $row['ID'], md5($row['post_title']), "Post title contains suspicious code", "This post contains code that is suspicious. Please check the title of the post and confirm that the code in the title is not malicious.", array(
					'postID' => $postID,
					'postTitle' => $row['post_title'],
					'permalink' => get_permalink($postID),
					'editPostLink' => get_edit_post_link($postID),
					'type' => $row['post_type'],
					'postDate' => $row['post_date'],
					'isMultisite' => $blog['isMultisite'],
					'domain' => $blog['domain'],
					'path' => $blog['path'],
					'blog_id' => $blog['blog_id']
				));
			}
			
			$this->forkIfNeeded();
		}
	}
	private function scan_posts_finish() {
		global $wpdb;
		$wfdb = new wfDB();
		$this->status(2, 'info', "Examining URLs found in posts we scanned for dangerous websites");
		$hooverResults = $this->hoover->getBaddies();
		$this->status(2, 'info', "Done examining URLs");
		if ($this->hoover->errorMsg) {
			wfIssues::statusEndErr();
			throw new Exception($this->hoover->errorMsg);
		}
		$this->hoover->cleanup();
		$haveIssues = wfIssues::STATUS_SECURE;
		foreach ($hooverResults as $idString => $hresults) {
			$arr = explode('-', $idString);
			$blogID = $arr[0];
			$postID = $arr[1];
			$prefix = $wpdb->get_blog_prefix($blogID);
			$table = "{$prefix}posts";
			$blog = null;
			$post = null;
			foreach ($hresults as $result) {
				if ($result['badList'] != 'goog-malware-shavar' && $result['badList'] != 'googpub-phish-shavar' && $result['badList'] != 'wordfence-dbl') {
					continue; //A list type that may be new and the plugin has not been upgraded yet.
				}
				
				if ($blog === null) {
					$blogs = self::getBlogsToScan('posts', $blogID);
					$blog = array_shift($blogs);
				}
				
				if ($post === null) {
					$post = $wfdb->querySingleRec("select ID, post_title, post_type, post_date, post_content from {$table} where ID = %d", $postID);
					$type = $post['post_type'] ? $post['post_type'] : 'comment';
					$uctype = ucfirst($type);
					$postDate = $post['post_date'];
					$title = $post['post_title'];
					$contentMD5 = md5($post['post_content']);
				}
				
				if ($result['badList'] == 'goog-malware-shavar') {
					$shortMsg = "{$uctype} contains a suspected malware URL: " . esc_html($title);
					$longMsg = "This " . esc_html($type) . " contains a suspected malware URL listed on Google's list of malware sites. The URL is: " . esc_html($result['URL']) . " - More info available at <a href=\"http://safebrowsing.clients.google.com/safebrowsing/diagnostic?site=" . urlencode($result['URL']) . "&client=googlechrome&hl=en-US\" target=\"_blank\" rel=\"noopener noreferrer\">Google Safe Browsing diagnostic page</a>.";
				}
				else if ($result['badList'] == 'googpub-phish-shavar') {
					$shortMsg = "{$uctype} contains a suspected phishing site URL: " . esc_html($title);
					$longMsg = "This " . esc_html($type) . " contains a URL that is a suspected phishing site that is currently listed on Google's list of known phishing sites. The URL is: " . esc_html($result['URL']);
				}
				else if ($result['badList'] == 'wordfence-dbl') {
					$shortMsg = "{$uctype} contains a suspected malware URL: " . esc_html($title);
					$longMsg = "This " . esc_html($type) . " contains a URL that is currently listed on Wordfence's domain blacklist. The URL is: " . esc_html($result['URL']);
				}
				else {
					//A list type that may be new and the plugin has not been upgraded yet.
					continue;
				}
				
				$this->status(2, 'info', "Adding issue: $shortMsg");
				if (is_multisite()) {
					switch_to_blog($blogID);
				}
				$ignoreP = $idString;
				$ignoreC = $idString . $contentMD5;
				$added = $this->addIssue('postBadURL', 1, $ignoreP, $ignoreC, $shortMsg, $longMsg, array(
					'postID' => $postID,
					'badURL' => $result['URL'],
					'postTitle' => $title,
					'type' => $type,
					'uctype' => $uctype,
					'permalink' => get_permalink($postID),
					'editPostLink' => get_edit_post_link($postID),
					'postDate' => $postDate,
					'isMultisite' => $blog['isMultisite'],
					'domain' => $blog['domain'],
					'path' => $blog['path'],
					'blog_id' => $blogID
				));
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
				else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
				if (is_multisite()) {
					restore_current_blog();
				}
			}
		}
		wfIssues::statusEnd($this->statusIDX['posts'], $haveIssues);
		$this->scanQueue = '';
	}
	private function scan_comments_init(){
		$this->statusIDX['comments'] = wfIssues::statusStart('Scanning comments for URLs on a domain blacklist');
		$this->scanData = array();
		$this->scanQueue = '';
		$this->hoover = new wordfenceURLHoover($this->apiKey, $this->wp_version);
		$blogsToScan = self::getBlogsToScan('comments');
		$wfdb = new wfDB();
		foreach($blogsToScan as $blog){
			$q1 = $wfdb->querySelect("select comment_ID from " . $blog['table'] . " where comment_approved=1");
			foreach($q1 as $idRow){
				$this->scanQueue .= pack('LL', $blog['blog_id'], $idRow['comment_ID']);
			}
		}
	}
	private function scan_comments_main(){
		global $wpdb;
		$wfdb = new wfDB();
		while (strlen($this->scanQueue) > 0) {
			$segment = substr($this->scanQueue, 0, 8);
			$this->scanQueue = substr($this->scanQueue, 8);
			$elem = unpack('Lblog/Lcomment', $segment);
			$queueSize = strlen($this->scanQueue) / 8;
			if ($queueSize > 0 && $queueSize % 1000 == 0) {
				wordfence::status(2, 'info', "Scanning comments with {$queueSize} left to scan.");
			}
			
			$blogID = $elem['blog'];
			$commentID = $elem['comment'];
			
			$prefix = $wpdb->get_blog_prefix($blogID);
			$table = "{$prefix}comments";
			
			$row = $wfdb->querySingleRec("select comment_ID, comment_date, comment_type, comment_author, comment_author_url, comment_content from {$table} where comment_ID=%d", $commentID);
			$this->hoover->hoover($blogID . '-' . $row['comment_ID'], $row['comment_author_url'] . ' ' . $row['comment_author'] . ' ' . $row['comment_content'], wordfenceURLHoover::standardExcludedHosts());
			$this->forkIfNeeded();
		}
	}
	private function scan_comments_finish(){
		$wfdb = new wfDB();
		$hooverResults = $this->hoover->getBaddies();
		if($this->hoover->errorMsg){
			wfIssues::statusEndErr();
			throw new Exception($this->hoover->errorMsg);
		}
		$this->hoover->cleanup();
		$haveIssues = wfIssues::STATUS_SECURE;
		foreach($hooverResults as $idString => $hresults){
			$arr = explode('-', $idString);
			$blogID = $arr[0];
			$commentID = $arr[1];
			$blog = null;
			$comment = null;
			foreach ($hresults as $result) {
				if ($result['badList'] != 'goog-malware-shavar' && $result['badList'] != 'googpub-phish-shavar' && $result['badList'] != 'wordfence-dbl') { 
					continue; //A list type that may be new and the plugin has not been upgraded yet.
				}
				
				if ($blog === null) {
					$blogs = self::getBlogsToScan('comments', $blogID);
					$blog = array_shift($blogs);
				}
				
				if ($comment === null) {
					$comment = $wfdb->querySingleRec("select comment_ID, comment_date, comment_type, comment_author, comment_author_url, comment_content from " . $blog['table'] . " where comment_ID=%d", $commentID);
					$type = $comment['comment_type'] ? $comment['comment_type'] : 'comment';
					$uctype = ucfirst($type);
					$author = $comment['comment_author'];
					$date = $comment['comment_date'];
					$contentMD5 = md5($comment['comment_content'] . $comment['comment_author'] . $comment['comment_author_url']);
				}
				
				if ($result['badList'] == 'goog-malware-shavar') {
					$shortMsg = "$uctype with author " . esc_html($author) . " contains a suspected malware URL.";
					$longMsg = "This " . esc_html($type) . " contains a suspected malware URL listed on Google's list of malware sites. The URL is: " . esc_html($result['URL']) . " - More info available at <a href=\"http://safebrowsing.clients.google.com/safebrowsing/diagnostic?site=" . urlencode($result['URL']) . "&client=googlechrome&hl=en-US\" target=\"_blank\" rel=\"noopener noreferrer\">Google Safe Browsing diagnostic page</a>.";
				}
				else if ($result['badList'] == 'googpub-phish-shavar') {
					$shortMsg = "$uctype contains a suspected phishing site URL.";
					$longMsg = "This " . esc_html($type) . " contains a URL that is a suspected phishing site that is currently listed on Google's list of known phishing sites. The URL is: " . esc_html($result['URL']);
				}
				else if ($result['badList'] == 'wordfence-dbl') {
					$shortMsg = "$uctype contains a suspected malware URL.";
					$longMsg = "This " . esc_html($type) . " contains a URL that is currently listed on Wordfence's domain blacklist. The URL is: " . esc_html($result['URL']);
				}
				
				if(is_multisite()){
					switch_to_blog($blogID);
				}
				
				$ignoreP = $idString;
				$ignoreC = $idString . '-' . $contentMD5;
				$added = $this->addIssue('commentBadURL', 1, $ignoreP, $ignoreC, $shortMsg, $longMsg, array(
					'commentID' => $commentID,
					'badURL' => $result['URL'],
					'author' => $author,
					'type' => $type,
					'uctype' => $uctype,
					'editCommentLink' => get_edit_comment_link($commentID),
					'commentDate' => $date,
					'isMultisite' => $blog['isMultisite'],
					'domain' => $blog['domain'],
					'path' => $blog['path'],
					'blog_id' => $blogID
					));
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
				else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
				
				if(is_multisite()){
					restore_current_blog();
				}
			}
		}
		wfIssues::statusEnd($this->statusIDX['comments'], $haveIssues);
		$this->scanQueue = '';
	}
	public function isBadComment($author, $email, $url, $IP, $content){
		$content = $author . ' ' . $email . ' ' . $url . ' ' . $IP . ' ' . $content;
		$cDesc = '';
		if($author){
			$cDesc = "Author: $author ";
		}
		if($email){
			$cDesc .= "Email: $email ";
		}
		$cDesc .= "Source IP: $IP ";
		$this->status(2, 'info', "Scanning comment with $cDesc");

		$h = new wordfenceURLHoover($this->apiKey, $this->wp_version);
		$h->hoover(1, $content, wordfenceURLHoover::standardExcludedHosts());
		$hooverResults = $h->getBaddies();
		if($h->errorMsg){
			return false;
		}
		$h->cleanup();
		if(sizeof($hooverResults) > 0 && isset($hooverResults[1])){
			$hresults = $hooverResults[1];	
			foreach($hresults as $result){
				if($result['badList'] == 'goog-malware-shavar'){
					$this->status(2, 'info', "Marking comment as spam for containing a malware URL. Comment has $cDesc");
					return true;
				}
				else if($result['badList'] == 'googpub-phish-shavar'){
					$this->status(2, 'info', "Marking comment as spam for containing a phishing URL. Comment has $cDesc");
					return true;
				}
				else if ($result['badList'] == 'wordfence-dbl') {
					$this->status(2, 'info', "Marking comment as spam for containing a malware URL. Comment has $cDesc");
				}
				else {
					//A list type that may be new and the plugin has not been upgraded yet.
					continue;
				}
			}
		}
		$this->status(2, 'info', "Scanned comment with $cDesc");
		return false;
	}
	public static function getBlogsToScan($table, $withID = null){
		$wfdb = new wfDB();
		global $wpdb;
		$prefix = $wpdb->base_prefix;
		$blogsToScan = array();
		if(is_multisite()){
			if ($withID === null) {
				$q1 = $wfdb->querySelect("select blog_id, domain, path from $prefix"."blogs where deleted=0 order by blog_id asc");
			}
			else {
				$q1 = $wfdb->querySelect("select blog_id, domain, path from $prefix"."blogs where deleted=0 and blog_id = %d", $withID);
			}
			
			foreach($q1 as $row){
				$row['isMultisite'] = true;
				if($row['blog_id'] == 1){
					$row['table'] = $prefix . $table;
				} else {
					$row['table'] = $prefix . $row['blog_id'] . '_' . $table;
				}
				$blogsToScan[] = $row; 
			}
		} else {
			$blogsToScan[] = array(
				'isMultisite' => false,
				'table' => $prefix . $table,
				'blog_id' => '1',
				'domain' => '',
				'path' => '',
				);
		}
		return $blogsToScan;
	}
	private function highestCap($caps){
		foreach(array('administrator', 'editor', 'author', 'contributor', 'subscriber') as $cap){
			if(empty($caps[$cap]) === false && $caps[$cap]){
				return $cap;
			}
		}
		return '';
	}
	private function isEditor($caps){
		foreach(array('contributor', 'author', 'editor', 'administrator') as $cap){
			if(empty($caps[$cap]) === false && $caps[$cap]){
				return true;
			}
		}
		return false;
	}
	private function scan_passwds_init(){
		$this->statusIDX['passwds'] = wfIssues::statusStart('Scanning for weak passwords');
		global $wpdb;
		$counter = 0;
		$query = "select ID from " . $wpdb->users;
		$dbh = $wpdb->dbh;
		$useMySQLi = (is_object($dbh) && $wpdb->use_mysqli);
		if ($useMySQLi) { //If direct-access MySQLi is available, we use it to minimize the memory footprint instead of letting it fetch everything into an array first
			$result = $dbh->query($query);
			if (!is_object($result)) {
				return array(
					'errorMsg' => "We were unable to generate the user list for your password audit.",
				);
			}
			while ($rec = $result->fetch_assoc()) {
				$this->userPasswdQueue .= pack('N', $rec['ID']);
				$counter++;
			}
		}
		else {
			$res1 = $wpdb->get_results($query, ARRAY_A);
			foreach($res1 as $rec){
				$this->userPasswdQueue .= pack('N', $rec['ID']);
				$counter++;
			}
		}
		wordfence::status(2, 'info', "Starting password strength check on {$counter} users.");
	}
	private function scan_passwds_main(){
		while(strlen($this->userPasswdQueue) > 3){
			$usersLeft = strlen($this->userPasswdQueue) / 4; //4 byte ints
			if($usersLeft % 100 == 0){
				wordfence::status(2, 'info', "Total of {$usersLeft} users left to process in password strength check.");
			}
			$userID = unpack('N', substr($this->userPasswdQueue, 0, 4));
			$userID = $userID[1];
			$this->userPasswdQueue = substr($this->userPasswdQueue, 4);
			$state = $this->scanUserPassword($userID);
			if ($state == wfIssues::STATUS_PROBLEM) { $this->passwdHasIssues = wfIssues::STATUS_PROBLEM; }
			else if ($this->passwdHasIssues != wfIssues::STATUS_PROBLEM && $state == wfIssues::STATUS_IGNORED) { $this->passwdHasIssues = wfIssues::STATUS_IGNORED; }
			
			$this->forkIfNeeded();
		}
	}
	private function scan_passwds_finish(){
		wfIssues::statusEnd($this->statusIDX['passwds'], $this->passwdHasIssues);
	}
	public function scanUserPassword($userID){
		$suspended = wp_suspend_cache_addition();
		wp_suspend_cache_addition(true);
		require_once( ABSPATH . 'wp-includes/class-phpass.php');
		$passwdHasher = new PasswordHash(8, TRUE);
		$userDat = get_userdata($userID);
		if ($userDat === false) {
			wordfence::status(2, 'error', "Could not get username for user with ID {$userID} when checking password strength.");
			return false;
		}
		//user_login
		$this->status(4, 'info', "Checking password strength of user '" . $userDat->user_login . "' with ID {$userID}" . (function_exists('memory_get_usage') ? " (Mem:" . sprintf('%.1f', memory_get_usage(true) / (1024 * 1024)) . "M)" : ""));
		$highCap = $this->highestCap($userDat->wp_capabilities);
		if($this->isEditor($userDat->wp_capabilities)){ 
			$shortMsg = "User \"" . esc_html($userDat->user_login) . "\" with \"" . esc_html($highCap) . "\" access has an easy password.";
			$longMsg = "A user with the a role of '" . esc_html($highCap) . "' has a password that is easy to guess. Please change this password yourself or ask the user to change it.";
			$level = 1;
			$words = $this->dictWords;
		} else {
			$shortMsg = "User \"" . esc_html($userDat->user_login) . "\" with 'subscriber' access has a very easy password.";
			$longMsg = "A user with 'subscriber' access has a password that is very easy to guess. Please either change it or ask the user to change their password.";
			$level = 2;
			$words = array($userDat->user_login);
		}
		$haveIssues = wfIssues::STATUS_SECURE;
		for($i = 0; $i < sizeof($words); $i++){
			if($passwdHasher->CheckPassword($words[$i], $userDat->user_pass)){
				$this->status(2, 'info', "Adding issue " . $shortMsg);
				$added = $this->addIssue('easyPassword', $level, $userDat->ID, $userDat->ID . '-' . $userDat->user_pass, $shortMsg, $longMsg, array(
					'ID' => $userDat->ID,
					'user_login' => $userDat->user_login,
					'user_email' => $userDat->user_email,
					'first_name' => $userDat->first_name,
					'last_name' => $userDat->last_name,
					'editUserLink' => wfUtils::editUserLink($userDat->ID)
					));
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
				else if ($haveIssues != wfIssues::STATUS_SECURE && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
				break;
			}
		}
		$this->status(4, 'info', "Completed checking password strength of user '" . $userDat->user_login . "'");
		wp_suspend_cache_addition($suspended);
		return $haveIssues;
	}
	/*
	private function scan_sitePages(){
		if(is_multisite()){ return; } //Multisite not supported by this function yet
		$this->statusIDX['sitePages'] = wordfence::statusStart("Scanning externally for malware");
		$resp = wp_remote_get(site_url());
		if(is_array($resp) && isset($resp['body']) && strlen($rep['body']) > 0){
			$this->hoover = new wordfenceURLHoover($this->apiKey, $this->wp_version);
			$this->hoover->hoover(1, $rep['body']);
			$hooverResults = $this->hoover->getBaddies();
			if($this->hoover->errorMsg){
				wordfence::statusEndErr();
				throw new Exception($this->hoover->errorMsg);
			}
			$badURLs = array();
			foreach($hooverResults as $idString => $hresults){
				foreach($hresults as $result){
					if(! in_array($result['URL'], $badURLs)){
						$badURLs[] = $result['URL'];
					}
				}
			}
			if(sizeof($badURLs) > 0){
				$this->addIssue('badSitePage', 1, 'badSitePage1', 'badSitePage1', "Your home page contains a malware URL");
			}
		}
	}
	*/
	private function scan_diskSpace(){
		$this->statusIDX['diskSpace'] = wfIssues::statusStart("Scanning to check available disk space");
		wfUtils::errorsOff();
		$total = @disk_total_space('.');
		$free = @disk_free_space('.');
		wfUtils::errorsOn();
		if( (! $total) || (! $free )){ //If we get zeros it's probably not reading right. If free is zero then we're out of space and already in trouble.
			wfIssues::statusEnd($this->statusIDX['diskSpace'], wfIssues::STATUS_FAILED);
			return;
		}
		$this->status(2, 'info', "Total disk space: " . sprintf('%.4f', ($total / 1024 / 1024 / 1024)) . "GB -- Free disk space: " . sprintf('%.4f', ($free / 1024 / 1024 / 1024)) . "GB");
		$freeMegs = sprintf('%.2f', $free / 1024 / 1024);
		$this->status(2, 'info', "The disk has $freeMegs MB space available");
		if($freeMegs < 5){
			$level = 1;
		} else if($freeMegs < 20){
			$level = 2;
		} else {
			wfIssues::statusEnd($this->statusIDX['diskSpace'], wfIssues::STATUS_SECURE);
			return;
		}
		$haveIssues = wfIssues::STATUS_SECURE;
		$added = $this->addIssue('diskSpace', $level, 'diskSpace', 'diskSpace' . $level, "You have $freeMegs" . "MB disk space remaining", "You only have $freeMegs" . " Megabytes of your disk space remaining. Please free up disk space or your website may stop serving requests.", array(
			'spaceLeft' => $freeMegs . "MB" ));
		if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
		else if ($haveIssues != wfIssues::STATUS_SECURE && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
		wfIssues::statusEnd($this->statusIDX['diskSpace'], $haveIssues);
	}
	private function scan_dns(){
		if(! function_exists('dns_get_record')){
			$this->status(1, 'info', "Skipping DNS scan because this system does not support dns_get_record()");
			return;
		}
		$this->statusIDX['dns'] = wfIssues::statusStart("Scanning DNS for unauthorized changes");
		$haveIssues = wfIssues::STATUS_SECURE;
		$home = get_home_url();
		if(preg_match('/https?:\/\/([^\/]+)/i', $home, $matches)){
			$host = strtolower($matches[1]);
			$this->status(2, 'info', "Starting DNS scan for $host");
			
			$cnameArrRec = @dns_get_record($host, DNS_CNAME);
			$cnameArr = array();
			$cnamesWeMustTrack = array();
			if ($cnameArrRec) {
				foreach($cnameArrRec as $elem){
					$this->status(2, 'info', "Scanning CNAME DNS record for " . $elem['host']);
					if($elem['host'] == $host){
						$cnameArr[] = $elem;
						$cnamesWeMustTrack[] = $elem['target'];
					}
				}
			}
			
			function wfAnonFunc1($a){ return $a['host'] . ' points to ' . $a['target']; }
			$cnameArr = array_map('wfAnonFunc1', $cnameArr);
			sort($cnameArr, SORT_STRING);
			$currentCNAME = implode(', ', $cnameArr);
			$loggedCNAME = wfConfig::get('wf_dnsCNAME');
			$dnsLogged = wfConfig::get('wf_dnsLogged', false);
			$msg = "A change in your DNS records may indicate that a hacker has hacked into your DNS administration system and has pointed your email or website to their own server for malicious purposes. It could also indicate that your domain has expired. If you made this change yourself you can mark it 'resolved' and safely ignore it.";
			if($dnsLogged && $loggedCNAME != $currentCNAME){
				$added = $this->addIssue('dnsChange', 2, 'dnsChanges', 'dnsChanges' . $currentCNAME, "Your DNS records have changed", "We have detected a change in the CNAME records of your DNS configuration for the domain $host. A CNAME record is an alias that is used to point a domain name to another domain name. For example foo.example.com can point to bar.example.com which then points to an IP address of 10.1.1.1. $msg", array(
					'type' => 'CNAME',
					'host' => $host,
					'oldDNS' => $loggedCNAME,
					'newDNS' => $currentCNAME
				));
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
				else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
			}
			wfConfig::set('wf_dnsCNAME', $currentCNAME);
			
			$aArrRec = @dns_get_record($host, DNS_A);
			$aArr = array();
			if ($aArrRec) {
				foreach($aArrRec as $elem){
					$this->status(2, 'info', "Scanning DNS A record for " . $elem['host']);
					if($elem['host'] == $host || in_array($elem['host'], $cnamesWeMustTrack) ){
						$aArr[] = $elem;
					}
				}
			}
			function wfAnonFunc2($a){ return $a['host'] . ' points to ' . $a['ip']; }
			$aArr = array_map('wfAnonFunc2', $aArr);
			sort($aArr, SORT_STRING);
			$currentA = implode(', ', $aArr);
			$loggedA = wfConfig::get('wf_dnsA');
			$dnsLogged = wfConfig::get('wf_dnsLogged', false);
			if($dnsLogged && $loggedA != $currentA){
				$added = $this->addIssue('dnsChange', 2, 'dnsChanges', 'dnsChanges' . $currentA, "Your DNS records have changed", "We have detected a change in the A records of your DNS configuration that may affect the domain $host. An A record is a record in DNS that points a domain name to an IP address. $msg", array(
					'type' => 'A',
					'host' => $host,
					'oldDNS' => $loggedA,
					'newDNS' => $currentA
				));
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
				else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
			}
			wfConfig::set('wf_dnsA', $currentA);
			
			$mxArrRec = @dns_get_record($host, DNS_MX);
			$mxArr = array();
			if ($mxArrRec) {
				foreach ($mxArrRec as $elem)
				{
					$this->status(2, 'info', "Scanning DNS MX record for " . $elem['host']);
					if ($elem['host'] == $host)
					{
						$mxArr[] = $elem;
					}
				}
			}
			function wfAnonFunc3($a){ return $a['target']; }
			$mxArr = array_map('wfAnonFunc3', $mxArr);
			sort($mxArr, SORT_STRING);
			$currentMX = implode(', ', $mxArr);
			$loggedMX = wfConfig::get('wf_dnsMX');
			if($dnsLogged && $loggedMX != $currentMX){
				$added = $this->addIssue('dnsChange', 2, 'dnsChanges', 'dnsChanges' . $currentMX, "Your DNS records have changed", "We have detected a change in the email server (MX) records of your DNS configuration for the domain $host. $msg", array( 
					'type' => 'MX',
					'host' => $host,
					'oldDNS' => $loggedMX,
					'newDNS' => $currentMX
					));
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
				else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
			}
			wfConfig::set('wf_dnsMX', $currentMX);
				
			wfConfig::set('wf_dnsLogged', 1);
		}
		wfIssues::statusEnd($this->statusIDX['dns'], $haveIssues);
	}
	
	private function scan_oldVersions_init() {
		$this->statusIDX['oldVersions'] = wfIssues::statusStart("Scanning for old themes, plugins and core files");
		
		$this->updateCheck = new wfUpdateCheck();
		$this->updateCheck->checkAllUpdates(false);
		$this->updateCheck->checkAllVulnerabilities();
		
		foreach ($this->updateCheck->getPluginSlugs() as $slug) {
			$this->pluginRepoStatus[$slug] = false;
		}
		
		//Strip plugins that have a pending update
		if (count($this->updateCheck->getPluginUpdates()) > 0) {
			foreach ($this->updateCheck->getPluginUpdates() as $plugin) {
				if (!empty($plugin['slug'])) {
					unset($this->pluginRepoStatus[$plugin['slug']]);
				}
			}
		}
	}
	
	private function scan_oldVersions_main() {
		if (!function_exists('plugins_api')) {
			require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
		}
		
		foreach ($this->pluginRepoStatus as $slug => $status) {
			if ($status === false) {
				$result = plugins_api('plugin_information', array(
					'slug' => $slug,
					'fields' => array(
						'short_description' => false,
						'description' => false,
						'sections' => false,
						'tested' => true,
						'requires' => true,
						'rating' => false,
						'ratings' => false,
						'downloaded' => false,
						'downloadlink' => false,
						'last_updated' => true,
						'added' => false,
						'tags' => false,
						'compatibility' => true,
						'homepage' => true,
						'versions' => false,
						'donate_link' => false,
						'reviews' => false,
						'banners' => false,
						'icons' => false,
						'active_installs' => false,
						'group' => false,
						'contributors' => false,
					),
				));
				unset($result->versions);
				unset($result->screenshots);
				$this->pluginRepoStatus[$slug] = $result;
				
				$this->forkIfNeeded();
			}
		}
	}

	private function scan_oldVersions_finish() {
		$haveIssues = wfIssues::STATUS_SECURE;

		// WordPress core updates needed
		if ($this->updateCheck->needsCoreUpdate()) {
			$added = $this->addIssue('wfUpgrade', 1, 'wfUpgrade' . $this->updateCheck->getCoreUpdateVersion(), 'wfUpgrade' . $this->updateCheck->getCoreUpdateVersion(), "Your WordPress version is out of date", "WordPress version " . esc_html($this->updateCheck->getCoreUpdateVersion()) . " is now available. Please upgrade immediately to get the latest security updates from WordPress.", array(
				'currentVersion' => $this->wp_version,
				'newVersion'     => $this->updateCheck->getCoreUpdateVersion(),
			));
			if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
			else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
		}
		
		$allPlugins = $this->updateCheck->getAllPlugins();

		// Plugin updates needed
		if (count($this->updateCheck->getPluginUpdates()) > 0) {
			foreach ($this->updateCheck->getPluginUpdates() as $plugin) {
				$severity = 1; //Critical
				if (isset($plugin['vulnerable'])) {
					if (!$plugin['vulnerable']) {
						$severity = 2; //Warning
					}
				}
				$key = 'wfPluginUpgrade' . ' ' . $plugin['pluginFile'] . ' ' . $plugin['newVersion'] . ' ' . $plugin['Version'];
				$shortMsg = "The Plugin \"" . $plugin['Name'] . "\" needs an upgrade (" . $plugin['Version'] . " -> " . $plugin['newVersion'] . ").";
				$added = $this->addIssue('wfPluginUpgrade', $severity, $key, $key, $shortMsg, "You need to upgrade \"" . $plugin['Name'] . "\" to the newest version to ensure you have any security fixes the developer has released.", $plugin);
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
				else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
				
				if (isset($plugin['slug'])) {
					unset($allPlugins[$plugin['slug']]);
				}
			}
		}

		// Theme updates needed
		if (count($this->updateCheck->getThemeUpdates()) > 0) {
			foreach ($this->updateCheck->getThemeUpdates() as $theme) {
				$severity = 1; //Critical
				if (isset($theme['vulnerable'])) {
					if (!$theme['vulnerable']) {
						$severity = 2; //Warning
					}
				}
				$key = 'wfThemeUpgrade' . ' ' . $theme['Name'] . ' ' . $theme['version'] . ' ' . $theme['newVersion'];
				$shortMsg = "The Theme \"" . $theme['Name'] . "\" needs an upgrade (" . $theme['version'] . " -> " . $theme['newVersion'] . ").";
				$added = $this->addIssue('wfThemeUpgrade', $severity, $key, $key, $shortMsg, "You need to upgrade \"" . esc_html($theme['Name']) . "\" to the newest version to ensure you have any security fixes the developer has released.", $theme);
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
				else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
			}
		}
		
		//Abandoned plugins
		foreach ($this->pluginRepoStatus as $slug => $status) {
			if ($status !== false && !is_wp_error($status) && property_exists($status, 'last_updated')) {
				$lastUpdateTimestamp = strtotime($status->last_updated);
				if ($lastUpdateTimestamp > 0 && (time() - $lastUpdateTimestamp) > 63072000 /* ~2 years */) {
					$statusArray = (array) $status;
					$statusArray['dateUpdated'] = wfUtils::formatLocalTime(get_option('date_format'), $lastUpdateTimestamp);
					$severity = 2; //Warning
					$statusArray['abandoned'] = true;
					$statusArray['vulnerable'] = false;
					if ($this->updateCheck->isPluginVulnerable($slug, $statusArray['version'])) {
						$severity = 1; //Critical 
						$statusArray['vulnerable'] = true;
					}
					
					if (isset($allPlugins[$slug])) {
						$statusArray['wpURL'] = $allPlugins[$slug]['wpURL'];
					}
					
					$key = "wfPluginAbandoned {$slug} {$statusArray['version']}";
					$shortMsg = 'The Plugin "' . $statusArray['name'] . '" appears to be abandoned.';
					$longMsg = 'It was last updated ' . wfUtils::makeTimeAgo(time() - $lastUpdateTimestamp) . ' ago.';
					if ($statusArray['vulnerable']) {
						$longMsg .= ' It has unpatched security issues and may have compatibility problems with the current version of WordPress.';
					}
					else {
						$longMsg .= ' It may have compatibility problems with the current version of WordPress or unknown security issues.';
					}
					$longMsg .= ' <a href="https://docs.wordfence.com/en/Understanding_scan_results#Plugin_appears_to_be_abandoned" target="_blank" rel="noopener noreferrer">Get more information.</a>';
					$added = $this->addIssue('wfPluginAbandoned', $severity, $key, $key, $shortMsg, $longMsg, $statusArray);
					if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
					else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
					
					unset($allPlugins[$slug]);
				}
			}
			else if ($status !== false && is_wp_error($status) && isset($status->error_data['plugins_api_failed']) && $status->error_data['plugins_api_failed'] == 'N;') { //The plugin does not exist in the wp.org repo
				$knownFiles = $this->getKnownFilesLoader()->getKnownFiles();
				$requestedPlugins = $this->getPlugins();
				foreach ($requestedPlugins as $key => $data) {
					$testKey = trailingslashit($data['FullDir']) . '.';
					if ($data['ShortDir'] == $slug && isset($knownFiles['plugins'][$testKey])) { //It existed in the repo at some point and was removed
						$pluginFile = wfUtils::getPluginBaseDir() . $key;
						$pluginData = get_plugin_data($pluginFile);
						$pluginData['wpRemoved'] = true;
						$pluginData['vulnerable'] = false;
						if ($this->updateCheck->isPluginVulnerable($slug, $pluginData['Version'])) {
							$pluginData['vulnerable'] = true;
						}
						
						$key = "wfPluginRemoved {$slug} {$pluginData['Version']}";
						$shortMsg = 'The Plugin "' . $pluginData['Name'] . '" has been removed from wordpress.org.';
						if ($pluginData['vulnerable']) {
							$longMsg = 'It has unpatched security issues and may have compatibility problems with the current version of WordPress.';
						}
						else {
							$longMsg = 'It may have compatibility problems with the current version of WordPress or unknown security issues.';
						}
						$longMsg .= ' <a href="https://docs.wordfence.com/en/Understanding_scan_results#Plugin_has_been_removed_from_wordpress.org" target="_blank" rel="noopener noreferrer">Get more information.</a>';
						$added = $this->addIssue('wfPluginRemoved', 1, $key, $key, $shortMsg, $longMsg, $pluginData);
						if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
						else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
						
						unset($allPlugins[$slug]);
					}
				}
			}
		}
		
		//Other vulnerable plugins
		//Disabled until we improve the data source to weed out false positives
		/*if (count($allPlugins) > 0) {
			foreach ($allPlugins as $plugin) {
				if (!isset($plugin['vulnerable']) || !$plugin['vulnerable']) {
					continue;
				}
				
				$key = 'wfPluginVulnerable' . ' ' . $plugin['pluginFile'] . ' ' . $plugin['Version'];
				$shortMsg = "The Plugin \"" . $plugin['Name'] . "\" has an unpatched security vulnerability.";
				$longMsg = 'To protect your site from this vulnerability, the safest option is to deactivate and completely remove ' . esc_html($plugin['Name']) . ' until the developer releases a security fix. <a href="https://docs.wordfence.com/en/Understanding_scan_results#Plugin_has_an_unpatched_security_vulnerability" target="_blank" rel="noopener noreferrer">Get more information.</a>';
				$added = $this->addIssue('wfPluginVulnerable', 1, $key, $key, $shortMsg, $longMsg, $plugin);
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
				else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
				
				if (isset($plugin['slug'])) {
					unset($allPlugins[$plugin['slug']]);
				}
			}
		}*/
		
		$this->updateCheck = false;
		$this->pluginRepoStatus = array();

		wfIssues::statusEnd($this->statusIDX['oldVersions'], $haveIssues);
	}

	public function scan_suspiciousAdminUsers() {
		$this->statusIDX['suspiciousAdminUsers'] = wfIssues::statusStart("Scanning for admin users not created through WordPress");
		$haveIssues = wfIssues::STATUS_SECURE;

		$adminUsers = new wfAdminUserMonitor();
		if ($adminUsers->isEnabled() && $suspiciousAdmins = $adminUsers->checkNewAdmins()) {
			foreach ($suspiciousAdmins as $userID) {
				$user = new WP_User($userID);
				$key = 'suspiciousAdminUsers' . $userID;
				$added = $this->addIssue('suspiciousAdminUsers', 1, $key, $key,
					"An admin user with the username " . esc_html($user->user_login) . " was created outside of WordPress.",
					"An admin user with the username " . esc_html($user->user_login) . " was created outside of WordPress. It's
				possible a plugin could have created the account, but if you do not recognize the user, we suggest you remove
				it.",
					array(
						'userID' => $userID,
					));
				if ($added == wfIssues::ISSUE_ADDED || $added == wfIssues::ISSUE_UPDATED) { $haveIssues = wfIssues::STATUS_PROBLEM; }
				else if ($haveIssues != wfIssues::STATUS_PROBLEM && ($added == wfIssues::ISSUE_IGNOREP || $added == wfIssues::ISSUE_IGNOREC)) { $haveIssues = wfIssues::STATUS_IGNORED; }
			}
		}

		wfIssues::statusEnd($this->statusIDX['suspiciousAdminUsers'], $haveIssues);
	}

	public function status($level, $type, $msg){
		wordfence::status($level, $type, $msg);
	}
	public function addIssue($type, $severity, $ignoreP, $ignoreC, $shortMsg, $longMsg, $templateData, $alreadyHashed = false) {
		wfIssues::updateScanStillRunning();
		return $this->i->addIssue($type, $severity, $ignoreP, $ignoreC, $shortMsg, $longMsg, $templateData, $alreadyHashed);
	}
	public function addPendingIssue($type, $severity, $ignoreP, $ignoreC, $shortMsg, $longMsg, $templateData){
		wfIssues::updateScanStillRunning();
		return $this->i->addPendingIssue($type, $severity, $ignoreP, $ignoreC, $shortMsg, $longMsg, $templateData);
	}
	public function getPendingIssueCount() {
		return $this->i->getPendingIssueCount();
	}
	public function getPendingIssues($offset = 0, $limit = 100) {
		return $this->i->getPendingIssues($offset, $limit);
	}
	public static function requestKill(){
		wfConfig::set('wfKillRequested', time(), wfConfig::DONT_AUTOLOAD);
	}
	public static function checkForKill(){
		$kill = wfConfig::get('wfKillRequested', 0);
		if($kill && time() - $kill < 600){ //Kill lasts for 10 minutes
			wordfence::status(10, 'info', "SUM_KILLED:Previous scan was killed successfully.");
			throw new Exception("Scan was killed on administrator request.");
		}
	}
	public static function startScan($isFork = false){
		if(! $isFork){ //beginning of scan
			wfConfig::inc('totalScansRun');	
			wfConfig::set('wfKillRequested', 0, wfConfig::DONT_AUTOLOAD); 
			wordfence::status(4, 'info', "Entering start scan routine");
			if(wfUtils::isScanRunning()){
				wfUtils::getScanFileError();
				return "A scan is already running. Use the kill link if you would like to terminate the current scan.";
			}
			wfConfig::set('currentCronKey', ''); //Ensure the cron key is cleared
		}
		$timeout = self::getMaxExecutionTime() - 2; //2 seconds shorter than max execution time which ensures that only 2 HTTP processes are ever occupied
		$testURL = admin_url('admin-ajax.php?action=wordfence_testAjax');
		if(! wfConfig::get('startScansRemotely', false)){
			$testResult = wp_remote_post($testURL, array(
				'timeout' => $timeout,
				'blocking' => true,
				'sslverify' => false,
				'headers' => array()
				));
			wordfence::status(4, 'info', "Test result of scan start URL fetch: " . var_export($testResult, true));	
		}
		$cronKey = wfUtils::bigRandomHex();
		wfConfig::set('currentCronKey', time() . ',' . $cronKey);
		if( (! wfConfig::get('startScansRemotely', false)) && (! is_wp_error($testResult)) && (is_array($testResult) || $testResult instanceof ArrayAccess) && strstr($testResult['body'], 'WFSCANTESTOK') !== false){
			//ajax requests can be sent by the server to itself
			$cronURL = 'admin-ajax.php?action=wordfence_doScan&isFork=' . ($isFork ? '1' : '0') . '&cronKey=' . $cronKey;
			$cronURL = admin_url($cronURL);
			$headers = array('Referer' => false/*, 'Cookie' => 'XDEBUG_SESSION=1'*/);
			wordfence::status(4, 'info', "Starting cron with normal ajax at URL $cronURL");
			wp_remote_get( $cronURL, array(
				'timeout' => $timeout, //Must be less than max execution time or more than 2 HTTP children will be occupied by scan
				'blocking' => true, //Non-blocking seems to block anyway, so we use blocking
				'sslverify' => false,
				'headers' => $headers 
				) );
			wordfence::status(4, 'info', "Scan process ended after forking.");
		} else {
			$cronURL = admin_url('admin-ajax.php');
			$cronURL = preg_replace('/^(https?:\/\/)/i', '$1noc1.wordfence.com/scanp/', $cronURL);
			$cronURL .= '?action=wordfence_doScan&isFork=' . ($isFork ? '1' : '0') . '&cronKey=' . $cronKey;
			$headers = array();
			wordfence::status(4, 'info', "Starting cron via proxy at URL $cronURL");

			wp_remote_get( $cronURL, array(
				'timeout' => $timeout, //Must be less than max execution time or more than 2 HTTP children will be occupied by scan
				'blocking' => true, //Non-blocking seems to block anyway, so we use blocking
				'sslverify' => false,
				'headers' => $headers 
				) );
			wordfence::status(4, 'info', "Scan process ended after forking.");
		}
		return false; //No error
	}
	public function processResponse($result){
		return false;
	}
	public static function getMaxExecutionTime($staySilent = false) {
		$config = wfConfig::get('maxExecutionTime');
		if (!$staySilent) { wordfence::status(4, 'info', "Got value from wf config maxExecutionTime: $config"); }
		if(is_numeric($config) && $config >= 10){
			if (!$staySilent) { wordfence::status(4, 'info', "getMaxExecutionTime() returning config value: $config"); }
			return $config;
		}
		$ini = @ini_get('max_execution_time');
		if (!$staySilent) { wordfence::status(4, 'info', "Got max_execution_time value from ini: $ini"); }
		if(is_numeric($ini) && $ini >= 10){
			$ini = floor($ini / 2);
			if (!$staySilent) { wordfence::status(4, 'info', "getMaxExecutionTime() returning half ini value: $ini"); }
			return $ini;
		}
		if (!$staySilent) { wordfence::status(4, 'info', "getMaxExecutionTime() returning default of: 15"); }
		return 15;
	}

	/**
	 * @return wfScanKnownFilesLoader
	 */
	public function getKnownFilesLoader() {
		if ($this->knownFilesLoader === null) {
			$this->knownFilesLoader = new wfScanKnownFilesLoader($this->api, $this->getPlugins(), $this->getThemes());
		}
		return $this->knownFilesLoader;
	}

	/**
	 * @return array
	 */
	public function getPlugins() {
		static $plugins = null;
		if ($plugins !== null) {
			return $plugins;
		}
		
		if(! function_exists( 'get_plugins')){
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}
		$pluginData = get_plugins();
		$plugins = array();
		foreach ($pluginData as $key => $data) {
			if (preg_match('/^([^\/]+)\//', $key, $matches)) {
				$pluginDir = $matches[1];
				$pluginFullDir = "wp-content/plugins/" . $pluginDir;
				$plugins[$key] = array(
					'Name'     => $data['Name'],
					'Version'  => $data['Version'],
					'ShortDir' => $pluginDir,
					'FullDir'  => $pluginFullDir
				);
			}
		}
		return $plugins;
	}

	/**
	 * @return array
	 */
	public function getThemes() {
		if (!function_exists('wp_get_themes')) {
			require_once ABSPATH . '/wp-includes/theme.php';
		}
		$themeData = wp_get_themes();
		$themes = array();
		foreach ($themeData as $themeName => $themeVal) {
			if (preg_match('/\/([^\/]+)$/', $themeVal['Stylesheet Dir'], $matches)) {
				$shortDir = $matches[1]; //e.g. evo4cms
				$fullDir = substr($themeVal['Stylesheet Dir'], strlen(ABSPATH)); //e.g. wp-content/themes/evo4cms
				$themes[$themeName] = array(
					'Name'     => $themeVal['Name'],
					'Version'  => $themeVal['Version'],
					'ShortDir' => $shortDir,
					'FullDir'  => $fullDir
				);
			}
		}
		return $themes;
	}
	
	public function recordMetric($type, $key, $value, $singular = true) {
		if (!isset($this->metrics[$type])) {
			$this->metrics[$type] = array();
		}
		
		if (!isset($this->metrics[$type][$key])) {
			$this->metrics[$type][$key] = array();
		}
		
		if ($singular) {
			$this->metrics[$type][$key] = $value;
		}
		else {
			$this->metrics[$type][$key][] = $value;
		}
	}
}

class wfScanKnownFilesLoader {
	/**
	 * @var array
	 */
	private $plugins;

	/**
	 * @var array
	 */
	private $themes;

	/**
	 * @var array
	 */
	private $knownFiles = array();

	/**
	 * @var wfAPI
	 */
	private $api;


	/**
	 * @param wfAPI $api
	 * @param array $plugins
	 * @param array $themes
	 */
	public function __construct($api, $plugins = null, $themes = null) {
		$this->api = $api;
		$this->plugins = $plugins;
		$this->themes = $themes;
	}

	/**
	 * @return bool
	 */
	public function isLoaded() {
		return is_array($this->knownFiles) && count($this->knownFiles) > 0;
	}

	/**
	 * @param $file
	 * @return bool
	 * @throws wfScanKnownFilesException
	 */
	public function isKnownFile($file) {
		if (!$this->isLoaded()) {
			$this->fetchKnownFiles();
		}

		return isset($this->knownFiles['core'][$file]) ||
			isset($this->knownFiles['plugins'][$file]) ||
			isset($this->knownFiles['themes'][$file]);
	}

	/**
	 * @param $file
	 * @return bool
	 * @throws wfScanKnownFilesException
	 */
	public function isKnownCoreFile($file) {
		if (!$this->isLoaded()) {
			$this->fetchKnownFiles();
		}
		return isset($this->knownFiles['core'][$file]);
	}

	/**
	 * @param $file
	 * @return bool
	 * @throws wfScanKnownFilesException
	 */
	public function isKnownPluginFile($file) {
		if (!$this->isLoaded()) {
			$this->fetchKnownFiles();
		}
		return isset($this->knownFiles['plugins'][$file]);
	}

	/**
	 * @param $file
	 * @return bool
	 * @throws wfScanKnownFilesException
	 */
	public function isKnownThemeFile($file) {
		if (!$this->isLoaded()) {
			$this->fetchKnownFiles();
		}
		return isset($this->knownFiles['themes'][$file]);
	}

	/**
	 * @throws wfScanKnownFilesException
	 */
	public function fetchKnownFiles() {
		try {
			$dataArr = $this->api->binCall('get_known_files', json_encode(array(
				'plugins' => $this->plugins,
				'themes'  => $this->themes
			)));

			if ($dataArr['code'] != 200) {
				throw new wfScanKnownFilesException("Got error response from Wordfence servers: " . $dataArr['code'], $dataArr['code']);
			}
			$this->knownFiles = @json_decode($dataArr['data'], true);
			if (!is_array($this->knownFiles)) {
				throw new wfScanKnownFilesException("Invalid response from Wordfence servers.");
			}
		} catch (Exception $e) {
			throw new wfScanKnownFilesException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function getKnownPluginData($file) {
		if ($this->isKnownPluginFile($file)) {
			return $this->knownFiles['plugins'][$file];
		}
		return null;
	}

	public function getKnownThemeData($file) {
		if ($this->isKnownThemeFile($file)) {
			return $this->knownFiles['themes'][$file];
		}
		return null;
	}

	/**
	 * @return array
	 */
	public function getPlugins() {
		return $this->plugins;
	}

	/**
	 * @param array $plugins
	 */
	public function setPlugins($plugins) {
		$this->plugins = $plugins;
	}

	/**
	 * @return array
	 */
	public function getThemes() {
		return $this->themes;
	}

	/**
	 * @param array $themes
	 */
	public function setThemes($themes) {
		$this->themes = $themes;
	}

	/**
	 * @return array
	 * @throws wfScanKnownFilesException
	 */
	public function getKnownFiles() {
		if (!$this->isLoaded()) {
			$this->fetchKnownFiles();
		}
		return $this->knownFiles;
	}

	/**
	 * @param array $knownFiles
	 */
	public function setKnownFiles($knownFiles) {
		$this->knownFiles = $knownFiles;
	}

	/**
	 * @return wfAPI
	 */
	public function getAPI() {
		return $this->api;
	}

	/**
	 * @param wfAPI $api
	 */
	public function setAPI($api) {
		$this->api = $api;
	}
}

class wfScanKnownFilesException extends Exception {

}

class wfCommonBackupFileTest {

	/**
	 * @param string $path
	 * @return wfCommonBackupFileTest
	 */
	public static function createFromRootPath($path) {
		return new self(site_url($path), ABSPATH . $path); 
	}

	private $url;
	private $path;
	/**
	 * @var array
	 */
	private $requestArgs;
	private $response;


	/**
	 * @param string $url
	 * @param string $path
	 * @param array $requestArgs
	 */
	public function __construct($url, $path, $requestArgs = array()) {
		$this->url = $url;
		$this->path = $path;
		$this->requestArgs = $requestArgs;
	}

	/**
	 * @return bool
	 */
	public function fileExists() {
		return file_exists($this->path);
	}

	/**
	 * @return bool
	 */
	public function isPubliclyAccessible() {
		$this->response = wp_remote_get($this->url, $this->requestArgs);
		if ((int) floor(((int) wp_remote_retrieve_response_code($this->response) / 100)) === 2) {
			$handle = @fopen($this->path, 'r');
			if ($handle) {
				$contents = fread($handle, 700);
				fclose($handle);
				$remoteContents = substr(wp_remote_retrieve_body($this->response), 0, 700);
				return $contents === $remoteContents;
			}
		}
		return false;
	}

	/**
	 * @return string
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * @param string $url
	 */
	public function setUrl($url) {
		$this->url = $url;
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * @param string $path
	 */
	public function setPath($path) {
		$this->path = $path;
	}

	/**
	 * @return array
	 */
	public function getRequestArgs() {
		return $this->requestArgs;
	}

	/**
	 * @param array $requestArgs
	 */
	public function setRequestArgs($requestArgs) {
		$this->requestArgs = $requestArgs;
	}

	/**
	 * @return mixed
	 */
	public function getResponse() {
		return $this->response;
	}
}

class wfPubliclyAccessibleFileTest extends wfCommonBackupFileTest {
	
}

class wfScanEngineDurationLimitException extends Exception {
}

class wfScanEngineCoreVersionChangeException extends Exception {
}
