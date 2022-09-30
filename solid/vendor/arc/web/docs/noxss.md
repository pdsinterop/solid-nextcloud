arc/noxss
=========

`arc/noxss` is an XSS attack detection and prevention class. It contains two methods, detect and prevent.
The `detect()` method must be called at the start of handling any request, e.g. in your front controller or router.
The `prevent()` method must be called at the end of handling any request.

Usage
-----
```php
    \arc\noxss::detect();

    // handle request normally

    \arc\noxss::prevent();
```

If any suspicious characters are found in any input argument, `detect()` will start an output buffer. `prevent()` will 
check that buffer. If any of the suspicious input arguments are detected as-is in the buffer, `prevent()` will send a 
'400 Bad Request' header and won't send the generated output.

If you want to handle the bad request yourself, you can pass a callback function to `prevent()`. It will only be called 
in the case of a bad request and the only argument to the callback is the generated output.

```php
    \arc\noxss::detect();

    // do your own stuff, load your routes, run your app, etc.

    \arc\noxss::prevent(function($output) {
        error_log('We are under attack!');
        header('400 Bad Request');
        echo '<h1>Bad Request, go home!</h1>';
    });
```

Although you can potentially try to fix the output and strip out any offending content, you shouldn't. Any kind of
'cleaning' you do, can and will be used against you. Smart attackers will use your cleaning routine to do
even more evil stuff. The only sure way to avoid an XSS attack is to completely skip the output.

`arc/noxss` doesn't tell you which input is the culprit. This is by design. There is no way to fix the input anyway.
If you want to log inputs, just grab whatever is in \_GET or \_POST or \_COOKIE and log that. If you do log something,
make sure it is the URL called so you can fix your code. The XSS `prevent()` will only trigger _if you did not filter 
user input before including it in your webpage!_

Warning
-------
This component is still not a 100% fix for XSS attacks. If you store unfiltered user input into your database or other
storage system and then in later requests send them out again unfiltered, this component won't help you. It only protects
against direct XSS attacks. You should never store content without proper validation and filtering.

There are systems that claim to automatically escape / filter inputs, but none of them are 100% foolproof. There are
too many different contexts in which output needs to be escaped slightly differently. Only when you know the exact context
(html, javascript, sql..) can you do this. All automated systems will fail if they get the context wrong. It only needs
to be wrong once for an attacker to abuse it.

Finally remember that most requests triggering the XSS `prevent()` call will most probably be innocent people with an 
apostrophe in their name or some other valid use case for 'suspicious' characters in their input. Don't jump to 
conclusions and tell them that they are evil hackers...