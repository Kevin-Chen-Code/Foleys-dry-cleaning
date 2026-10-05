<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\OrdersTable;
use Cake\TestSuite\TestCase;
use Cake\Validation\Validator;

final class OrdersTableTest extends TestCase
{
    public function testEmailIsRequiredAndMustBeValid(): void
    {
        $table = new OrdersTable(['table' => 'orders']);
        $validator = $table->validationDefault(new Validator());

        $expected = 'Please enter your email address to proceed';
        $missingErrors = $validator->validate([], true);
        $blankErrors = $validator->validate(['email' => ''], true);
        $invalidErrors = $validator->validate(['email' => 'not-an-email'], true);

        $this->assertSame($expected, $missingErrors['email']['_required']);
        $this->assertSame($expected, $blankErrors['email']['_empty']);
        $this->assertSame($expected, $invalidErrors['email']['email']);
    }
}
