<?php declare(strict_types=1);

namespace IgniTest\Functional\Storage;

use Igni\Storage\Driver\MongoDB\Connection as MongoDBConnection;
use Igni\Storage\Driver\MongoDB\ConnectionOptions as MongoDBOptions;
use Igni\Storage\Driver\Pdo\Connection as SqliteConnection;
use Igni\Storage\Driver\Pdo\ConnectionOptions as SqliteOptions;
use Igni\Storage\Driver\Pdo\Cursor;
use Igni\Storage\EntityManager;
use Igni\Storage\Storage;
use IgniTest\Fixtures\Album\AlbumEntity;
use IgniTest\Fixtures\Album\AlbumRepository;
use IgniTest\Fixtures\Artist\ArtistEntity;
use IgniTest\Fixtures\Artist\ArtistRepository;
use IgniTest\Fixtures\Genre\GenreEntity;
use IgniTest\Fixtures\Genre\GenreRepository;
use IgniTest\Fixtures\Playlist\PlaylistEntity;
use IgniTest\Fixtures\Playlist\PlaylistRepository;
use IgniTest\Fixtures\Track\TrackEntity;
use IgniTest\Fixtures\Track\TrackRepository;

trait StorageTrait
{
    /** @var Storage */
    private $unitOfWork;

    /** @var string */
    private $sqliteDbPath;

    /** @var string */
    private $mongoData;

    /** @var SqliteConnection */
    private $sqliteConnection;

    /** @var MongoDBConnection */
    private $mongoConnection;

    /** @var EntityManager */
    private $entityManager;

    private function setupStorage(): void
    {
        $tmpDir = __DIR__ . '/../../tmp/';
        // Copy database so it stays untouched.
        $dbDir = __DIR__ . '/../../Fixtures/';
        $this->sqliteDbPath = tempnam(sys_get_temp_dir(), 'igni-test');
        copy($dbDir . '/test.db', $this->sqliteDbPath);

        $this->sqliteConnection = new SqliteConnection($this->sqliteDbPath, new SqliteOptions('sqlite'));
        $this->sqliteConnection->open();
        $this->entityManager = new EntityManager($tmpDir);
        $this->unitOfWork = new Storage($this->entityManager);

        $this->mongoConnection = new MongoDBConnection('localhost', new MongoDBOptions('test', 'travis', 'test'));
        $this->mongoConnection->open();

        $mongoData = json_decode(file_get_contents($dbDir . '/test.json'), true);
        foreach ($mongoData as $collection => $data) {
            try {
                $this->mongoConnection->dropCollection($collection);
            } catch (\Exception $e) {
                // Ignore missing collection error.
            }
            $this->mongoConnection->insert($collection, ...$data);
        }
    }

    private function executeSql(string $query, array $bind = null): \PDOStatement
    {
        $statement = $this->sqliteConnection->getBaseConnection()->prepare($query);
        $statement->execute($bind);

        return $statement;
    }

    private function clearStorage(): void
    {
        unlink($this->sqliteDbPath);
    }

    private function createCursorForSql(string $sql, array $bind = null): Cursor
    {
        return $this->sqliteConnection->execute($sql, $bind);
    }

    private function loadRepositories(): void
    {
        $this->entityManager->addRepository(
            new ArtistRepository($this->sqliteConnection, $this->entityManager),
            new AlbumRepository($this->sqliteConnection, $this->entityManager),
            new TrackRepository($this->sqliteConnection, $this->entityManager),
            new GenreRepository($this->sqliteConnection, $this->entityManager),
            new PlaylistRepository($this->mongoConnection, $this->entityManager)
        );
    }
}
