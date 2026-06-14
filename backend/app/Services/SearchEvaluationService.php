<?php

namespace App\Services;

class SearchEvaluationService
{
    public function evaluate(array $queries, int $k = 5): array
    {
        $totals = ['precision_at_k' => 0, 'recall_at_k' => 0, 'mrr' => 0, 'ndcg_at_k' => 0];
        $details = [];

        foreach ($queries as $query) {
            $relevant = $query['relevant'];
            $retrieved = $query['retrieved'];
            $topK = array_slice($retrieved, 0, $k);
            $hits = array_values(array_intersect($topK, $relevant));
            $firstRelevantRank = null;
            foreach ($retrieved as $rank => $product) {
                if (in_array($product, $relevant, true)) {
                    $firstRelevantRank = $rank + 1;
                    break;
                }
            }

            $dcg = 0.0;
            foreach ($topK as $rank => $product) {
                if (in_array($product, $relevant, true)) {
                    $dcg += 1 / log($rank + 2, 2);
                }
            }
            $idealHits = min($k, count($relevant));
            $idcg = 0.0;
            for ($rank = 0; $rank < $idealHits; $rank++) {
                $idcg += 1 / log($rank + 2, 2);
            }

            $metrics = [
                'precision_at_k' => count($hits) / $k,
                'recall_at_k' => count($relevant) ? count($hits) / count($relevant) : 0,
                'mrr' => $firstRelevantRank ? 1 / $firstRelevantRank : 0,
                'ndcg_at_k' => $idcg ? $dcg / $idcg : 0,
            ];
            foreach ($metrics as $metric => $value) {
                $totals[$metric] += $value;
            }
            $details[] = ['query' => $query['query'], ...$metrics];
        }

        $count = count($queries);

        return [
            'queries' => $count,
            'k' => $k,
            'metrics' => array_map(fn (float|int $value) => $count ? round($value / $count, 4) : 0, $totals),
            'details' => $details,
        ];
    }
}
