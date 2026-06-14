<?php

namespace App\Services;

class VectorMath
{
    public static function cosine(array $left, array $right): float
    {
        if (count($left) !== count($right) || $left === []) {
            return 0;
        }

        $dot = $leftMagnitude = $rightMagnitude = 0.0;
        foreach ($left as $index => $value) {
            $dot += $value * $right[$index];
            $leftMagnitude += $value * $value;
            $rightMagnitude += $right[$index] * $right[$index];
        }

        return ($leftMagnitude && $rightMagnitude) ? $dot / (sqrt($leftMagnitude) * sqrt($rightMagnitude)) : 0;
    }

    public static function normalize(array $vector): array
    {
        $magnitude = sqrt(array_sum(array_map(fn (float|int $value) => $value * $value, $vector)));

        return $magnitude
            ? array_map(fn (float|int $value) => round($value / $magnitude, 8), $vector)
            : $vector;
    }

    public static function pgVector(array $vector): string
    {
        return '['.implode(',', $vector).']';
    }
}
