<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\BarristerDirectory;
use Cake\TestSuite\TestCase;
use Cake\Validation\Validation;

final class BarristerDirectoryTest extends TestCase
{
    public function testSearchMatchesFirstNameSurnameCaseAndAccents(): void
    {
        $entry = ['name' => "Zoë O’Connor", 'email' => 'zoe@example.com'];
        $directory = new BarristerDirectory([$entry, ['name' => 'Other Person', 'email' => 'other@example.com']]);
        foreach (['zoe', 'ZOË', 'connor', 'o\'connor', 'Connor Zoe'] as $query) {
            $this->assertSame([$entry], $directory->search($query));
        }
    }

    public function testAmbiguousNamesRemainSeparateAndResultsAreBounded(): void
    {
        $directory = new BarristerDirectory([
            ['name' => 'Casey Smith', 'email' => 'casey.one@example.com'],
            ['name' => 'Casey Smith', 'email' => 'casey.two@example.com'],
        ]);
        $this->assertCount(2, $directory->search('Casey'));
        $this->assertCount(1, $directory->search('Casey', 1));
        foreach (['', 'C', ['Casey'], str_repeat('a', 151), "Casey\r\n", '<script>', "\xFF"] as $invalid) {
            $this->assertSame([], $directory->search($invalid));
        }
    }

    public function testImportedDirectoryHasAll364NameEmailPairs(): void
    {
        $data = json_decode(file_get_contents(RESOURCES . 'data/barristers.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Mailing List (as at Sept 2026)', $data['sheet']);
        $this->assertCount(364, $data['barristers']);
        foreach ($data['barristers'] as $entry) {
            $this->assertSame(['name', 'email'], array_keys($entry));
            $this->assertTrue(Validation::email($entry['email'], false));
            $this->assertLessThanOrEqual(150, mb_strlen($entry['name']));
        }
    }
}
