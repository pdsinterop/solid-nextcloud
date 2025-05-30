<?php

namespace OCA\Solid\Controller;

use OCA\Solid\ServerConfig;
use OCP\IURLGenerator;
use Psr\Http\Message\RequestInterface;

trait GetStorageUrlTrait
{
	//////////////////////////// GETTERS AND SETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\

	final public function setConfig(ServerConfig $config): void
	{
		$this->config = $config;
	}

	final public function setUrlGenerator(IURLGenerator $urlGenerator): void
	{
		$this->urlGenerator = $urlGenerator;
	}

	////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

	protected ServerConfig $config;
	protected IURLGenerator $urlGenerator;

	/////////////////////////////// PROTECTED API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	/**
	 * @FIXME: Add check for bob.nextcloud.local/solid/alice to throw 404
	 * @TODO: Use route without `~alice` in /apps/solid/~alice/profile/card#me when user-domains are enabled
	 */
	public function getStorageUrl($userId) {
		$routeUrl = $this->urlGenerator->linkToRoute(
			'solid.storage.handleHead',
			['userId' => $userId, 'path' => 'foo']
		);

		$storageUrl = $this->urlGenerator->getAbsoluteURL($routeUrl);

		$storageUrl = preg_replace('/foo$/', '', $storageUrl);

		if ($this->config->getUserSubDomainsEnabled()) {
			$url = parse_url($storageUrl);

			if (strpos($url['host'], $userId . '.') !== false) {
				$url['host'] = str_replace($userId . '.', '', $url['host']);
			}

			$url['host'] = $userId . '.' . $url['host']; // $storageUrl = $userId . '.' . $storageUrl;
			$storageUrl = $this->buildUrl($url);
		}

		return $storageUrl;
	}

	public function validateUrl(RequestInterface $request): bool {
		$isValid = false;

		$host = $request->getUri()->getHost();
		$path = $request->getUri()->getPath();
		$pathParts = explode('/', $path);

		$pathUsers = array_filter($pathParts, static function ($value) {
			return str_starts_with($value, '~');
		});

		if (count($pathUsers) === 1) {
			$pathUser = reset($pathUsers);
			$subDomainUser = explode('.', $host)[0];

			$isValid = $pathUser === '~' . $subDomainUser;
		}

		return $isValid;
	}

	////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	private function buildUrl(array $parts) {
		// @FIXME: Replace with existing more robust URL builder
		return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '') .
			(isset($parts['host']) ? "//{$parts['host']}" : '') .
			(isset($parts['port']) ? ":{$parts['port']}" : '') .
			(isset($parts['path']) ? "{$parts['path']}" : '') .
			(isset($parts['query']) ? "?{$parts['query']}" : '') .
			(isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
	}
}
