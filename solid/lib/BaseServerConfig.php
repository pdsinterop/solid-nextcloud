<?php
	namespace OCA\Solid;

	use OCP\IConfig;

	class BaseServerConfig {
		private IConfig $config;

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
		public function setEncryptionKey($publicKey) {
			$this->config->setAppValue('solid','encryptionKey',$publicKey);
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

		/**
		 * @param string $clientId
		 * @return array|null
		 */
		public function getClientConfigById($clientId) {
			$clients = (array)$this->config->getAppValue('solid','clients');
			if (array_key_exists($clientId, $clients)) {
				return $clients[$clientId];
			}
			return null;
		}

		/**
		 * @return array|null
		 */
		public function getClients() {
			$configKeys = (array)$this->config->getAppKeys('solid');
			$clients = [];
			foreach ($configKeys as $key) {
				if (preg_match("/^client-([a-z0-9]+)$/", $key, $matches)) {
					$clientRegistration = json_decode($this->config->getAppValue('solid', $key, '{}'), true);
					$clients[] = [
						"clientId" => $matches[1],
						"clientName" => $clientRegistration['client_name']
					];
				}
			}
			return $clients;
		}

		/**
		 * @param array $clientConfig
		 * @return string
		 */
		public function saveClientConfig($clientId, $clientConfig) {
			$clients = (array)$this->config->getAppValue('solid', 'clients');
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

		public function saveClientRegistration($origin, $clientData) {
			$originHash = md5($origin);
			$existingRegistration = $this->getClientRegistration($originHash);
			if ($existingRegistration && isset($existingRegistration['redirect_uris'])) {
				foreach ($existingRegistration['redirect_uris'] as $uri) {
					$clientData['redirect_uris'][] = $uri;
				}
				$clientData['redirect_uris'] = array_unique($clientData['redirect_uris']);
			}

			$clientData['client_name'] = $origin;
			$clientData['client_secret'] = md5(random_bytes(32));
			$this->config->setAppValue('solid', "client-" . $originHash, json_encode($clientData));
			$this->config->setAppValue('solid', "client-" . $origin, json_encode($clientData));
			$this->saveClientConfig($originHash, $clientData);
			return $originHash;
		}

		public function removeClientRegistration($clientId) {
			$this->config->deleteAppValue('solid', "client-" . $clientId);
		}

		public function getClientRegistration($clientId) {
			$data = $this->config->getAppValue('solid', "client-" . $clientId, "{}");
			return json_decode($data, true);
		}
	}
