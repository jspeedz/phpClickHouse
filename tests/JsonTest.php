<?php

namespace ClickHouseDB\Tests;

use ClickHouseDB\Exception\DatabaseException;
use PHPUnit\Framework\TestCase;

/**
 * Class JsonTest
 * @group Json
 * @package ClickHouseDB\Tests
 */
final class JsonTest extends TestCase
{
    use WithClient;

    public function testJSONEachRow()
    {



        $state=$this->client->select('SELECT sin(number) as sin,cos(number) as cos FROM {table_name} LIMIT 2 FORMAT JSONEachRow', ['table_name'=>'system.numbers']);
        $checkString='{"sin":0,"cos":1}';
        $this->assertStringContainsString($checkString,$state->rawData());


        $state=$this->client->select('SELECT round(4+sin(number),2) as sin,round(4+cos(number),2) as cos FROM {table_name} LIMIT 2 FORMAT JSONCompact', ['table_name'=>'system.numbers']);

        $re=[
                [[4,5]],
                [[4.84,4.54]]
            ];

//        print_r($state->rows());
//        print_r($re);
//        die();
        $this->assertEquals($re,$state->rows());

    }

    public function testWriteCreateTableNotJsonRow()
    {
        $state=$this->client->write('CREATE TABLE testWriteNotJsonRow
(
    some_id UInt64,
    some_time DateTime,
    some_name String
)
ENGINE = MergeTree
ORDER BY (some_time, some_id)');
        $this->assertSame(false, $state->getFormat());
        $this->assertSame([], $state->rows());

        $state=$this->client->select('SHOW CREATE TABLE testWriteNotJsonRow');
        $this->assertSame([
            [
                'statement' => 'CREATE TABLE php_clickhouse.testWriteNotJsonRow
(
    `some_id` UInt64,
    `some_time` DateTime,
    `some_name` String
)
ENGINE = MergeTree
ORDER BY (some_time, some_id)
SETTINGS index_granularity = 8192'
            ]
        ], $state->rows());

        $this->client->write('DROP TABLE testWriteNotJsonRow SYNC');

        $exceptionThrown = false;
        try {
            $this->client->select('SHOW CREATE TABLE testWriteNotJsonRow')
                ->rows();
        }
        catch(\Throwable $e) {
            $this->assertInstanceOf(DatabaseException::class, $e);
            $this->assertSame('Table `testWriteNotJsonRow` doesn\'t exist. (CANNOT_GET_CREATE_TABLE_QUERY) 
IN:SHOW CREATE TABLE testWriteNotJsonRow FORMAT JSON', $e->getMessage());

            $exceptionThrown = true;
        }

        if(!$exceptionThrown) {
            $this->fail('Expected exception');
        }
    }

    public function testWriteCreateTableNotJsonRowOnCluster()
    {
        $state=$this->client->write('CREATE TABLE testWriteNotJsonRowOnCluster ON CLUSTER test_cluster
(
    some_id UInt64,
    some_time DateTime,
    some_name String
)
ENGINE = ReplicatedMergeTree(\'/clickhouse/tables/{shard}/testWriteNotJsonRowOnCluster\', \'{replica}\')
ORDER BY (some_time, some_id)');
        $this->assertSame('JSON', $state->getFormat());
        $this->assertSame([], $state->rows());

        $state=$this->client->select('SHOW CREATE TABLE testWriteNotJsonRowOnCluster');
        $this->assertSame([
            [
                'statement' => 'CREATE TABLE php_clickhouse.testWriteNotJsonRowOnCluster
(
    `some_id` UInt64,
    `some_time` DateTime,
    `some_name` String
)
ENGINE = ReplicatedMergeTree(\'/clickhouse/tables/{shard}/testWriteNotJsonRowOnCluster\', \'{replica}\')
ORDER BY (some_time, some_id)
SETTINGS index_granularity = 8192'
            ]
        ], $state->rows());

        $this->client->write('DROP TABLE testWriteNotJsonRowOnCluster ON CLUSTER test_cluster SYNC');

        $exceptionThrown = false;
        try {
            $this->client->select('SHOW CREATE TABLE testWriteNotJsonRowOnCluster')
                ->rows();
        }
        catch(\Throwable $e) {
            $this->assertInstanceOf(DatabaseException::class, $e);
            $this->assertSame('Table `testWriteNotJsonRowOnCluster` doesn\'t exist. (CANNOT_GET_CREATE_TABLE_QUERY) 
IN:SHOW CREATE TABLE testWriteNotJsonRowOnCluster FORMAT JSON', $e->getMessage());

            $exceptionThrown = true;
        }

        if(!$exceptionThrown) {
            $this->fail('Expected exception');
        }
    }
}
