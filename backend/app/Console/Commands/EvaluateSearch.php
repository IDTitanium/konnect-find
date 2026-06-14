<?php

namespace App\Console\Commands;

use App\Services\SearchEvaluationService;
use App\Services\SearchService;
use Illuminate\Console\Command;
use RuntimeException;

class EvaluateSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:evaluate
                            {--file= : JSON relevance set; defaults to database/evaluation/search_queries.json}
                            {--k=5 : Number of ranked results to evaluate}
                            {--json= : Write the complete machine-readable evaluation report to this path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate text retrieval using Precision, Recall, MRR, and nDCG';

    /**
     * Execute the console command.
     */
    public function handle(SearchService $search, SearchEvaluationService $evaluation): int
    {
        $path = $this->option('file') ?: database_path('evaluation/search_queries.json');
        throw_unless(is_file($path), RuntimeException::class, "Evaluation file not found: $path");
        $queries = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $k = max(1, (int) $this->option('k'));

        $cases = collect($queries)->map(fn (array $case) => [
            ...$case,
            'retrieved' => $search->retrieveText($case['query'], $k)
                ->map(fn (array $result) => $result['product']->name)
                ->all(),
        ])->all();
        $report = $evaluation->evaluate($cases, $k);
        $report['generated_at'] = now()->toIso8601String();
        $report['dataset'] = str_replace(base_path().DIRECTORY_SEPARATOR, '', realpath($path));

        $this->table(
            ['Metric', 'Score'],
            collect($report['metrics'])->map(fn ($score, $metric) => [$metric, number_format($score, 4)])->values(),
        );
        $this->newLine();
        $this->table(
            ['Query', "P@$k", "R@$k", 'MRR', "nDCG@$k"],
            collect($report['details'])->map(fn (array $detail) => [
                $detail['query'],
                number_format($detail['precision_at_k'], 3),
                number_format($detail['recall_at_k'], 3),
                number_format($detail['mrr'], 3),
                number_format($detail['ndcg_at_k'], 3),
            ])->all(),
        );

        if ($outputPath = $this->option('json')) {
            $directory = dirname($outputPath);
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($outputPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
            $this->components->info("Evaluation report written to $outputPath.");
        }

        return self::SUCCESS;
    }
}
