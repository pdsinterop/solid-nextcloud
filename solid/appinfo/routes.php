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
        ['name' => 'page#do_echo', 'url' => '/echo', 'verb' => 'POST'],
        ['name' => 'page#profile', 'url' => '/@{userId}/', 'verb' => 'GET'],
        ['name' => 'page#turtleProfile', 'url' => '/@{userId}/turtle', 'verb' => 'GET' ],
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
        ['name' => 'server#token', 'url' => '/token', 'verb' => 'GET'],
        ['name' => 'server#userinfo', 'url' => '/userinfo', 'verb' => 'GET'],
        ['name' => 'server#register', 'url' => '/register', 'verb' => 'POST'],
        ['name' => 'server#registeredClient', 'url' => '/register/{clientId}', 'verb' => 'GET']
    ]
];
