<?php

namespace Tests\Unit;

use App\Services\FaceEmbeddingMatcher;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FaceEmbeddingMatcherTest extends TestCase
{
    private function vector(float $first, float $second = 0): array
    {
        return [$first, $second, ...array_fill(0, 510, 0.0)];
    }

    public function test_cosine_is_normalized_and_bounded(): void
    {
        $matcher = new FaceEmbeddingMatcher;
        $this->assertEqualsWithDelta(1, $matcher->cosineSimilarity($this->vector(2), $this->vector(5)), 1e-12);
        $this->assertEqualsWithDelta(0, $matcher->cosineSimilarity($this->vector(1), $this->vector(0, 1)), 1e-12);
        $this->assertEqualsWithDelta(-1, $matcher->cosineSimilarity($this->vector(1), $this->vector(-1)), 1e-12);
    }

    public function test_nearest_match_obeys_threshold_and_unknown_has_no_candidate(): void
    {
        $matcher = new FaceEmbeddingMatcher;
        $gallery = [10 => $this->vector(0, 1), 20 => $this->vector(0.8, 0.6)];
        $this->assertSame(20, $matcher->identify($this->vector(1), $gallery, 0.7));
        $this->assertNull($matcher->identify($this->vector(1), $gallery, 0.9));
        $this->assertNull($matcher->identify($this->vector(1), $gallery, null));
        $this->assertNull($matcher->identify($this->vector(1), [], 0.7));
        $this->assertNull($matcher->identify($this->vector(1), [10 => $this->vector(1), 20 => $this->vector(1)], 0.7));
    }

    public function test_invalid_vectors_fail_closed(): void
    {
        $matcher = new FaceEmbeddingMatcher;
        foreach ([[], array_fill(0, 512, 0), $this->vector(NAN), $this->vector(INF), ['1', ...array_fill(0, 511, 0)], array_fill(1, 512, 1)] as $vector) {
            try {
                $matcher->cosineSimilarity($vector, $this->vector(1));
                $this->fail('Invalid vector accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_invalid_thresholds_are_rejected(): void
    {
        $matcher = new FaceEmbeddingMatcher;
        foreach ([0, -0.1, 1.1, NAN, INF] as $threshold) {
            try {
                $matcher->identify($this->vector(1), [], $threshold);
                $this->fail('Invalid threshold accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
