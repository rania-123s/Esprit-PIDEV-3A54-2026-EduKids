<?php

namespace App\Form\Quiz;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * Transforms array of options to newline-separated string and back.
 */
class OptionsToTextTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string
    {
        if ($value === null || !\is_array($value)) {
            return '';
        }
        return implode("\n", $value);
    }

    public function reverseTransform(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        return array_map('trim', $lines ?: []);
    }
}
