<?php
declare(strict_types=1);

namespace App\Service;

use Normalizer;

/** Public directory imported from columns A–C of the supplied mailing-list sheet. */
final class BarristerDirectory
{
    public function __construct(private ?array $entries = null)
    {
    }

    public function search(mixed $query, int $limit = 10): array
    {
        if (!is_string($query) || !mb_check_encoding($query, 'UTF-8') || mb_strlen($query) > 150 || preg_match('/[\x00-\x1F\x7F<>]/', $query)) {
            return [];
        }
        $query = $this->normalize($query);
        if (mb_strlen($query) < 2) {
            return [];
        }
        $entries = $this->entries ??= json_decode(
            file_get_contents(RESOURCES . 'data' . DS . 'barristers.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        )['barristers'];
        $tokens = explode(' ', $query);
        $matches = [];
        foreach ($entries as $entry) {
            $name = $this->normalize($entry['name']);
            foreach ($tokens as $token) {
                if (!str_contains($name, $token)) {
                    continue 2;
                }
            }
            $matches[] = ['name' => $entry['name'], 'email' => $entry['email'], 'rank' => str_starts_with($name, $query) ? 0 : 1];
        }
        usort($matches, static fn (array $a, array $b): int => ($a['rank'] <=> $b['rank']) ?: strcasecmp($a['name'], $b['name']));
        return array_map(
            static fn (array $entry): array => ['name' => $entry['name'], 'email' => $entry['email']],
            array_slice($matches, 0, max(1, min(20, $limit))),
        );
    }

    private function normalize(string $value): string
    {
        $value = Normalizer::normalize($value, Normalizer::FORM_D) ?: $value;
        $value = preg_replace('/\p{Mn}+/u', '', mb_strtolower($value));
        return trim(preg_replace('/[\p{P}\p{Z}\s]+/u', ' ', $value));
    }
}
