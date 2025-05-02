<?php
	namespace OCA\Solid;

	use OCP\IConfig;
	use OCP\IUserManager;
	use OCP\IUrlGenerator;
	use OCA\Solid\BaseServerConfig;
	
	/**
	 * @package OCA\Solid
	 */
	class ServerConfig extends BaseServerConfig {
		private IUrlGenerator $urlGenerator;
		private IUserManager $userManager;

		/**
		 * @param IConfig $config
		 * @param IUrlGenerator $urlGenerator
		 * @param IUserManager $userManager
		 */
		public function __construct(IConfig $config, IUrlGenerator $urlGenerator, IUserManager $userManager) {
			$this->config = $config;
			$this->userManager = $userManager;
			$this->urlGenerator = $urlGenerator;

			parent::__construct($config);
		}

		public function getAllowedClients($userId) {
			return json_decode($this->config->getUserValue($userId, 'solid', "allowedClients", "[]"), true);
		}

		public function addAllowedClient($userId, $clientId) {
			$allowedClients = $this->getAllowedClients($userId);
			$allowedClients[] = $clientId;
			$this->config->setUserValue($userId, "solid", "allowedClients", json_encode($allowedClients));
		}
		public function removeAllowedClient($userId, $clientId) {
			$allowedClients = $this->getAllowedClients($userId);
			$allowedClients = array_diff($allowedClients, array($clientId));
			$this->config->setUserValue($userId, "solid", "allowedClients", json_encode($allowedClients));
		}

		public function getProfileData($userId) {
			return $this->config->getUserValue($userId, "solid", "profileData", "");
		}
		public function setProfileData($userId, $profileData) {
			$this->config->setUserValue($userId, "solid", "profileData", $profileData);
			
			if ($this->userManager->userExists($userId)) {
				$graph = new \EasyRdf\Graph();
				$graph->parse($profileData, 'turtle');
				$data = $graph->toRdfPhp();
				$subject = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.profile.handleGet", array("userId" => $userId, "path" => "/card"))) . "#me";
				$subjectData = $data[$subject];
				$fields = array(
					"name" => $subjectData['http://xmlns.com/foaf/0.1/name'][0]['value']
				);

				// and write them to the user;
				$user = $this->userManager->get($userId);
				$user->setDisplayName($fields['name']);
			}
		}
	}
