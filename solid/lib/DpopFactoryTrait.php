<?php

namespace OCA\Solid;

use DateInterval;
use OCP\IDBConnection;
use Pdsinterop\Solid\Auth\Utils\DPop;
use Pdsinterop\Solid\Auth\Utils\JtiValidator;

trait DpopFactoryTrait
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private IDBConnection $connection;
    private DateInterval $validFor;

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function getDpop(): DPop
    {
        $interval = $this->getDpopValidFor();

        $replayDetector = new JtiReplayDetector($interval, $this->connection);

        $jtiValidator = new JtiValidator($replayDetector);

        return new DPop($jtiValidator);
    }

    final public function getDpopValidFor(): DateInterval
    {
        static $validFor;

        if ($validFor === null) {
            $validFor = new DateInterval('PT10M');
        }

        return $validFor;
    }

    final public function setJtiStorage(IDBConnection $connection): void
    {
        $this->connection = $connection;
    }

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\
}
