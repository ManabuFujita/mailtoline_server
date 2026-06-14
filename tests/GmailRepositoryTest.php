<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../model/GmailRepository.php';

class GmailRepositoryTest extends TestCase
{
    private GmailRepository $db;
    private PDO $pdo;

    // テスト用の固定データ
    private const LINE_ID = 'test-line-id';
    private const EMAIL = 'test@example.com';
    private const MAIL_ID = 'test-mail-id';

    // テスト用フィクスチャをトランザクション内に投入する
    protected function setUp(): void
    {
        $this->db = new GmailRepository();

        // GmailRepositoryが内部で使っているPDO接続を取得する
        // （投入したテストデータを同じトランザクション内で参照させるため）
        $reflection = new ReflectionProperty(Database::class, 'pdo');
        $this->pdo = $reflection->getValue($this->db);

        // テスト用データを実DBに残さないよう、トランザクション内で実行する
        $this->pdo->beginTransaction();

        $this->insertFixtures();
    }

    // setUpで投入したフィクスチャをロールバックする
    protected function tearDown(): void
    {
        // 投入したテストデータをロールバックし、実DBへの影響を残さない
        $this->pdo->rollBack();
    }

    // 各テストで共通して使う、アカウント・フィルター・送信ログのデータを投入する
    private function insertFixtures(): void
    {
        // mail_gmails.line_id は users.line_id への外部キーのため、先にusersへ投入する
        $this->pdo->exec(
            "INSERT INTO users (line_id, name)"
            . " VALUES ('" . self::LINE_ID . "', 'テストユーザー')"
        );

        $this->pdo->exec(
            "INSERT INTO mail_gmails (line_id, email, access_token, refresh_token, id_token, expires_in, created)"
            . " VALUES ('" . self::LINE_ID . "', '" . self::EMAIL . "', 'access-token', 'refresh-token', 'id-token', 3600, '2026-01-01 00:00:00')"
        );

        $this->pdo->exec(
            "INSERT INTO mailfilters (line_id, email, mail_from, subject)"
            . " VALUES ('" . self::LINE_ID . "', '" . self::EMAIL . "', 'from@example.com', 'テスト件名')"
        );

        $this->pdo->exec(
            "INSERT INTO sendlogs (line_id, email, mail_id, senddate, title, mail_from)"
            . " VALUES ('" . self::LINE_ID . "', '" . self::EMAIL . "', '" . self::MAIL_ID . "', '2026-01-01 00:00:00', 'テストタイトル', 'from@example.com')"
        );
    }

    // getAllGmail()が登録済みアカウント一覧を返すことを確認する
    public function testGetAllGmailReturnsRegisteredAccounts()
    {
        $list = $this->db->getAllGmail();

        // 投入したテストアカウントが一覧に含まれていることを確認する
        $emails = array_column($list, 'email');
        $this->assertContains(self::EMAIL, $emails);
    }

    // getMyGmail()が指定したline_idのアカウントのみを返すことを確認する
    public function testGetMyGmailReturnsAccountsForLineId()
    {
        $list = $this->db->getMyGmail(self::LINE_ID);

        $this->assertCount(1, $list);
        $this->assertSame(self::EMAIL, $list[0]['email']);
    }

    // getAllFilterWithToken()がフィルターとGmailトークンを結合した結果を返すことを確認する
    public function testGetAllFilterWithTokenReturnsFiltersWithToken()
    {
        $list = $this->db->getAllFilterWithToken();

        // 投入したテストデータの行を探す
        $found = null;
        foreach ($list as $row)
        {
            if ($row['line_id'] === self::LINE_ID && $row['email'] === self::EMAIL)
            {
                $found = $row;
                break;
            }
        }

        $this->assertNotNull($found);
        $this->assertSame('access-token', $found['access_token']);
        $this->assertSame('テスト件名', $found['subject']);
    }

    // getMyFilter()が指定したline_id/emailのフィルターを返すことを確認する
    public function testGetMyFilterReturnsFilterForLineIdAndEmail()
    {
        $list = $this->db->getMyFilter(self::LINE_ID, self::EMAIL);

        $this->assertCount(1, $list);
        $this->assertSame('テスト件名', $list[0]['subject']);
    }

    // getSendlog()が既存の送信ログを返すことを確認する
    public function testGetSendlogReturnsLogForKnownMailId()
    {
        $list = $this->db->getSendlog(self::LINE_ID, self::EMAIL, self::MAIL_ID);

        $this->assertCount(1, $list);
        $this->assertSame('テストタイトル', $list[0]['title']);
    }

    // isSended()が送信済みのmail_idに対してtrueを返すことを確認する
    public function testIsSendedReturnsTrueForKnownMailId()
    {
        $this->assertTrue($this->db->isSended(self::LINE_ID, self::EMAIL, self::MAIL_ID));
    }

    // isSended()が未送信のmail_idに対してfalseを返すことを確認する
    public function testIsSendedReturnsFalseForUnknownMailId()
    {
        $this->assertFalse($this->db->isSended(self::LINE_ID, self::EMAIL, 'unknown-mail-id'));
    }
}
