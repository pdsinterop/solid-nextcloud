<?php
/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\Solid\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#profile', 'url' => '/@{userId}/', 'verb' => 'GET'],
        ['name' => 'page#approval', 'url' => '/sharing/{clientId}', 'verb' => 'GET'],
        ['name' => 'page#handleRevoke', 'url' => '/revoke/{clientId}', 'verb' => 'GET'],
        ['name' => 'page#handleApproval', 'url' => '/sharing/{clientId}', 'verb' => 'POST'],
        ['name' => 'page#dataJson', 'url' => '/@{userId}/data.json', 'verb' => 'GET' ],
		
        ['name' => 'server#openid', 'url' => '/openid', 'verb' => 'GET'],
        ['name' => 'server#cors', 'url' => '/{path}', 'verb' => 'OPTIONS', 'requirements' => array('path' => '.+') ],
        ['name' => 'server#authorize', 'url' => '/authorize', 'verb' => 'GET'],
        ['name' => 'server#jwks', 'url' => '/jwks', 'verb' => 'GET'],
        ['name' => 'server#session', 'url' => '/session', 'verb' => 'GET'],
        ['name' => 'server#logout', 'url' => '/logout', 'verb' => 'GET'],
        ['name' => 'server#token', 'url' => '/token', 'verb' => 'POST'],
        ['name' => 'server#userinfo', 'url' => '/userinfo', 'verb' => 'GET'],
        ['name' => 'server#register', 'url' => '/register', 'verb' => 'POST'],
        ['name' => 'server#registeredClient', 'url' => '/register/{clientId}', 'verb' => 'GET'],

        ['name' => 'profile#handleGet', 'url' => '/@{userId}/profile{path}', 'verb' => 'GET', 'requirements' => array('path' => '.+')],
        ['name' => 'profile#handlePut', 'url' => '/@{userId}/profile{path}', 'verb' => 'PUT', 'requirements' => array('path' => '.+')],
        ['name' => 'profile#handlePatch', 'url' => '/@{userId}/profile{path}', 'verb' => 'PATCH', 'requirements' => array('path' => '.+')],
        ['name' => 'profile#handleHead', 'url' => '/@{userId}/profile{path}', 'verb' => 'HEAD', 'requirements' => array('path' => '.+')],

        ['name' => 'storage#handleGet', 'url' => '/@{userId}/storage{path}', 'verb' => 'GET', 'requirements' => array('path' => '.+')],
        ['name' => 'storage#handlePost', 'url' => '/@{userId}/storage{path}', 'verb' => 'POST', 'requirements' => array('path' => '.+')],
        ['name' => 'storage#handlePut', 'url' => '/@{userId}/storage{path}', 'verb' => 'PUT', 'requirements' => array('path' => '.+')],
        ['name' => 'storage#handleDelete', 'url' => '/@{userId}/storage{path}', 'verb' => 'DELETE', 'requirements' => array('path' => '.+')],
        ['name' => 'storage#handlePatch', 'url' => '/@{userId}/storage{path}', 'verb' => 'PATCH', 'requirements' => array('path' => '.+')],
        ['name' => 'storage#handleHead', 'url' => '/@{userId}/storage{path}', 'verb' => 'HEAD', 'requirements' => array('path' => '.+')],

        ['name' => 'calendar#handleGet', 'url' => '/@{userId}/calendar{path}', 'verb' => 'GET', 'requirements' => array('path' => '.+')],
        ['name' => 'calendar#handlePost', 'url' => '/@{userId}/calendar{path}', 'verb' => 'POST', 'requirements' => array('path' => '.+')],
        ['name' => 'calendar#handlePut', 'url' => '/@{userId}/calendar{path}', 'verb' => 'PUT', 'requirements' => array('path' => '.+')],
        ['name' => 'calendar#handleDelete', 'url' => '/@{userId}/calendar{path}', 'verb' => 'DELETE', 'requirements' => array('path' => '.+')],
        ['name' => 'calendar#handlePatch', 'url' => '/@{userId}/calendar{path}', 'verb' => 'PATCH', 'requirements' => array('path' => '.+')],
        ['name' => 'calendar#handleHead', 'url' => '/@{userId}/calendar{path}', 'verb' => 'HEAD', 'requirements' => array('path' => '.+')],

        ['name' => 'contacts#handleGet', 'url' => '/@{userId}/contacts{path}', 'verb' => 'GET', 'requirements' => array('path' => '.+')],
        ['name' => 'contacts#handlePost', 'url' => '/@{userId}/contacts{path}', 'verb' => 'POST', 'requirements' => array('path' => '.+')],
        ['name' => 'contacts#handlePut', 'url' => '/@{userId}/contacts{path}', 'verb' => 'PUT', 'requirements' => array('path' => '.+')],
        ['name' => 'contacts#handleDelete', 'url' => '/@{userId}/contacts{path}', 'verb' => 'DELETE', 'requirements' => array('path' => '.+')],
        ['name' => 'contacts#handlePatch', 'url' => '/@{userId}/contacts{path}', 'verb' => 'PATCH', 'requirements' => array('path' => '.+')],
        ['name' => 'contacts#handleHead', 'url' => '/@{userId}/contacts{path}', 'verb' => 'HEAD', 'requirements' => array('path' => '.+')],

        ['name' => 'app#appLauncher', 'url' => '/launcher/', 'verb' => 'GET'],
    ]
];
