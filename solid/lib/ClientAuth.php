<?php
	namespace OCA\Solid;

	use OCP\User\Backend\ABackend;
	use OCP\User\Backend\ICheckPasswordBackend;
	
	/**
	 * @package OCA\Solid
	 */
	class ClientAuth extends ABackend implements ICheckPasswordBackend {
	        private $config;

		public function __construct(IConfig $config) {
		    $this->config = $config;
		}

                public function checkPassword(string $username, string $password) {
                    return true;
                }
	}
