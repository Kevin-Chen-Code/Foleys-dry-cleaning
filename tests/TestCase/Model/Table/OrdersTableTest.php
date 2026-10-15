<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\OrdersTable;
use Cake\TestSuite\TestCase;
use Cake\Validation\Validator;
use Cake\Event\Event;

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
        $this->assertSame('Please a valid email address to proceed', $invalidErrors['email']['email']);
    }

    public function testEmailCleanupPreservesValidAddressAndRejectsUnsafeInput(): void
    {
        $table = new OrdersTable(['table' => 'orders']);
        $validator = $table->validationDefault(new Validator());
        $data = new \ArrayObject(['barrister_name' => 'Casey O\'Connor', 'email' => '  Kevin+collection@example.com  ']);
        $table->beforeMarshal(new Event('Model.beforeMarshal', $table), $data, new \ArrayObject());

        $this->assertSame('Kevin+collection@example.com', $data['email']);
        $this->assertSame([], $validator->validate($data->getArrayCopy(), true));
        foreach ([['email' => ['someone@example.com']], ['email' => "someone@example.com\r\nBcc: other@example.com"], ['email' => '<script>alert(1)</script>'], ['email' => "\tsomeone@example.com"]] as $invalid) {
            $this->assertArrayHasKey('email', $validator->validate($invalid, true));
        }
    }

    public function testNameIsRequiredAndValidatedAsPlainText(): void
    {
        $table = new OrdersTable(['table' => 'orders']);
        $validator = $table->validationDefault(new Validator());
        $email = ['email' => 'casey@example.com'];
        foreach (['', ['Casey'], '<script>alert(1)</script>', "Casey\r\nSmith", str_repeat('A', 151)] as $invalid) {
            $this->assertArrayHasKey('barrister_name', $validator->validate($email + ['barrister_name' => $invalid], true));
        }
        $data = new \ArrayObject($email + ['barrister_name' => '  Casey   O\'Connor  ']);
        $table->beforeMarshal(new Event('Model.beforeMarshal', $table), $data, new \ArrayObject());
        $this->assertSame('Casey O\'Connor', $data['barrister_name']);
        $this->assertSame([], $validator->validate($data->getArrayCopy(), true));
    }
}
