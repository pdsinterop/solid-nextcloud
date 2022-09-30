<?php

// =============================================================================
// This part of the code should usually be handled by a bootstrap or framework
// -----------------------------------------------------------------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
ob_start();

require_once __DIR__ . '/../vendor/autoload.php';

// -----------------------------------------------------------------------------
// Create Request and Response objects
// -----------------------------------------------------------------------------
$request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals(
    $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
);
$response = new \Laminas\Diactoros\Response();

// -----------------------------------------------------------------------------
// Create the Filesystem
// -----------------------------------------------------------------------------
$formats = new \Pdsinterop\Rdf\Formats();

$graph = new \EasyRdf_Graph();

$serverUri = '';
if (isset($serverParams['SERVER_NAME'])) {
    $serverUri = "http://" . $serverParams['SERVER_NAME'] . $serverParams['REQUEST_URI'] ?? '';
}

$rdfAdapter = new \Pdsinterop\Rdf\Flysystem\Adapter\Rdf(
    new \League\Flysystem\Adapter\Local(__DIR__ . '/../tests/fixtures'),
    $graph,
    $formats,
    $serverUri
);

$filesystem = new \League\Flysystem\Filesystem($rdfAdapter);

$filesystem->addPlugin(new \Pdsinterop\Rdf\Flysystem\Plugin\AsMime($formats));
$filesystem->addPlugin(new \Pdsinterop\Rdf\Flysystem\Plugin\ReadRdf($graph));
// =============================================================================


// =============================================================================
// Create the Resource CRUD Server
// -----------------------------------------------------------------------------
$server = new \Pdsinterop\Solid\Resources\Server($filesystem, $response);
// =============================================================================


// =============================================================================
// Handle requests
// -----------------------------------------------------------------------------
$path = $request->getUri()->getPath();

$target = $request->getMethod() . $request->getRequestTarget();

if (strpos($path, '/data/') === 0) {
    /*/ Remove the `/data` prefix from the path /*/
    $changedPath = substr($request->getUri()->getPath(), 5);
    $request = $request->withUri($request->getUri()->withPath($changedPath));

    $response = $server->respondToRequest($request);
} elseif ($target === 'GET/') {
    $fileHandle = fopen(__FILE__, 'rb');
    fseek($fileHandle, __COMPILER_HALT_OFFSET__);
    $homepage = stream_get_contents($fileHandle);
    $response->getBody()->write($homepage);
} else {
    $response = $response->withStatus(404);
    $response->getBody()->write("<h1>404</h1><p>Path '$path' does not exist.</p>");
}
// =============================================================================


// =============================================================================
// Handling the response is usually also handled by your framework
// -----------------------------------------------------------------------------
http_response_code($response->getStatusCode());

foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header(sprintf('%s: %s', $name, $value), false);
    }
}

echo (string) $response->getBody();
exit;

__halt_compiler();<!doctype html>
<html lang="en">
<meta charset="UTF-8">
<title>Example Solid Resource Server</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' stroke='%23000' stroke-width='0' viewBox='-5 -5 110 110'><circle cx='50' cy='50' r='51' fill='%237C4DFF'/><circle cx='50' cy='50' r='34' fill='%23F2E205'/><path fill='%23FFF' stroke-width='2' d='M-1 50h16a38 35.5 0 0027.5 34.45V68.7A20 20 0 0150 30.2a20 20 0 017.5 38.5v15.75A38 35.5 0 0085 50h15A1 1 0 010 50z'/></svg>">

<h1>Example Solid Resource Server</h1>

<section>
    <p>This is an example of how to implement the PDS Interop Solid-CRUD package.</p>

    <p>The forms below demonstrate how the available requests are handled.</p>

    <p>
        The CRUD API expects <code>DELETE</code> and <code>PUT</code> HTTP methods
        for the delete and update actions. As these are not supported by HTML
        forms, they can also be provided as HTTP GET parameters.
    </p>

    <p>
        To interact with a resource, a <code>path</code> must be provided.
    </p>

    <!-- @TODO: Add file upload instead of only allowing textarea -->
    <form action="/data/" method="POST" enctype="application/x-www-form-urlencoded">

        <fieldset><legend>Path</legend>
            <label><code>/data/</code><input /></label>
        </fieldset>

        <fieldset><legend>Content</legend>
            <label><textarea></textarea></label>
        </fieldset>

        <fieldset class="actions"><legend>Action</legend>
            <button type="submit" data-method="POST">Create</button>
            <button type="submit" data-method="GET">Read</button>
            <button type="submit" data-method="PUT">Update</button>
            <button type="submit" data-method="DELETE">Delete</button>
            <hr>
            <button type="submit" data-method="HEAD">Head</button>
            <button type="submit" data-method="OPTIONS">Options</button>
            <button type="submit" data-method="PATCH">Patch</button>
        </fieldset>

        <fieldset><legend>Result</legend>
            <div></div>
        </fieldset>
    </form>
</section>

<style>
    button { width: 6em; }
    button:first-letter { font-weight: bold; }
    fieldset p { border: 1px solid black; color: white; font-family:monospace; opacity: 0.8; padding: 0.5em; }
    fieldset, section { margin: 1em; padding: 1em; }
    fieldset:nth-of-type(-n+2) {width: 20em}
    fieldset:nth-of-type(3) {width: 6em}
    form { align-items: flex-start; display: flex; width: 100%; }
    input { width: calc( 100% - 4em); }
    legend { font-weight: bold; }
    pre { border: 1px solid; padding: 1em; white-space: pre-wrap; word-break: keep-all }
    textarea {height: 10em; width: 100%;}

    .empty { opacity: 0.1; background: lightgray; }
    .redirected::before {content: "(redirected to)";}
    .status-2 p {background: green}
    .status-4 p {background: orangered}
    .status-5 p {background: darkred}
    .status-error p {background: red}
</style>

<script>
    window.addEventListener('DOMContentLoaded', _ => {
        const form = document.querySelector('form')

        const data = form.querySelector('textarea')
        const path = form.querySelector('input')
        const output = form.querySelector('div')

        form.addEventListener('submit', event => event.preventDefault())

        form.querySelectorAll('button').forEach(button => button.addEventListener('click', _ => {
            const body = data.value
            const method = button.getAttribute('data-method')
            const url = form.action + path.value

            output.innerHTML = `<pre>${method} ${url}</pre>`

            fetch(url, {
                body: ['DELETE', 'GET', 'HEAD', 'OPTIONS'].includes(method) ? null : body,
                method: method
            })
                .then(async response => ({text: await response.text(), response}))
                .then(({text, response}) => {
                    const headers = Array.from(response.headers.entries())
                        .map(entry => `${entry[0]}: ${entry[1]}`)
                        .join("\n")

                    output.classList = `status-${(''+response.status)[0]}`

                    const html = text.replace(/[\u00A0-\u9999<>&]/g, function (i) {
                        return '&#' + i.charCodeAt(0) + ';';
                    });

                    output.innerHTML += `
                      <p class="${response.redirected ? 'redirected' : ''}">
                        <code>${response.status}</code> ${response.statusText}
                      </p>
                      <pre><code>${headers}</code></pre>
                      <pre class="${text?'':'empty'}"><code>${html}</code></pre>
                    `
                }).catch(error => {
                    output.classList = 'status-error'
                    output.innerHTML += `
                      <p>
                        <code>ERROR!</code>
                      </p>
                      <pre><code>${error}</code></pre>
                    `
                })
        }))
    })
</script>
</html>

