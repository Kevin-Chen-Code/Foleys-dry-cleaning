<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\WeeklyReportService;
use Cake\TestSuite\TestCase;

final class WeeklyReportServiceTest extends TestCase
{
    public function testDiscountedLineCostsAddUpToTheFinalRequestTotal(): void
    {
        $items = [
            (object)['line_total_cents' => 500],
            (object)['line_total_cents' => 1000],
        ];

        $discountedCosts = (new WeeklyReportService())->discountedLineCosts(1350, $items);

        $this->assertSame([450, 900], $discountedCosts);
        $this->assertSame(1350, array_sum($discountedCosts));
    }

    public function testDiscountedLineCostsKeepRoundingCentsInTheFinalTotal(): void
    {
        $items = [
            (object)['line_total_cents' => 333],
            (object)['line_total_cents' => 333],
            (object)['line_total_cents' => 334],
        ];

        $discountedCosts = (new WeeklyReportService())->discountedLineCosts(900, $items);

        $this->assertSame(900, array_sum($discountedCosts));
    }
}
