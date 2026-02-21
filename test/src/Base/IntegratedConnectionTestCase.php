<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace ActiveCollab\JobsQueue\Test\Base;

use ActiveCollab\DatabaseConnection\Connection\MysqliConnection;
use mysqli;
use RuntimeException;

class IntegratedConnectionTestCase extends TestCase
{
    protected static mysqli $static_link;
    protected mysqli $link;
    protected MysqliConnection $connection;
    protected static array $tables_to_truncate = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $name = getenv('DB_NAME') ?: 'activecollab_jobs_queue_test';

        self::$static_link = new mysqli($host, $user, $pass, $name);

        if (self::$static_link->connect_error) {
            throw new RuntimeException('Failed to connect to database. MySQL said: ' . self::$static_link->connect_error);
        }
    }

    public function setUp(): void
    {
        parent::setUp();

        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $name = getenv('DB_NAME') ?: 'activecollab_jobs_queue_test';

        $this->link = new mysqli($host, $user, $pass, $name);

        if ($this->link->connect_error) {
            throw new RuntimeException('Failed to connect to database. MySQL said: ' . $this->link->connect_error);
        }

        $this->connection = new MysqliConnection($this->link);

        // Truncate tables to ensure clean state before each test
        // This is faster than DROP/CREATE and resets AUTO_INCREMENT
        foreach (static::$tables_to_truncate as $table) {
            if (in_array($table, $this->connection->getTableNames())) {
                $this->connection->execute("TRUNCATE TABLE `{$table}`");
            }
        }
    }

    protected function tearDown(): void
    {
        $this->link->close();

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$static_link)) {
            self::$static_link->close();
        }

        parent::tearDownAfterClass();
    }
}
