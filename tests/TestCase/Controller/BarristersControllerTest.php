<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class BarristersControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testPublicSuggestionsReturnMatchingNameAndEmail(): void
    {
        $this->get('/barristers/suggestions?q=Luisa%20Alampi');
        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $this->assertSame(['suggestions' => [['name' => 'Luisa Alampi', 'email' => 'alampi@vicbar.com.au']]], json_decode((string)$this->_response->getBody(), true));
    }

    public function testMalformedQueryIsIgnoredAndPostIsNotAllowed(): void
    {
        $this->get('/barristers/suggestions?q%5B%5D=Luisa');
        $this->assertResponseOk();
        $this->assertSame(['suggestions' => []], json_decode((string)$this->_response->getBody(), true));
        $this->enableCsrfToken();
        $this->post('/barristers/suggestions', ['q' => 'Luisa']);
        $this->assertResponseCode(405);
    }
}
