<?php
declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/** Populates the styled Excel template without requiring Node on the web server. */
final class WeeklyReportWorkbook
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function build(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'foleys-report-');
        if ($path === false) {
            throw new RuntimeException('Could not create the Excel report.');
        }
        $zip = new ZipArchive();
        $open = false;
        try {
            if (!copy(ROOT . '/resources/reports/weekly-report-template.xlsx', $path)
                || $zip->open($path) !== true) {
                throw new RuntimeException('Could not open the Excel report template.');
            }
            $open = true;
            $sheet = $this->xml($zip, 'xl/worksheets/sheet1.xml');
            $xpath = new DOMXPath($sheet);
            $xpath->registerNamespace('s', self::NS);
            $data = $xpath->query('//s:sheetData')->item(0);
            $prototype = $xpath->query('//s:row[@r="2"]')->item(0);
            // Keep only the header. No template spacer rows or cells outside the table.
            foreach (iterator_to_array($data->childNodes) as $node) {
                if ($node instanceof DOMElement && $node->getAttribute('r') !== '1') {
                    $data->removeChild($node);
                }
            }
            $rowNumber = 2;
            foreach ($report['orders'] as $order) {
                foreach ($report['itemsByOrder'][(int)$order->id] ?? [] as $index => $item) {
                    $discounted = $report['discountedCostsByOrder'][(int)$order->id][$index] ?? 0;
                    $values = [
                        (string)($order->barrister_name ?? ''),
                        (string)($order->email ?? ''),
                        $order->submitted_at ? $this->excelDate($order->submitted_at) : null,
                        (string)$item->item_name,
                        (int)$item->quantity,
                        (int)$item->line_total_cents / 100,
                        $discounted / 100,
                    ];
                    $row = $prototype->cloneNode(true);
                    $row->setAttribute('r', (string)$rowNumber);
                    foreach ($row->getElementsByTagNameNS(self::NS, 'c') as $i => $cell) {
                        $cell->setAttribute('r', chr(65 + $i) . $rowNumber);
                        $this->value($cell, $values[$i]);
                    }
                    $data->appendChild($row);
                    $rowNumber++;
                }
            }
            // Excel Tables need a blank insertable row when the report is empty.
            if ($rowNumber === 2) {
                $data->appendChild($prototype);
            }
            $lastRow = max(2, $rowNumber - 1);
            $table = $this->xml($zip, 'xl/tables/table1.xml');
            $range = 'A1:G' . $lastRow;
            $dimension = $xpath->query('//s:dimension')->item(0);
            if ($dimension instanceof DOMElement) {
                $dimension->setAttribute('ref', $range);
            }
            $table->documentElement->setAttribute('ref', $range);
            $table->getElementsByTagNameNS(self::NS, 'autoFilter')->item(0)->setAttribute('ref', $range);
            if (!$zip->addFromString('xl/worksheets/sheet1.xml', $sheet->saveXML())
                || !$zip->addFromString('xl/tables/table1.xml', $table->saveXML())
                || !$zip->close()) {
                throw new RuntimeException('Could not save the Excel report.');
            }
            $open = false;
            $bytes = file_get_contents($path);
            if ($bytes === false) {
                throw new RuntimeException('Could not read the Excel report.');
            }
            return $bytes;
        } finally {
            // Also closes an open archive if XML generation failed.
            if ($open) {
                $zip->close();
            }
            unlink($path);
        }
    }

    private function xml(ZipArchive $zip, string $part): DOMDocument
    {
        $xml = $zip->getFromName($part);
        $document = new DOMDocument();
        if ($xml === false || !$document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Invalid Excel report template: ' . $part);
        }
        return $document;
    }

    /** Inline strings keep user input literal, never executable spreadsheet formulas. */
    private function value(DOMElement $cell, string|int|float|null $value): void
    {
        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }
        $cell->removeAttribute('t');
        if ($value === null) {
            return;
        }
        $document = $cell->ownerDocument;
        if (is_string($value)) {
            $cell->setAttribute('t', 'inlineStr');
            $inline = $document->createElementNS(self::NS, 'x:is');
            $text = $document->createElementNS(self::NS, 'x:t');
            $text->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            // XML 1.0 disallows control characters that can occur in legacy input.
            $text->textContent = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
            $inline->appendChild($text);
            $cell->appendChild($inline);
        } else {
            $cell->setAttribute('t', 'n');
            $number = $document->createElementNS(self::NS, 'x:v');
            $number->textContent = (string)$value;
            $cell->appendChild($number);
        }
    }

    private function excelDate(DateTimeInterface $value): float
    {
        $local = DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone(WeeklyReportService::TIMEZONE));
        // Excel serial dates have no timezone. Store Melbourne wall-clock time.
        $wallClock = new DateTimeImmutable($local->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
        return $wallClock->getTimestamp() / 86400 + 25569;
    }
}
