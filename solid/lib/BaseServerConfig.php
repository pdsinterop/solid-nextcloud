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
	}
