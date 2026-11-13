<?php

namespace App\Services;

use InvalidArgumentException;

/** Pure server-side vector math. Enrollment/attendance must filter eligible gallery rows. */
class FaceEmbeddingMatcher
{
    public function cosineSimilarity(array $left, array $right): float
    {
        $left = $this->normalize($left);
        $right = $this->normalize($right);
        $score = 0.0;
        foreach ($left as $index => $value) {
            $score += $value * $right[$index];
        }

        return max(-1.0, min(1.0, $score));
    }

    /** @param array<int, array<float>> $gallery employee internal ID => compatible embedding */
    public function identify(array $probe, array $gallery, ?float $threshold): ?int
    {
        // No guessed threshold: null means recognition has not been calibrated/enabled.
        if ($threshold === null) {
            return null;
        }
        if (! is_finite($threshold) || $threshold <= 0 || $threshold > 1) {
            throw new InvalidArgumentException('Invalid face similarity threshold.');
        }
        $bestId = null;
        $bestScore = -INF;
        $tied = false;
        $this->normalize($probe);
        foreach ($gallery as $employeeId => $embedding) {
            if (! is_int($employeeId) || $employeeId <= 0) {
                throw new InvalidArgumentException('Gallery requires internal employee IDs.');
            }
            $score = $this->cosineSimilarity($probe, $embedding);
            if (abs($score - $bestScore) <= 1e-12) {
                $tied = true;
            } elseif ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $employeeId;
                $tied = false;
            }
        }

        return ! $tied && $bestScore >= $threshold ? $bestId : null;
    }

    private function normalize(array $embedding): array
    {
        if (count($embedding) !== 512 || ! array_is_list($embedding)) {
            throw new InvalidArgumentException('Expected a 512-dimensional embedding.');
        }
        $scale = 0.0;
        foreach ($embedding as $value) {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException('Embedding contains invalid values.');
            }
            $scale = max($scale, abs($value));
        }
        if ($scale <= 1e-12) {
            throw new InvalidArgumentException('Embedding has zero magnitude.');
        }
        $values = array_map(fn ($value) => $value / $scale, $embedding);
        $norm = sqrt(array_sum(array_map(fn ($value) => $value * $value, $values)));

        return array_map(fn ($value) => $value / $norm, $values);
    }
}
