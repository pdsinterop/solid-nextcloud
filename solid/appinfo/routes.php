<?php
/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\PDSInterop\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
    'routes' => [
        ['name' => 'page#openid', 'url' => '/openid', 'verb' => 'GET'],
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#do_echo', 'url' => '/echo', 'verb' => 'POST'],
        ['name' => 'page#profile', 'url' => '/@{userId}/', 'verb' => 'GET'],
        ['name' => 'page#turtleProfile', 'url' => '/@{userId}/turtle', 'verb' => 'GET' ],
        ['name' => 'page#dataJson', 'url' => '/@{userId}/data.json', 'verb' => 'GET' ],
        ['name' => 'page#cors', 'url' => '/{path}', 'verb' => 'OPTIONS', 'requirements' => array('path' => '.+') ],

        ['name' => 'page#authorize', 'url' => '/authorize', 'verb' => 'GET'],
        ['name' => 'page#jwks', 'url' => '/jwks', 'verb' => 'GET'],
        ['name' => 'page#session', 'url' => '/session', 'verb' => 'GET'],
        ['name' => 'page#logout', 'url' => '/logout', 'verb' => 'GET'],
        ['name' => 'page#token', 'url' => '/token', 'verb' => 'GET'],
        ['name' => 'page#userinfo', 'url' => '/userinfo', 'verb' => 'GET'],
        ['name' => 'page#register', 'url' => '/register', 'verb' => 'POST']
    ]
];
