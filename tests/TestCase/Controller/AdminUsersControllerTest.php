<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class AdminUsersControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testUsernameIsTrimmedAndPasswordIsPreserved(): void
    {
        Configure::write('Foleys.adminUsername', 'admin');
        Configure::write('Foleys.adminPassword', ' password<&> ');
        $this->enableCsrfToken();
        $this->post('/admin/login', ['username' => '  admin  ', 'password' => ' password<&> ']);
        $this->assertRedirect('/');
        $this->assertSession('admin', 'Foleys.admin');
    }

    public function testArrayCredentialsAreRejected(): void
    {
        Configure::write('Foleys.adminUsername', 'admin');
        Configure::write('Foleys.adminPassword', 'password');
        $this->enableCsrfToken();
        $this->post('/admin/login', ['username' => ['admin'], 'password' => ['password']]);
        $this->assertResponseOk();
        $this->assertResponseContains('Incorrect username or password.');
        $this->assertSessionNotHasKey('Foleys.admin');
    }

    public function testPasswordWhitespaceCannotBeDiscardedToAuthenticate(): void
    {
        Configure::write('Foleys.adminUsername', 'admin');
        Configure::write('Foleys.adminPassword', 'password');
        $this->enableCsrfToken();
        $this->post('/admin/login', ['username' => 'admin', 'password' => ' password ']);
        $this->assertResponseContains('Incorrect username or password.');
        $this->assertSessionNotHasKey('Foleys.admin');
    }
}
