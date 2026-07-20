<?php

namespace App\Domain\Inventory;

final class ProductIdentity
{
    private const ACCENT_REPLACEMENTS = [
        "\u{00C0}" => 'A',
        "\u{00C1}" => 'A',
        "\u{00C2}" => 'A',
        "\u{00C3}" => 'A',
        "\u{00C4}" => 'A',
        "\u{00C7}" => 'C',
        "\u{00C8}" => 'E',
        "\u{00C9}" => 'E',
        "\u{00CA}" => 'E',
        "\u{00CB}" => 'E',
        "\u{00CC}" => 'I',
        "\u{00CD}" => 'I',
        "\u{00CE}" => 'I',
        "\u{00CF}" => 'I',
        "\u{00D2}" => 'O',
        "\u{00D3}" => 'O',
        "\u{00D4}" => 'O',
        "\u{00D5}" => 'O',
        "\u{00D6}" => 'O',
        "\u{00D9}" => 'U',
        "\u{00DA}" => 'U',
        "\u{00DB}" => 'U',
        "\u{00DC}" => 'U',
        "\u{00E0}" => 'a',
        "\u{00E1}" => 'a',
        "\u{00E2}" => 'a',
        "\u{00E3}" => 'a',
        "\u{00E4}" => 'a',
        "\u{00E7}" => 'c',
        "\u{00E8}" => 'e',
        "\u{00E9}" => 'e',
        "\u{00EA}" => 'e',
        "\u{00EB}" => 'e',
        "\u{00EC}" => 'i',
        "\u{00ED}" => 'i',
        "\u{00EE}" => 'i',
        "\u{00EF}" => 'i',
        "\u{00F2}" => 'o',
        "\u{00F3}" => 'o',
        "\u{00F4}" => 'o',
        "\u{00F5}" => 'o',
        "\u{00F6}" => 'o',
        "\u{00F9}" => 'u',
        "\u{00FA}" => 'u',
        "\u{00FB}" => 'u',
        "\u{00FC}" => 'u',
    ];

    public function internalCodeForId(int $productId): string
    {
        return sprintf('PRD-%06d', max(0, $productId));
    }

    public function normalizeDescription(string $description): string
    {
        $normalizedDescription = strtr(trim($description), self::ACCENT_REPLACEMENTS);
        $asciiDescription = function_exists('iconv')
            ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalizedDescription)
            : false;

        if ($asciiDescription !== false) {
            $normalizedDescription = $asciiDescription;
        }

        $normalizedDescription = mb_strtolower($normalizedDescription, 'UTF-8');
        $normalizedDescription = preg_replace('/[^a-z0-9]+/u', ' ', $normalizedDescription) ?? $normalizedDescription;

        return trim(preg_replace('/\s+/', ' ', $normalizedDescription) ?? $normalizedDescription);
    }

    public function descriptionsMatch(string $firstDescription, string $secondDescription): bool
    {
        $firstNormalizedDescription = $this->normalizeDescription($firstDescription);
        $secondNormalizedDescription = $this->normalizeDescription($secondDescription);

        return $firstNormalizedDescription !== ''
            && $firstNormalizedDescription === $secondNormalizedDescription;
    }
}
