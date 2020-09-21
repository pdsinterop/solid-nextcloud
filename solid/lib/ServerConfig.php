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
		 * Config constructor
		 * @param IConfig $config
		 */
		public function __construct(IConfig $config) {
			$this->config = $config;
		}

		public function getPrivateKey() {
			return $this->config->getAppValue('solid','privateKey');
		}

		/** @param string $privateKey */
		public function setPrivateKey($privateKey) {
			$this->config->setAppValue('solid','privateKey',$privateKey);
		}

		public function getEncryptionKey() {
			return $this->config->getAppValue('solid','encryptionKey');
		}

		/** @param string $publicKey */
		public function setEncryptionKey($encryptionKey) {
			$this->config->setAppValue('solid','encryptionKey',$publicKey);
		}

	}