<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth;

use Pdsinterop\Rdf\Enum\Format as Format;

class WAC {
	private $filesystem;
	private $baseUrl;
	private $basePath;

	public function __construct($filesystem) {
		$this->filesystem = $filesystem;
		$this->baseUrl = '';
		$this->basePath = '';
	}
	
	public function setBaseUrl($url) {
		$this->baseUrl = $url;
		$serverRequest = new \Laminas\Diactoros\ServerRequest(array(),array(), $url);
		$this->basePath = $serverRequest->getUri()->getPath();
	}

	public function addWACHeaders($request, $response, $webId) {
		$currentFormat = $this->filesystem->getAdapter()->getFormat(); // keep the format so we can put it back later. prevents the acl file from being converted;
		$this->filesystem->getAdapter()->setFormat('');
		$uri = $request->getUri();
		$userGrants = $this->getWACGrants($this->getUserGrants($uri, $webId), $uri);
		$publicGrants = $this->getWACGrants($this->getPublicGrants($uri), $uri);

		$wacHeaders = array();
		if ($userGrants) {
			$wacHeaders[] = "user=\"$userGrants\"";
		}
		if ($publicGrants) {
			$wacHeaders[] = "public=\"$publicGrants\"";
		}
		
		$response = $response->withHeader("Link", '<.acl>; rel="acl"');
		$response = $response->withHeader("WAC-Allow", implode(",", $wacHeaders));
		$this->filesystem->getAdapter()->setFormat($currentFormat);
		return $response;
	}
	
	/**
	 * Checks the requested filename (path+name) and user (webid) to see if the request
	 * is allowed to continue, according to the web acl
	 * see: https://github.com/solid/web-access-control-spec
	 */

	public function isAllowed($request, $webId, $origin=false, $allowedOrigins=[]) {
		$requestedGrants = $this->getRequestedGrants($request);
		$uri = $request->getUri();
		$parentUri = $this->getParentUri($uri);

	// @FIXME: $origin can be anything at this point, null, string, array, bool
	//	 This causes trouble downstream where an unchecked `parse_url($origin)['host'];` occurs

	foreach ($requestedGrants as $requestedGrant) {
			switch ($requestedGrant['type']) {
				case "resource":
					if ($this->isPublicGranted($requestedGrant['grants'], $uri)) {
						return true;
					}
					if (!$this->isUserGranted($requestedGrant['grants'], $uri, $webId)) {
						return false;
					}
					if (!$this->isOriginGranted($requestedGrant['grants'], $uri, $origin, $allowedOrigins)) {
						return false;
					}
				break;
				case "parent":
					if ($this->isPublicGranted($requestedGrant['grants'], $uri)) {
						return true;
					}
					if (!$this->isUserGranted($requestedGrant['grants'], $parentUri, $webId)) {
						return false;
					}
					if (!$this->isOriginGranted($requestedGrant['grants'], $parentUri, $origin, $allowedOrigins)) {
						return false;
					}
				break;
			}
		}
		return true;
	}

	private function getPathFromUri($uri) {
		$path = $uri->getPath();
		if ($this->basePath) {
			$path = str_replace($this->basePath, '', $path);
		}
		return $path;
	}
	private function checkGrants($requestedGrants, $uri, $grants) {
		if (!$requestedGrants) {
			return true;
		}
		if (is_array($grants)) {
			foreach ($requestedGrants as $requestedGrant) {
				if (isset($grants['accessTo']) && isset($grants['accessTo'][$requestedGrant]) && $this->arePathsEqual($grants['accessTo'][$requestedGrant], $uri)) {
					return true;
				} else if (isset($grants['default']) && isset($grants['default'][$requestedGrant])) {
					if ($this->arePathsEqual($grants['default'][$requestedGrant], $uri)) {
						return false; // only use default for children, not for an exact match;
					}
					return true;
				}
			}
		}
		return false;
	}

	private function isPublicGranted($requestedGrants, $uri) {
		// error_log("REQUESTED GRANT: " . join(" or ", $requestedGrants) . " on $uri");
		$grants = $this->getPublicGrants($uri);
		// error_log("GRANTED GRANTS for public: " . json_encode($grants));
		return $this->checkGrants($requestedGrants, $uri, $grants);
	}

	private function isUserGranted($requestedGrants, $uri, $webId) {
		// error_log("REQUESTED GRANT: " . join(" or ", $requestedGrants) . " on $uri");
		$grants = $this->getUserGrants($uri, $webId);
		// error_log("GRANTED GRANTS for user $webId: " . json_encode($grants));
		return $this->checkGrants($requestedGrants, $uri, $grants);
	}
	
	private function isOriginGranted($requestedGrants, $uri, $origin, $allowedOrigins) {
	if (is_array($origin)) {
	    $origin = reset($origin);
	}

		if (!$origin) {
			return true;
		}

		$parsedOrigin = parse_url($origin)['host'];
		if (
			in_array($parsedOrigin, $allowedOrigins, true) ||
			in_array($origin, $allowedOrigins, true)
		) {
			return true;
		}
		//error_log("REQUESTED GRANT: " . join(" or ", $requestedGrants) . " on $uri");
		$grants = $this->getOriginGrants($uri, $origin);
		//error_log("GRANTED GRANTS for origin $origin: " . json_encode($grants));
		return $this->checkGrants($requestedGrants, $uri, $grants);
	}

	private function getPublicGrants($resourceUri) {
		$resourcePath = $this->getPathFromUri($resourceUri);
		$aclPath = $this->getAclPath($resourcePath);
		if (!$aclPath) {
			return array();
		}
		
		$acl = $this->filesystem->read($aclPath);

		$graph = new \EasyRdf\Graph();

		// error_log("PARSE ACL from $aclPath with base " . $this->getAclBase($aclPath));
		$graph->parse($acl, Format::TURTLE, $this->getAclBase($aclPath));
		
		$grants = array();

		$foafAgent = "http://xmlns.com/foaf/0.1/Agent";
		$matching = $graph->resourcesMatching('http://www.w3.org/ns/auth/acl#agentClass');
		foreach ($matching as $match) {
			$agentClass = $match->get("<http://www.w3.org/ns/auth/acl#agentClass>");
			if ($agentClass == $foafAgent) {
				$accessTo = $match->get("<http://www.w3.org/ns/auth/acl#accessTo>");
				$default = $match->get("<http://www.w3.org/ns/auth/acl#default>");
				$modes = $match->all("<http://www.w3.org/ns/auth/acl#mode>");
				if ($default) {
					foreach ($modes as $mode) {
						$grants["default"][$mode->getUri()] = $default->getUri();
					}
				}
				if ($accessTo) {
					foreach ($modes as $mode) {
						$grants["accessTo"][$mode->getUri()] = $accessTo->getUri();
					}
				}
			}
		}
		return $grants;
	}	

	private function getUserGrants($resourceUri, $webId) {
		$resourcePath = $this->getPathFromUri($resourceUri);
		$aclPath = $this->getAclPath($resourcePath);
		if (!$aclPath) {
			return array();
		}
		$acl = $this->filesystem->read($aclPath);

		$graph = new \EasyRdf\Graph();
		$graph->parse($acl, Format::TURTLE, $this->getAclBase($aclPath));
		
		// error_log("GET GRANTS for $webId");

		$grants = $this->getPublicGrants($resourceUri);
		
		// Then get grants that are valid for any authenticated agent;
		$authenticatedAgent = "http://www.w3.org/ns/auth/acl#AuthenticatedAgent";
		$matching = $graph->resourcesMatching('http://www.w3.org/ns/auth/acl#agentClass');
		foreach ($matching as $match) {
			$agentClass = $match->get("<http://www.w3.org/ns/auth/acl#agentClass>");
			if ($agentClass == $authenticatedAgent) {
				$accessTo = $match->get("<http://www.w3.org/ns/auth/acl#accessTo>");
				$default = $match->get("<http://www.w3.org/ns/auth/acl#default>");
				$modes = $match->all("<http://www.w3.org/ns/auth/acl#mode>");
				if ($default) {
					foreach ($modes as $mode) {
						$grants["default"][$mode->getUri()] = $default->getUri();
					}
				}
				if ($accessTo) {
					foreach ($modes as $mode) {
						$grants["accessTo"][$mode->getUri()] = $accessTo->getUri();
					}
				}
			}
		}

		// Then add grants for this specific user;
		$matching = $graph->resourcesMatching('http://www.w3.org/ns/auth/acl#agent');
		//error_log("MATCHING " . sizeof($matching));
		// Find all grants machting our webId;
		foreach ($matching as $match) {
			$agent = $match->get("<http://www.w3.org/ns/auth/acl#agent>");
			if ($agent == $webId) {
				$accessTo = $match->get("<http://www.w3.org/ns/auth/acl#accessTo>");
				//error_log("$webId accessTo $accessTo");
				$default = $match->get("<http://www.w3.org/ns/auth/acl#default>");
				$modes = $match->all("<http://www.w3.org/ns/auth/acl#mode>");
				if ($default) {
					foreach ($modes as $mode) {
						$grants["default"][$mode->getUri()] = $default->getUri();
					}
				}
				if ($accessTo) {
					foreach ($modes as $mode) {
						$grants["accessTo"][$mode->getUri()] = $accessTo->getUri();
					}
				}
			}
		}

		return $grants;
	}

	private function getOriginGrants($resourceUri, $origin) {
		$resourcePath = $this->getPathFromUri($resourceUri);
		$aclPath = $this->getAclPath($resourcePath);
		if (!$aclPath) {
			return array();
		}
		$acl = $this->filesystem->read($aclPath);

		$graph = new \EasyRdf\Graph();
		$graph->parse($acl, Format::TURTLE, $this->getAclBase($aclPath));

		// error_log("GET GRANTS for $origin");

		$grants = array();
		$matching = $graph->resourcesMatching('http://www.w3.org/ns/auth/acl#origin');
		//error_log("MATCHING " . sizeof($matching));
		// Find all grants machting our origin;
		foreach ($matching as $match) {
			$grantedOrigin = $match->get("<http://www.w3.org/ns/auth/acl#origin>");
			if ($grantedOrigin == $origin) {
				$accessTo = $match->get("<http://www.w3.org/ns/auth/acl#accessTo>");
				//error_log("$origin accessTo $accessTo");
				$default = $match->get("<http://www.w3.org/ns/auth/acl#default>");
				$modes = $match->all("<http://www.w3.org/ns/auth/acl#mode>");
				if ($default) {
					foreach ($modes as $mode) {
						$grants["default"][$mode->getUri()] = $default->getUri();
					}
				}
				if ($accessTo) {
					foreach ($modes as $mode) {
						$grants["accessTo"][$mode->getUri()] = $accessTo->getUri();
					}
				}
			}
		}

		return $grants;
	}

	private function getAclPath($path) {
		$path = $this->normalizePath($path);
		// get the filename from the request
		$filename = basename($path);
		$path = dirname($path);
		
		// error_log("REQUESTED PATH: $path");
		// error_log("REQUESTED FILE: $filename");

		$aclOptions = array(
			$this->normalizePath($path.'/'.$filename.'.acl'),
			$this->normalizePath($path.'/'.$filename.'/.acl'),
			$this->normalizePath($path.'/.acl'),
		);

		foreach ($aclOptions as $aclPath) {
			if (
				$this->filesystem->has($aclPath)
		&& $this->filesystem->read($aclPath) !== false
			) {
				return $aclPath;
			}
		}

		//error_log("Seeking .acl from $path");
		// see: https://github.com/solid/web-access-control-spec#acl-inheritance-algorithm
		// check for acl:default predicate, if not found, continue searching up the directory tree
		return $this->getParentAcl($path);
	}
	private function normalizePath($path) {
		return preg_replace("|//|", "/", $path);
	}
	private function getParentAcl($path) {
		//error_log("GET PARENT ACL $path");
		if ($this->filesystem->has($path.'/.acl')) {
			//error_log("CHECKING ACL FILE ON $path/.acl");
			return $path . "/.acl";
		}
		$parent = dirname($path);
		if ($parent == $path) {
			return false;
		} else {
			return $this->getParentAcl($parent);
		}
	}

	public function getRequestedGrants($request) {
		/*
			Build up the grants that are accepted as valid. The structure is as follows:
			- Each entry of the result is treated as an 'and'.
			- Each entry want a grant for either 'resource' or 'parent'
			- Each entry contains a list of grants that can satisfy the request, treated as an 'or';

			Examples:
			A request that requires 'read' and 'write' on the targeted resource:
			[
				["type" => "resource", "grants" => ["http://www.w3.org/ns/auth/acl#Read"]],
				["type" => "resource", "grants" => ["http://www.w3.org/ns/auth/acl#Write"]]
			]

			A request that requires 'write' on the resource and 'append' on the parent:
			[
				["type" => "resource", "grants" => ["http://www.w3.org/ns/auth/acl#Write"]],
				["type" => "parent", "grants" => ["http://www.w3.org/ns/auth/acl#Append"]]
			]

			A request that requires 'append' or 'write' on the resource
			[
				["type" => "resource", "grants" => ["http://www.w3.org/ns/auth/acl#Append", "http://www.w3.org/ns/auth/acl#Write"]]
			]
		*/
		$method = strtoupper($request->getMethod());
		$path = $request->getUri()->getPath();
		if ($this->basePath) {
			$path = str_replace($this->basePath, '', $path);
		}

		// Special case: restrict access to all .acl files.
		// Control is needed to do anything with them,
		// having Control allows all operations.
		if (preg_match('/.acl$/', $path)) {
			return array(
				array(
					"type" => "resource",
					"grants" => array('http://www.w3.org/ns/auth/acl#Control')
				)
			);
		}

		switch ($method) {
			case "GET":
			case "HEAD":
				return array(
					array(
						"type" => "resource",
						"grants" => array('http://www.w3.org/ns/auth/acl#Read')
					)
				);
			break;
			case "DELETE":
				return array(
					array(
						"type" => "resource",
						"grants" => array('http://www.w3.org/ns/auth/acl#Write')
					),
					array(
						"type" => "parent",
						"grants" => array('http://www.w3.org/ns/auth/acl#Write')
					)
				);
			break;
			case "PUT":
				if ($this->filesystem->has($path)) {
					return array(
						array(
							"type" => "resource",
							"grants" => array('http://www.w3.org/ns/auth/acl#Write')
						)
					);
				} else {
					// FIXME: to add a new file, Append is needed on the parent container;
					return array(
						array(
							"type" => "resource",
							"grants" => array('http://www.w3.org/ns/auth/acl#Write')
						),
						array(
							"type" => "parent",
							"grants" => array(
								'http://www.w3.org/ns/auth/acl#Append',
								'http://www.w3.org/ns/auth/acl#Write'
							)
						)
					);
				}
			break;
			case "POST":
				return array(
					array(
						"type" => "resource",
						"grants" => array(
							'http://www.w3.org/ns/auth/acl#Write', // We need 'append' for this, but because Write trumps Append, also allow it when we have Write;
							'http://www.w3.org/ns/auth/acl#Append'
						)
					)
				);
			break;
			case "PATCH";
				$grants = array();
				if (!$this->filesystem->has($path)) {
					$grants[] = array(
						"type" => "parent",
						"grants" => array(
							'http://www.w3.org/ns/auth/acl#Append',
							'http://www.w3.org/ns/auth/acl#Write'
						)
					);
				}

				$body = $request->getBody()->getContents();
				$request->getBody()->rewind();

				// FIXME: determine the actual patch types instead of using a string match;
				if (strstr($body, "deletes")) {
					$grants[] = array(
						"type" => "resource",
						"grants" => array('http://www.w3.org/ns/auth/acl#Write')
					);
					// To delete a triple from a resource, you need to be able to know that the triple is there.
					// which requires Read;
					$grants[] = array(
						"type" => "resource",
						"grants" => array('http://www.w3.org/ns/auth/acl#Read')
					);
				}
				if (strstr($body, "inserts")) {
					if ($this->filesystem->has($path)) {
						$grants[] = array(
							"type" => "resource",
							"grants" => array(
								'http://www.w3.org/ns/auth/acl#Append',
								'http://www.w3.org/ns/auth/acl#Write'
							)
						);
					} else {
						$grants[] = array(
							"type" => "resource",
							"grants" => array(
								'http://www.w3.org/ns/auth/acl#Write'
							)
						);
					}
				}
				return $grants;
			break;
		}
	}

	private function arePathsEqual($grantPath, $requestPath) {
		// error_log("COMPARING GRANTPATH: [" . $grantPath. "]");
		// error_log("COMPARING REQPATH: [" . $requestPath . "]");
		return $grantPath == $requestPath;
	}

	private function getParentUri($uri) {
		$path = $uri->getPath();
		if ($path == "/") {
			return $uri;
		}
		if ($path == $this->basePath) {
			return $uri;
		}
		
		$parentPath = dirname($path) . '/';
		
		$localPath = str_replace($this->basePath, '', $parentPath);
		if ($localPath == "/") {
			return $uri->withPath($parentPath);
		} elseif ($this->filesystem->has($localPath)) {
			return $uri->withPath($parentPath);
		} else {
			return $this->getParentUri($uri->withPath($parentPath));
		}
	}
	private function getWACGrants($grants, $uri) {
		$wacGrants = array();
		if (!isset($grants['accessTo'])) {
			$grants['accessTo'] = [];
		}
		if (!isset($grants['default'])) {
			$grants['default'] = [];
		}
		foreach ((array)$grants['accessTo'] as $grant => $grantedUri) {
			if ($this->arePathsEqual($grantedUri, $uri)) {
				$wacGrants[] = $this->grantToWac($grant);
			}
		}
		foreach ((array)$grants['default'] as $grant => $grantedUri) {
			if (!$this->arePathsEqual($grantedUri, $uri)) {
				$wacGrants[] = $this->grantToWac($grant);
			}
		}

		return implode(" ", $wacGrants);
	}
	private function grantToWac($grant) {
		return strtolower(explode("#", $grant)[1]); // http://www.w3.org/ns/auth/acl#Read => read
	}

	private function getAclBase($aclPath) {
		return $this->baseUrl . $this->normalizePath(dirname($aclPath) . "/");
	}
}
