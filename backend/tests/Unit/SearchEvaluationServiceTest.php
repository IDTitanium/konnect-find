<?php

namespace Tests\Unit;

use App\Services\SearchEvaluationService;
use PHPUnit\Framework\TestCase;

class SearchEvaluationServiceTest extends TestCase
{
    public function test_it_calculates_standard_information_retrieval_metrics(): void
    {
        $report = (new SearchEvaluationService)->evaluate([[
            'query' => 'power',
            'relevant' => ['Inverter', 'Generator'],
            'retrieved' => ['Inverter', 'Shoes', 'Generator'],
        ]], 3);

        $this->assertSame(0.6667, $report['metrics']['precision_at_k']);
        $this->assertSame(1.0, $report['metrics']['recall_at_k']);
        $this->assertSame(1.0, $report['metrics']['mrr']);
        $this->assertSame(0.9197, $report['metrics']['ndcg_at_k']);
    }
}
