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

use ActiveCollab\JobsQueue\Jobs\Job;
use ActiveCollab\JobsQueue\JobsDispatcher;
use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use Exception;

abstract class IntegratedMySqlQueueTest extends IntegratedConnectionTestCase
{
    protected QueueInterface $queue;
    protected JobsDispatcher $dispatcher;
    protected ?string $last_failed_job = null;
    protected ?string $last_failure_message = null;

    protected static array $tables_to_truncate = [
        MySqlQueue::BATCHES_TABLE_NAME,
        MySqlQueue::JOBS_TABLE_NAME,
        MySqlQueue::FAILED_JOBS_TABLE_NAME,
        'email_log',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Create tables once for the entire test class
        $connection = new \ActiveCollab\DatabaseConnection\Connection\MysqliConnection(self::$static_link);
        $queue = new MySqlQueue($connection);
        $queue->createTables();
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->queue = new MySqlQueue($this->connection);
        $this->queue->onJobFailure(
            function (Job $job, Exception $reason) {
                $this->last_failed_job = get_class($job);
                $this->last_failure_message = $reason->getMessage();
            }
        );

        $this->dispatcher = new JobsDispatcher($this->queue);

        $this->assertCount(0, $this->dispatcher->getQueue());
    }

    protected function tearDown(): void
    {
        // No need to drop tables - transaction rollback handles cleanup
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        // Drop tables after all tests in the class have run
        if (isset(self::$static_link)) {
            $connection = new \ActiveCollab\DatabaseConnection\Connection\MysqliConnection(self::$static_link);
            $connection->dropTable(MySqlQueue::BATCHES_TABLE_NAME);
            $connection->dropTable(MySqlQueue::JOBS_TABLE_NAME);
            $connection->dropTable(MySqlQueue::FAILED_JOBS_TABLE_NAME);
            if (in_array('email_log', $connection->getTableNames())) {
                $connection->dropTable('email_log');
            }
        }

        parent::tearDownAfterClass();
    }

    /**
     * Check number of records in jobs queue table.
     */
    protected function assertRecordsCount(int $expected): void
    {
        $this->assertSame(
            $expected,
            $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '`')
        );
    }

    /**
     * Check number of records in failed jobs queue table.
     */
    protected function assertFailedRecordsCount(int $expected): void
    {
        $this->assertSame(
            $expected,
            $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::FAILED_JOBS_TABLE_NAME . '`')
        );
    }

    /**
     * Check if attempts value for the given job has an expected value.
     */
    protected function assertAttempts(?int $expected, int $job_id): void
    {
        $result = $this->connection->executeFirstCell(
            'SELECT `attempts` FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE id = ?', $job_id
        );

        if ($expected === null) {
            $this->assertEmpty($result);
        } else {
            $this->assertSame($expected, (integer) $result);
        }
    }
}
