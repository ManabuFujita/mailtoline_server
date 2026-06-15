<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../model/GmailRepository.php';
require_once __DIR__ . '/../lib/mail_filter.php';

class MailFilterTest extends TestCase
{
    // リフレッシュトークンが無効化されている場合、処理をスキップすることを確認する
    public function testProcessFilterSkipsWhenRefreshTokenIsEmpty()
    {
        $db = $this->createMock(GmailRepository::class);
        $db->expects($this->never())
            ->method('getSendCountThisMonth');

        $f = [
            'email' => 'gmail@example.com',
            'line_id' => 'line-id',
            'refresh_token' => '',
            'mail_from' => 'from@example.com',
            'subject' => 'テスト件名',
        ];

        $now = new DateTimeImmutable();
        processFilter($f, $db, $now, $now);
    }

    public function testLogSentMessagesInsertsLogsWhenSucceeded()
    {
        $db = $this->createMock(GmailRepository::class);
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
        $db = $this->createMock(GmailRepository::class);
        $db->expects($this->never())
            ->method('insertSendlog');

        $sendLogs = [
            ['mailId' => 'mail1', 'subject' => 'subject1', 'from' => 'from1', 'now' => new DateTimeImmutable()],
        ];

        logSentMessages('line-id', 'gmail@example.com', $sendLogs, $db, false);
    }
}
