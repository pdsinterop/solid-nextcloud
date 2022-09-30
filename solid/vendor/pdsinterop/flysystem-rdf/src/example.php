<!DOCTYPE html>
<?php
    require_once __DIR__.'/../vendor/autoload.php';

    $formats = \Pdsinterop\Rdf\Enum\Format::keys();

    $adapter = new League\Flysystem\Adapter\Local(__DIR__ . '/../tests/fixtures');
    $filesystem = new League\Flysystem\Filesystem($adapter);
    $graph = new \EasyRdf\Graph();
    $plugin = new \Pdsinterop\Rdf\Flysystem\Plugin\ReadRdf($graph);

    $filesystem->addPlugin($plugin);
?>
<html lang="en">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>

    .active {background-color: #F2E205;}

    title {display: block;}

    .content {
        display: none;
    }

    .content pre {
        white-space: pre-wrap;
    }

    .tab-selector:nth-of-type(1):checked ~ .content:nth-of-type(1),
    .tab-selector:nth-of-type(2):checked ~ .content:nth-of-type(2),
    .tab-selector:nth-of-type(3):checked ~ .content:nth-of-type(3),
    .tab-selector:nth-of-type(4):checked ~ .content:nth-of-type(4),
    .tab-selector:nth-of-type(5):checked ~ .content:nth-of-type(5) {
        display: initial;
    }

</style>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.0/css/bulma.min.css" integrity="sha256-aPeK/N8IHpHsvPBCf49iVKMdusfobKo2oxF8lRruWJg=" crossorigin="anonymous">

<section class="section">
    <div class="container">
        <h1 class="title">
            <title>PDS Interop Flysystem RDF Plugin example</title>
        </h1>
        <div class="tabs is-centered is-toggle">
            <ul>
                <?php foreach ($formats as $index => $format): ?>
                <li>
                    <label for="tab-<?= $index ?>">
                        <a>
                            <span class="icon is-small"><i class="fas fa-music" aria-hidden="true"></i></span>
                            <span><?= $format ?></span>
                        </a>
                    </label>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="container section">
        <?php foreach ($formats as $index => $format): ?>
        <input id="tab-<?= $index ?>" name="tabs" type="radio" class="tab-selector is-hidden" />
        <div class="content">
            <h3><?= $index ?></h3>
            <pre><code><?= htmlentities($filesystem->readRdf('/foaf.rdf', $format, \Pdsinterop\Rdf\Enum\Rdf::EMPTY_NODE)) ?></code></pre>
        </div>
        <?php endforeach; ?>
    </div>
</section>
</html>
