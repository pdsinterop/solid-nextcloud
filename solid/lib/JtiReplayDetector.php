<?php

namespace OCA\Solid;

use DateInterval;
use DateTime;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Pdsinterop\Solid\Auth\ReplayDetectorInterface;

class JtiReplayDetector implements ReplayDetectorInterface
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private string $table = 'solid_jti';

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    public function __construct(private DateInterval $interval, private IDBConnection $connection)
    {
    }

    public function detect(string $jti, string $targetUri): bool
    {
        // @TODO: $this->rotateBuckets();
        $has = $this->has($jti, $targetUri);

        if ($has === false) {
            $this->store($jti, $targetUri);
        }

        return $has;
    }

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private function has(string $jti, string $uri): bool
    {
        $queryBuilder = $this->connection->getQueryBuilder();

        $notOlderThan = (new DateTime())->sub($this->interval);

        $cursor = $queryBuilder->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('jti', $queryBuilder->createNamedParameter($jti,IQueryBuilder::PARAM_STR))
            )
            ->andWhere(
                $queryBuilder->expr()->eq('uri', $queryBuilder->createNamedParameter($uri, IQueryBuilder::PARAM_STR))
            )
            ->andWhere(
                $queryBuilder->expr()->gt('request_time', $queryBuilder->createParameter('notOlderThan'))
            )->setParameter('notOlderThan', $notOlderThan, 'datetime')
            ->execute()
        ;

        $row = $cursor->fetch();

        $cursor->closeCursor();

        return ! empty($row);
    }

    private function store(string $jti, string $uri): void
    {
        $queryBuilder = $this->connection->getQueryBuilder();

        $queryBuilder->insert($this->table)
            ->values([
                'jti' => $queryBuilder->createNamedParameter($jti),
                'uri' => $queryBuilder->createNamedParameter($uri),
            ])
            ->executeStatement()
        ;
    }
}
