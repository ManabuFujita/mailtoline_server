<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/mail_filter.php';

class MailFilterTest extends TestCase
{
    public function testLogSentMessagesInsertsLogsWhenSucceeded()
    {
        $db = $this->createMock(Mail_gmailStub::class);
        $db->expects($this->exactly(2))
            ->method('insertSendlog');

        $sendLogs = [
            ['mailId' => 'mail1', 'subject' => 'subject1', 'from' => 'from1', 'now' => new DateTimeImmutable()],
            ['mailId' => 'mail2', 'subject' => 'subject2', 'from' => 'from2', 'now' => new DateTimeImmutable()],
        ];

        logSentMessages('line-id', 'gmail@example.com', $sendLogs, $db, true);
    }

    public function testLogSentMessagesDoesNothingWhenFailed()
    {
        $db = $this->createMock(Mail_gmailStub::class);
        $db->expects($this->never())
            ->method('insertSendlog');

        $sendLogs = [
            ['mailId' => 'mail1', 'subject' => 'subject1', 'from' => 'from1', 'now' => new DateTimeImmutable()],
        ];

        logSentMessages('line-id', 'gmail@example.com', $sendLogs, $db, false);
    }
}

// $dbの型ヒントがないため、モック作成用にinsertSendlogを持つインターフェースを用意する
interface Mail_gmailStub
{
    public function insertSendlog($lineId, $email, $mailId, $subject, $from, $now);
}
