<?php declare(strict_types=1);

namespace Igni\Storage\Driver\MongoDB;

use MongoDB;
use Igni\Storage\Driver\Connection as ConnectionInterface;
use Igni\Storage\Exception\DriverException;

final class Connection implements ConnectionInterface
{
    /** @var array */
    private const VALID_FIND_OPTIONS = [
        'sort', 'projection', 'skip', 'limit', 'max', 'min'
    ];

    /** @var MongoDB\Driver\Manager */
    private $handler;

    /** @var string */
    private $host;

    /** @var ConnectionOptions */
    private $options;

    public function __construct(string $host, ConnectionOptions $options = null)
    {
        $this->host = $host;
        $this->options = $options;
    }

    public function close(): void
    {
        $this->handler = null;
    }

    public function open(): void
    {
        $this->handler = new MongoDB\Driver\Manager(
            'mongodb://' . $this->host . '/' . $this->options->getDatabase(),
            $this->options->getURIOptions(),
            $this->options->getDriverOptions()
        );
    }

    public function isOpen(): bool
    {
        return $this->handler !== null;
    }

    public function execute(...$parameters): Cursor
    {
        if (!$this->isOpen()) {
            $this->open();
        }

        $command = new MongoDB\Driver\Command($parameters[0]);

        return new Cursor($this, $this->options, $command);
    }

    public function dropCollection(string $collection): void
    {
        $cursor = $this->execute([
            'drop' => $collection,
        ]);
        $cursor->execute();
    }

    public function insert(string $collection, array ...$documents): void
    {
        $cursor = $this->execute([
            'insert' => $collection,
            'documents' => $documents,
        ]);
        $cursor->execute();
    }

    public function remove(string $collection, ...$ids): void
    {
        $deletes = [];
        foreach ($ids as $id) {
            $deletes[] = [
                'q' => [
                    '_id' => $id,
                ],
                'limit' => 1,
            ];
        }
        $cursor = $this->execute([
            'delete' => $collection,
            'deletes' => $deletes,
        ]);
        $cursor->execute();
    }

    public function find(string $collection, array $query = [], array $options = []): Cursor
    {
        if (!empty($options) && array_diff(array_keys($options), self::VALID_FIND_OPTIONS)) {
            throw DriverException::forOperationFailure('Invalid option passed to find query.');
        }
        $command = array_merge([
            'find' => $collection,
            'filter' => $query,
        ], $options);
        if (empty($command['filter'])) {
            unset ($command['filter']);
        }

        return $this->execute($command);
    }

    public function count(string $collection, array $query): Cursor
    {
        return $this->execute([
            'count' => $collection,
            'query' => $query,
        ]);
    }

    public function update(string $collection, array ...$documents): void
    {
        $updates = [];
        foreach ($documents as $document) {
            $id = null;
            if (!isset($document['_id'])) {
                if (!isset($document['id'])) {
                    throw DriverException::forOperationFailure('Cannot update documents without identity.');
                }
                $id = $document['id'];
                unset($document['id']);
            } else {
                $id = $document['_id'];
                unset($document['_id']);
            }

            $updates[] = [
                'q' => [
                    '_id' => $id,
                ],
                'u' => $document,
                'upsert' => true,
            ];
        }
        $cursor = $this->execute([
            'update' => $collection,
            'updates' => $updates,
        ]);
        $cursor->execute();
    }

    public function getBaseConnection(): MongoDB\Driver\Manager
    {
        return $this->handler;
    }
}
