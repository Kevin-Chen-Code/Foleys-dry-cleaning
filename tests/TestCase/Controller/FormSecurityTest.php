<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Model\Table\OrdersTable;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Validation\Validator;

final class FormSecurityTest extends TestCase
{
    use IntegrationTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        Configure::write('Foleys.adminUsername', 'admin');
        Configure::write('Foleys.adminPassword', 'test-password');
    }

    public function testSqlSyntaxCannotBypassAdministratorLogin(): void
    {
        $this->enableCsrfToken();
        $this->post('/admin/login', [
            'username' => "admin' OR '1'='1' --",
            'password' => 'test-password',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Incorrect username or password.');
        $this->assertSessionNotHasKey('Foleys.admin');
    }

    public function testReflectedUsernameIsHtmlEscaped(): void
    {
        $this->enableCsrfToken();
        $this->post('/admin/login', [
            'username' => '<img src=x onerror=alert(1)>',
            'password' => 'test-password',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('value="&lt;img src=x onerror=alert(1)&gt;"');
        $this->assertResponseNotContains('<img src=x onerror=alert(1)>');
        $this->assertSessionNotHasKey('Foleys.admin');
    }

    public function testAdministratorLoginRequiresCsrfToken(): void
    {
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'test-password',
        ]);

        $this->assertResponseCode(403);
        $this->assertSessionNotHasKey('Foleys.admin');
    }

    public function testRequestSubmissionRequiresCsrfToken(): void
    {
        $this->post('/request', ['email' => 'barrister@example.com']);

        $this->assertResponseCode(403);
        $this->assertSessionNotHasKey('Foleys.lastOrder');
    }

    public function testEmailTestRequiresAdministratorSession(): void
    {
        $this->enableCsrfToken();
        $this->post('/admin/test-email');
        $this->assertRedirect('/admin/login');
    }

    public function testEmailTestCannotSendViaGet(): void
    {
        $this->session(['Foleys' => ['admin' => 'admin']]);
        $this->get('/admin/test-email');
        $this->assertResponseCode(405);
    }

    public function testEmailTestRequiresCsrfTokenEvenForAdministrator(): void
    {
        $this->session(['Foleys' => ['admin' => 'admin']]);
        $this->post('/admin/test-email');
        $this->assertResponseCode(403);
    }

    public function testEmailRejectsMarkupAndMailHeaderInjection(): void
    {
        $table = new OrdersTable(['table' => 'orders']);
        $validator = $table->validationDefault(new Validator());
        foreach ([
            '<script>alert(1)</script>@example.com',
            '<img src=x onerror=alert(1)>@example.com',
            'someone@example.com"><script>alert(1)</script>',
            "someone@example.com\r\nBcc: other@example.com",
            "someone@example.com\0",
            ['someone@example.com'],
        ] as $input) {
            $this->assertArrayHasKey('email', $validator->validate(['email' => $input], true));
        }
    }

    public function testEmailWithSqlPunctuationIsStoredAndQueriedAsData(): void
    {
        // A private in-memory database makes this regression test independent
        // of the application's default (potentially production) connection.
        $connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        $connection->execute('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, notes TEXT)');
        $table = new OrdersTable(['table' => 'orders', 'connection' => $connection]);

        try {
            foreach (["o'connor@example.com", "'OR'1'='1@example.com"] as $email) {
                $order = $table->newEntity(['email' => $email]);
                $this->assertSame([], $order->getErrors());
                $this->assertNotFalse($table->save($order));
                $matches = $table->find()->where(['email' => $email])->all()->toList();
                $this->assertCount(1, $matches);
                $this->assertSame($email, $matches[0]->email);
            }
            $this->assertSame(2, $table->find()->count());
        } finally {
            $connection->getDriver()->disconnect();
        }
    }
}
