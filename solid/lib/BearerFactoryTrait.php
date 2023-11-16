<?php

namespace OCA\Solid;

use DateInterval;
use OCP\IDBConnection;
use Pdsinterop\Solid\Auth\Utils\Bearer;
use Pdsinterop\Solid\Auth\Utils\JtiValidator;

trait BearerFactoryTrait
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private IDBConnection $connection;
    private DateInterval $validFor;

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function getBearer(): Bearer
    {
        $interval = $this->getBearerValidFor();

        $replayDetector = new JtiReplayDetector($interval, $this->connection);

        $jtiValidator = new JtiValidator($replayDetector);

        return new Bearer($jtiValidator);
    }

    final public function getBearerValidFor(): DateInterval
    {
        static $validFor;

        if ($validFor === null) {
            $validFor = new DateInterval('PT10M');
        }

        return $validFor;
    }

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\
}
