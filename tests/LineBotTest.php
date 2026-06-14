<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../model/GmailRepository.php';
require_once __DIR__ . '/../lib/line_bot.php';

class LineBotTest extends TestCase
{
    private const LINE_ID = 'test-line-id';
    private const EMAIL = 'test@example.com';

    // フィルターが登録されている場合、設定内容を一覧表示することを確認する
    public function testBuildSettingsMessageReturnsFilterListWhenRegistered()
    {
        $db = $this->createMock(GmailRepository::class);
        $db->method('getMyGmail')
            ->with(self::LINE_ID)
            ->willReturn([
                ['line_id' => self::LINE_ID, 'email' => self::EMAIL],
            ]);
        $db->method('getMyFilter')
            ->with(self::LINE_ID, self::EMAIL)
            ->willReturn([
                ['mail_from' => 'from@example.com', 'subject' => 'テスト件名'],
            ]);

        $message = buildSettingsMessage($db, self::LINE_ID);

        $this->assertStringContainsString('To: ' . self::EMAIL, $message);
        $this->assertStringContainsString('From: from@example.com', $message);
        $this->assertStringContainsString('Subject: テスト件名', $message);
    }

    // 何も登録されていない場合、登録案内メッセージを返すことを確認する
    public function testBuildSettingsMessageReturnsGuideWhenNotRegistered()
    {
        $db = $this->createMock(GmailRepository::class);
        $db->method('getMyGmail')
            ->with(self::LINE_ID)
            ->willReturn([]);

        $message = buildSettingsMessage($db, self::LINE_ID);

        $this->assertStringContainsString('設定が登録されていません', $message);
    }
}
