<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../model/Database.php';

class DatabaseTest extends TestCase
{
    public function testConnectReturnsConnectedPdoInstance()
    {
        $db = new class extends Database {};

        $pdo = $db->connect();

        $this->assertInstanceOf(PDO::class, $pdo);
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertEquals(false, $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
    }
}
