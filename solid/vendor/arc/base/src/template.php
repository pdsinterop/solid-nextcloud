<?php

namespace arc;

class template
{
    /**
     * This method replaces {$key} entries in a string with the value of that key in an arguments list
     * If the key isn't in the arguments array, it will remain in the returned string as-is.
     * The arguments list may be an object or an array and the values may be basic types or callable.
     * In the latter case, the key will be substituted with the return value of the callable. The callable
     * is called with the key matched.
     * <code>
     *   $parsedTemplate = \arc\template::substitute( 'Hello {$world} {$foo}', [ 'world' => 'World!' ] );
     *   // => 'Hello World! {$foo}'
     *</code>
     * @param string $template
     * @param array $arguments
     * @return string
     */
    public static function substitute($template, $arguments)
    {
        if (is_object($arguments) && !($arguments instanceof \ArrayObject )) {
            $arguments = get_object_vars( $arguments );
        }
        $regex = '\{\$(' . implode( '|', array_keys( (array) $arguments ) ) . ')\}';

        return preg_replace_callback( '/'.$regex.'/', function ($matches) use ($arguments) {
            $argument = $arguments[ $matches[1] ];
            if (is_callable( $argument )) {
                $argument = call_user_func( $argument, $matches[1] );
            }

            return $argument;
        }, $template );
    }

    /**
     * This method is identical to \arc\template::substitute but it removes any keys left over.
     * <code>
     *   $parsedTemplate = \arc\template::substituteAll( 'Hello {$world} {$foo}', [ 'world' => 'World!' ] );
     *   // => 'Hello World! '
     *</code>
     * @param string $template
     * @param array $arguments
     * @return string
     */
    public static function substituteAll($template, $arguments)
    {
        return preg_replace('/\{\$.*\}/', '', self::substitute( $template, $arguments ) );
    }

    public static function compile($template)
    {
        $func = <<<EOF
return function(\$arguments) { 
    extract( \$arguments, EXTR_REFS );
    ob_start();
    ?>$template<?php
    \$result = ob_get_contents();
    ob_end_clean();

    return \$result;
};
EOF;
        return eval( $func );
    }

    public static function compileSubstitute($template)
    {
        $template = preg_replace('/EOTEMPLATE;.*$/', '', $template);

        return self::compile( "<"."?php\n echo <<<EOTEMPLATE\n".$template."\nEOTEMPLATE;\n ?".">");
    }

    public static function run($template, $arguments)
    {
        if (!is_callable($template)) {
            $template = self::compile( $template );
        }

        return $template( $arguments );
    }
}
