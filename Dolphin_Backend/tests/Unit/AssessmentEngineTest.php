<?php

namespace Tests\Unit;

use App\Services\AssessmentEngine\ScoreCalculator;
use App\Services\AssessmentEngine\WeightRepository;
use Tests\TestCase;

class AssessmentEngineTest extends TestCase
{
    public function test_scores_for_known_words(): void
    {
        $repo = new WeightRepository();
        $calc = new ScoreCalculator($repo);
        $words = ['Assertive','Calm','Persuasive','Flexible'];
        $scores = $calc->scores($words, 'self');
        $this->assertArrayHasKey('A', $scores);
        $this->assertArrayHasKey('B', $scores);
        $this->assertArrayHasKey('C', $scores);
        $this->assertArrayHasKey('D', $scores);
        $this->assertArrayHasKey('avg', $scores);
        $this->assertTrue($scores['A'] >= 0 && $scores['A'] <= 1);
    }

    public function test_decision_approach_range(): void
    {
        $repo = new WeightRepository();
        $calc = new ScoreCalculator($repo);
        $value = $calc->decisionApproach(0.3, 0.6);
        $this->assertTrue($value >= 0 && $value <= 1);
    }
}
