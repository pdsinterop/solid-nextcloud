<?php

namespace OCA\Solid\Controller;

use OCA\Solid\BaseServerConfig;
use OCP\IURLGenerator;

trait GetStorageUrlTrait
{
	//////////////////////////// GETTERS AND SETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\

	final public function setConfig(BaseServerConfig $config): void
	{
		$this->config = $config;
	}

	final public function setUrlGenerator(IURLGenerator $urlGenerator): void
	{
		$this->urlGenerator = $urlGenerator;
	}

	////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

	protected BaseServerConfig $config;
	protected IURLGenerator $urlGenerator;

	/////////////////////////////// PROTECTED API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	/**
	 * @FIXME: Deduplicate multiple declarations of getStorageUrl()
	 * @FIXME: Add check for bob.nextcloud.local/solid/alice to throw 404
	 * @TODO: Use route without `@alice` in /apps/solid/@alice/profile/card#me when user-domains are enabled
	 */
	protected function getStorageUrl($userId) {
		$routeUrl = $this->urlGenerator->linkToRoute(
			'solid.storage.handleHead',
			['userId' => $userId, 'path' => 'foo']
		);

		$storageUrl = $this->urlGenerator->getAbsoluteURL($routeUrl);

		// (?) $storageUrl = preg_replace('/foo$/', '', $storageUrl);
		$storageUrl = preg_replace('/foo$/', '/', $storageUrl);

		// @FIXME: $this->getUserSubDomainsEnabled should contain true/false from (?) somewhere
		if ($this->config->getUserSubDomainsEnabled()) {
			// @FIXME: Check whether we are already on a domain that starts with $userId,
			//          in which case it should not be prepended again
			$url = parse_url($storageUrl);
			$url['host'] = $userId . '.' . $url['host']; // $storageUrl = $userId . '.' . $storageUrl;
			$storageUrl = $this->build_url($url);
		}

		return $storageUrl;
	}

	////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	private function build_url(array $parts) {
		// @FIXME: Replace with existing more robust URL builder
		return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '') .
			(isset($parts['host']) ? "//{$parts['host']}" : '') .
			(isset($parts['port']) ? ":{$parts['port']}" : '') .
			(isset($parts['path']) ? "{$parts['path']}" : '') .
			(isset($parts['query']) ? "?{$parts['query']}" : '') .
			(isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
	}
}
