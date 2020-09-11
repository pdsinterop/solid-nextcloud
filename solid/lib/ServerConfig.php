<?php
	namespace OCA\Pdsinterop;

	use OCP\IConfig;

	/**
	 * @package OCA\Pdsinterop
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
			return $this->config->getAppValue('pdsinterop','privateKey');
		}

		/** @param string $privateKey */
		public function setPrivateKey($privateKey) {
			$this->config->setAppValue('pdsinterop','privateKey',$privateKey);
		}

		public function getPublicKey() {
			return $this->config->getAppValue('pdsinterop','publicKey');
		}

		/** @param string $publicKey */
		public function setPublicKey($publicKey) {
			$this->config->setAppValue('pdsinterop','publicKey',$publicKey);
		}

	}