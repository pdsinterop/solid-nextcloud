<?php
	namespace OCA\Solid;

	use OCP\IConfig;

	/**
	 * @package OCA\Solid
	 */
	class ServerConfig {

		/** @var IConfig */
		private $config;

		/**
		 * @param IConfig $config
		 */
		public function __construct(IConfig $config) {
			$this->config = $config;
		}

		/**
		 * @return string
		 */
		public function getPrivateKey() {
			$result = $this->config->getAppValue('solid','privateKey');
			if (!$result) {
				// generate and save a new set if we don't have a private key;
				$keys = $this->generateKeySet();
				$this->config->setAppValue('solid','privateKey',$keys['privateKey']);
				$this->config->setAppValue('solid','encryptionKey',$keys['encryptionKey']);
			}
			return $this->config->getAppValue('solid','privateKey');
		}

		/**
		 * @param string $privateKey
		 */
		public function setPrivateKey($privateKey) {
			$this->config->setAppValue('solid','privateKey',$privateKey);
		}

		/**
		 * @return string
		 */
		public function getEncryptionKey() {
			return $this->config->getAppValue('solid','encryptionKey');
		}

		/**
		 * @param string $publicKey
		 */
		public function setEncryptionKey($encryptionKey) {
			$this->config->setAppValue('solid','encryptionKey',$publicKey);
		}

		/**
		 * @param string $clientId
		 * @return array
		 */
		public function getClientConfigById($clientId) {
			$clients = (array)$this->config->getAppValue('solid','clients');
			if (array_key_exists($clientId, $clients)) {
				return $clients[$clientId];
			}
			return null;
		}

		/**
		 * @param array $clientConfig
		 * @return string
		 */
		public function saveClientConfig($clientConfig) {
			$clients = (array)$this->config->getAppValue('solid', 'clients');
			$clientId = uuidv4();
			$clients[$clientId] = $clientConfig;
			$this->config->setAppValue('solid','clients', $clients);
			return $clientId;
		}

		/**
		 * @param string $clientId
		 * @param array $scopes
		 */
		public function addScopesToClient($clientId, $scopes) {
			$clientScopes = $this->getClientScopes($clientId);
			$clientScopes = array_unique(array_merge($clientScopes, $scopes));
			$this->setClientScopes($clientId, $clientScopes);
		}

		/**
		 * @param string $clientId
		 * @param array $scopes
		 */
		public function setClientScopes($clientId, $scopes) {
			$clientScopes = (array)$this->config->getAppValue('solid', 'clientScopes');
			$clientScopes[$clientId] = $scopes;
			$this->config->setAppValue('solid', 'clientScopes', $clientScopes);
		}

		/**
		 * @param string $clientId
		 * @return array
		 */
		public function getClientScopes($clientId) {
			$clientScopes = (array)$this->config->getAppValue('solid', 'clientScopes');
			if (array_key_exists($clientId, $clientScopes)) {
				return $clientScopes[$clientId];
			}
			return [];
		}

		/**
		 * @param string $clientId
		 */
		public function removeClientConfig($clientId) {
			$clients = (array)$this->config->getAppValue('solid', 'clients');
			unset($clients[$clientId]);
			$this->config->setAppValue('solid','clients', $clients);
			$scopes = (array)$this->config->getAppValue('solid', 'clientScopes');
			unset($scopes[$clientId]);
			$this->config->setAppValue('solid', 'clientScopes', $scopes);
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

		public function saveClientRegistration($origin, $clientData) {
			$originHash = md5($origin);
			$existingRegistration = $this->getClientRegistration($originHash);
			if ($existingRegistration && isset($existingRegistration['client_name'])) {
				return $originHash;
			}

			$clientData['client_name'] = $origin;
			$clientData['client_secret'] = md5(random_bytes(32));
			$this->config->setAppValue('solid', "client-" . $originHash, json_encode($clientData));
			return $originHash;
		}

		public function getClientRegistration($clientId) {
			$data = $this->config->getAppValue('solid', "client-" . $clientId, "{}");
			return json_decode($data, true);
		}

		private function generateKeySet() {
			$config = array(
				"digest_alg" => "sha256",
				"private_key_bits" => 2048,
				"private_key_type" => OPENSSL_KEYTYPE_RSA,
			);
			// Create the private and public key
			$key = openssl_pkey_new($config);

			// Extract the private key from $key to $privateKey
			openssl_pkey_export($key, $privateKey);
			$encryptionKey = base64_encode(random_bytes(32));
			$result = array(
				"privateKey" => $privateKey,
				"encryptionKey" => $encryptionKey
			);
			return $result;
		}
	}