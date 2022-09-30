<?php

namespace arc;
/**
 * Class route
 * A simple router.
 * @package arc
 */
class route {

    /**
     * Matches a path or url to a list of routes and calls the best matching route handler.
     * @param string $path
     * @param mixed $routes A tree of routes with handlers as nodeValue.
     * @return array|bool
     */
    public static function match($path, $routes) {
        $routes     = \arc\tree::expand($routes);
        $controller = \arc\tree::dive(
            $routes->cd($path),
            function($node) {
                if ( isset($node->nodeValue) ) {
                    return $node;
                }
            }
        );
        if ( $controller ) {
            $remainder = substr( $path, strlen($controller->getPath()) );
            if ( is_callable($controller->nodeValue) ) {
                $result = call_user_func($controller->nodeValue, $remainder);
            } else {
                $result = $controller->nodeValue;
            }
            return [
                'path' => $controller->getPath(),
                'remainder' => $remainder,
                'result' => $result
            ];
        }
        return false;
    }

}