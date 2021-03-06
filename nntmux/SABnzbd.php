<?php
namespace nntmux;

use App\Models\Settings;
use GuzzleHttp\Client;
use nntmux\utility\Utility;

/**
 * Class SABnzbd
 */
class SABnzbd
{
	/**
	 * Type of site integration.
	 */
	const INTEGRATION_TYPE_NONE = 0;
	const INTEGRATION_TYPE_SITEWIDE = 1;
	const INTEGRATION_TYPE_USER = 2;
	/**
	 * Type of SAB API key.
	 */
	const API_TYPE_NZB = 1;
	const API_TYPE_FULL = 2;
	/**
	 * Priority to send the NZB to SAB.
	 */
	const PRIORITY_PAUSED = -2;
	const PRIORITY_LOW = -1;
	const PRIORITY_NORMAL = 0;
	const PRIORITY_HIGH = 1; // Sab is completely disabled - no user can use it.
	const PRIORITY_FORCE = 2; // Sab is enabled, 1 remote SAB server for the whole site.
	/**
	 * URL to the SAB server.
	 * @var string|array|bool
	 */
	public $url = '';

	/**
	 * The SAB API key.
	 * @var string|array|bool
	 */
	public $apikey = '';

	/**
	 * Download priority of the sent NZB file.
	 * @var string|array|bool
	 */
	public $priority = '';

	/**
	 * Type of SAB API key (full/nzb).
	 * @var string|array|bool
	 */
	public $apikeytype = '';

	/**
	 * @var int
	 */
	public $integrated = self::INTEGRATION_TYPE_NONE;

	/**
	 * Is sab integrated into the site or not.
	 * @var bool
	 */
	public $integratedBool = false;

	/**
	 * ID of the current user, to send to SAB when downloading a NZB.
	 * @var string
	 */
	protected $uid = '';

	/**
	 * User's nntmux API key to send to SAB when downloading a NZB.
	 * @var string
	 */
	protected $rsstoken = '';

	/**
	 * nZEDb Site URL to send to SAB to download the NZB.
	 * @var string
	 */
	protected $serverurl = '';

	/**
	 * Construct.
	 *
	 * @param \BasePage $page
	 *
	 * @throws \Exception
	 */
	public function __construct(&$page)
	{
		$this->uid = $page->userdata['id'];
		$this->rsstoken = $page->userdata['rsstoken'];
		$this->serverurl = $page->serverurl;
		$this->client = new Client(['verify' => false]);

		// Set up properties.
		switch (Settings::value('apps.sabnzbplus.integrationtype')) {
			case self::INTEGRATION_TYPE_USER:
				if (!empty($_COOKIE['sabnzbd_' . $this->uid . '__apikey']) && !empty($_COOKIE['sabnzbd_' . $this->uid . '__host'])) {
					$this->url = $_COOKIE['sabnzbd_' . $this->uid . '__host'];
					$this->apikey = $_COOKIE['sabnzbd_' . $this->uid . '__apikey'];
					$this->priority = $_COOKIE['sabnzbd_' . $this->uid . '__priority'] ?? 0;
					$this->apikeytype = $_COOKIE['sabnzbd_' . $this->uid . '__apitype'] ?? 1;
				} else if (!empty($page->userdata['sabapikey']) && !empty($page->userdata['saburl'])) {
					$this->url = $page->userdata['saburl'];
					$this->apikey = $page->userdata['sabapikey'];
					$this->priority = $page->userdata['sabpriority'];
					$this->apikeytype = $page->userdata['sabapikeytype'];
				}
				$this->integrated = self::INTEGRATION_TYPE_USER;
				switch ((int)$page->userdata['queuetype']) {
					case 1:
					case 2:
						$this->integratedBool = true;
						break;
					default:
						$this->integratedBool = false;
						break;
				}
				break;

			case self::INTEGRATION_TYPE_SITEWIDE:
				if ((Settings::value('apps.sabnzbplus.apikey') !== '') && (Settings::value('apps.sabnzbplus.url')
						!== '')) {
					$this->url = Settings::value('apps.sabnzbplus.url');
					$this->apikey = Settings::value('apps.sabnzbplus.apikey');
					$this->priority = Settings::value('apps.sabnzbplus.priority');
					$this->apikeytype = Settings::value('apps.sabnzbplus.apikeytype');
				}
				$this->integrated = self::INTEGRATION_TYPE_SITEWIDE;
				$this->integratedBool = true;
				break;

			case self::INTEGRATION_TYPE_NONE:
				$this->integrated = self::INTEGRATION_TYPE_NONE;
				// This is for nzbget.
				if ($page->userdata['queuetype'] === 2) {
					$this->integratedBool = true;
				}
				break;
		}
		// Verify the URL is good, fix it if not.
		if ($this->url !== '' && preg_match('/(?P<first>\/)?(?P<sab>[a-z]+)?(?P<last>\/)?$/i', $this->url, $matches)) {
			if (!isset($matches['first'])) {
				$this->url .= '/';
			}
			if (!isset($matches['sab'])) {
				$this->url .= 'sabnzbd';
			} elseif ($matches['sab'] !== 'sabnzbd') {
				$this->url .= 'sabnzbd';
			}
			if (!isset($matches['last'])) {
				$this->url .= '/';
			}
		}
	}

	/**
	 * Send a release to SAB.
	 *
	 * @param string $guid Release identifier.
	 *
	 * @return bool|mixed
	 */
	public function sendToSab($guid)
	{
		return $this->client->get(
				$this->url .
					'api?mode=addurl&priority=' .
					$this->priority .
					'&apikey=' .
					$this->apikey .
					'&name=' .
					urlencode(
						$this->serverurl .
						'getnzb/' .
						$guid .
						'&i=' .
						$this->uid .
						'&r=' .
						$this->rsstoken
					)
		);
	}

	/**
	 * Get JSON representation of the full SAB queue.
	 *
	 * @return bool|mixed
	 */
	public function getAdvQueue()
	{
		return $this->client->get(
					$this->url .
					'api?mode=queue&start=START&limit=LIMIT&output=json&apikey=' .
					$this->apikey

		);
	}

	/**
	 * Get JSON representation of SAB history.
	 *
	 * @return bool|mixed
	 */
	public function getHistory()
	{
		return $this->client->get(
			$this->url .
			'api?mode=history&start=START&limit=LIMIT&category=CATEGORY&search=SEARCH&failed_only=0&output=json&apikey=' .
			$this->apikey

		);
	}

	/**
	 * Delete a single NZB from the SAB queue.
	 *
	 * @param int $id
	 *
	 * @return bool|mixed
	 */
	public function delFromQueue($id)
	{
		return $this->client->get(
		$this->url .
			'api?mode=queue&name=delete&value=' .
			$id .
			'&apikey=' .
			$this->apikey);
	}

	/**
	 * Pause a single NZB in the SAB queue.
	 *
	 * @param int $id
	 *
	 * @return bool|mixed
	 */
	public function pauseFromQueue($id)
	{
		return $this->client->get(
		$this->url .
			'api?mode=queue&name=pause&value=' .
			$id .
			'&apikey=' .
			$this->apikey);
	}

	/**
	 * Resume a single NZB in the SAB queue.
	 *
	 * @param int $id
	 *
	 * @return bool|mixed
	 */
	public function resumeFromQueue($id)
	{
		return $this->client->get(
		$this->url .
		'api?mode=queue&name=resume&value=' .
			$id .
		'&apikey=' .
			$this->apikey
		);
	}

	/**
	 * Pause all NZB's in the SAB queue.
	 *
	 * @return bool|mixed
	 */
	public function pauseAll()
	{
		return $this->client->get(
		$this->url .
		'api?mode=pause' .
		'&apikey=' .
			$this->apikey
		);
	}

	/**
	 * Resume all NZB's in the SAB queue.
	 *
	 * @return bool|mixed
	 */
	public function resumeAll()
	{
		return $this->client->get(
		$this->url .
		'api?mode=resume' .
		'&apikey=' .
			$this->apikey
		);
	}

	/**
	 * Check if the SAB cookies are in the User's browser.
	 *
	 * @return bool
	 */
	public function checkCookie()
	{
		$res = false;
		if (isset($_COOKIE['sabnzbd_' . $this->uid . '__apikey'])) {
			$res = true;
		}
		if (isset($_COOKIE['sabnzbd_' . $this->uid . '__host'])) {
			$res = true;
		}
		if (isset($_COOKIE['sabnzbd_' . $this->uid . '__priority'])) {
			$res = true;
		}
		if (isset($_COOKIE['sabnzbd_' . $this->uid . '__apitype'])) {
			$res = true;
		}

		return $res;
	}

	/**
	 * Creates the SAB cookies for the user's browser.
	 *
	 * @param $host
	 * @param $apikey
	 * @param $priority
	 * @param $apitype
	 */
	public function setCookie($host, $apikey, $priority, $apitype)
	{
		setcookie('sabnzbd_' . $this->uid . '__host', $host, time() + 2592000);
		setcookie('sabnzbd_' . $this->uid . '__apikey', $apikey, time() + 2592000);
		setcookie('sabnzbd_' . $this->uid . '__priority', $priority, time() + 2592000);
		setcookie('sabnzbd_' . $this->uid . '__apitype', $apitype, time() + 2592000);
	}

	/**
	 * Deletes the SAB cookies from the user's browser.
	 */
	public function unsetCookie()
	{
		setcookie('sabnzbd_' . $this->uid . '__host', '', time() - 2592000);
		setcookie('sabnzbd_' . $this->uid . '__apikey', '', time() - 2592000);
		setcookie('sabnzbd_' . $this->uid . '__priority', '', time() - 2592000);
		setcookie('sabnzbd_' . $this->uid . '__apitype', '', time() - 2592000);
	}
}
