<?php
namespace OCA\Solid\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;
use OCA\Solid\BaseServerConfig;

class SolidAdmin implements ISettings {
    private IL10N $l;
    private IConfig $config;
    private BaseServerConfig $serverConfig;

    public function __construct(IConfig $config, IL10N $l) {
        $this->config = $config;
        $this->l = $l;
        $this->serverConfig = new BaseServerConfig($config);
    }

    /**
     * @return TemplateResponse
     */
    public function getForm() {
        $allClients = $this->serverConfig->getClients();

        $parameters = [
            'privateKey'    => $this->serverConfig->getPrivateKey(),
	    'encryptionKey' => $this->serverConfig->getEncryptionKey(),
	    'clients' => $allClients
        ];

        return new TemplateResponse('solid', 'admin', $parameters, '');
    }

    public function getSection() {
        return 'solid'; // Name of the previously created section.
    }

    /**
     * @return int whether the form should be rather on the top or bottom of
     * the admin section. The forms are arranged in ascending order of the
     * priority values. It is required to return a value between 0 and 100.
     *
     * E.g.: 70
     */
    public function getPriority() {
        return 70;
    }
}
