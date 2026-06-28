<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Dump model:show --json output for every Eloquent model into a single markdown ERD doc.
 *
 * Usage:
 *   php artisan schema:dump-erd
 *   php artisan schema:dump-erd --path=docs/database-erd.md
 */
final class DumpModelErdCommand extends Command
{
    protected $signature = 'schema:dump-erd
        {--path=docs/database-erd.md : Output markdown file path (relative to base path)}';

    protected $description = 'Generate a markdown ERD doc from model:show --json for all Eloquent models';

    /** @var array<class-string<Model>, string> */
    private array $classToTable = [];

    public function handle(): int
    {
        $outputPath = base_path($this->option('path'));
        $modelClasses = $this->discoverModelClasses();

        if ($modelClasses === []) {
            $this->error('No Eloquent models found under app/Models or app/VoiceLab/Models.');

            return self::FAILURE;
        }

        $this->info('Inspecting '.count($modelClasses).' models…');

        /** @var array<string, array<string, mixed>> $models */
        $models = [];
        $failures = [];

        foreach ($modelClasses as $class) {
            try {
                $payload = $this->inspectModel($class);

                if ($payload === null) {
                    $failures[] = $class;

                    continue;
                }

                $models[$class] = $payload;
                $this->classToTable[$class] = (string) $payload['table'];
            } catch (Throwable $throwable) {
                $failures[] = $class.' ('.$throwable->getMessage().')';
            }
        }

        if ($models === []) {
            $this->error('Could not inspect any models. Is the database reachable?');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, $this->buildMarkdown($models, $failures));

        $this->info('Wrote ERD doc to: '.$outputPath);

        if ($failures !== []) {
            $this->warn('Skipped '.count($failures).' model(s):');
            foreach ($failures as $failure) {
                $this->line('  - '.$failure);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return list<class-string<Model>>
     */
    private function discoverModelClasses(): array
    {
        $classes = [];

        foreach ([app_path('Models'), app_path('VoiceLab/Models')] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = Str::after($file->getPathname(), app_path());
                $class = 'App'.str_replace(['/', '.php'], ['\\', ''], $relativePath);

                if (! class_exists($class)) {
                    continue;
                }

                if (! is_subclass_of($class, Model::class)) {
                    continue;
                }

                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @param  class-string<Model>  $class
     * @return array<string, mixed>|null
     */
    private function inspectModel(string $class): ?array
    {
        $exitCode = Artisan::call('model:show', [
            'model' => $class,
            '--json' => true,
        ]);

        if ($exitCode !== self::SUCCESS) {
            return null;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode(mb_trim(Artisan::output()), true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $models
     * @param  list<string>  $failures
     */
    private function buildMarkdown(array $models, array $failures): string
    {
        $generatedAt = now()->toIso8601String();
        $lines = [
            '# LoreSpinner Database ERD',
            '',
            '> Auto-generated from `php artisan model:show --json` for every Eloquent model.',
            '> Regenerate with `php artisan schema:dump-erd`.',
            '',
            '**Generated:** '.$generatedAt,
            '**Models documented:** '.count($models),
            '',
            '---',
            '',
            '## Entity Relationship Diagram',
            '',
            '```mermaid',
            'erDiagram',
        ];

        foreach ($this->buildMermaidEdges($models) as $edge) {
            $lines[] = '    '.$edge;
        }

        $lines[] = '```';
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Tables Overview';
        $lines[] = '';
        $lines[] = '| Model | Table | Database | Relations |';
        $lines[] = '|-------|-------|----------|-----------|';

        foreach ($models as $class => $payload) {
            $relationCount = count($payload['relations'] ?? []);
            $lines[] = sprintf(
                '| `%s` | `%s` | `%s` | %d |',
                class_basename($class),
                $payload['table'] ?? '—',
                $payload['database'] ?? '—',
                $relationCount,
            );
        }

        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Model Details';
        $lines[] = '';

        foreach ($models as $class => $payload) {
            $lines = array_merge($lines, $this->buildModelSection($class, $payload));
        }

        if ($failures !== []) {
            $lines[] = '---';
            $lines[] = '';
            $lines[] = '## Skipped Models';
            $lines[] = '';

            foreach ($failures as $failure) {
                $lines[] = '- `'.$failure.'`';
            }

            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Raw JSON';
        $lines[] = '';
        $lines[] = '<details>';
        $lines[] = '<summary>Full model:show payloads</summary>';
        $lines[] = '';
        $lines[] = '```json';
        $lines[] = json_encode($models, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '</details>';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  array<string, array<string, mixed>>  $models
     * @return list<string>
     */
    private function buildMermaidEdges(array $models): array
    {
        $edges = [];

        foreach ($models as $payload) {
            $fromTable = $this->mermaidId((string) ($payload['table'] ?? ''));

            if ($fromTable === '') {
                continue;
            }

            foreach ($payload['relations'] ?? [] as $relation) {
                if (! is_array($relation)) {
                    continue;
                }

                $edge = $this->relationToMermaidEdge($fromTable, $relation);

                if ($edge !== null) {
                    $edges[$edge] = $edge;
                }
            }
        }

        sort($edges);

        return array_values($edges);
    }

    /**
     * @param  array<string, mixed>  $relation
     */
    private function relationToMermaidEdge(string $fromTable, array $relation): ?string
    {
        $type = (string) ($relation['type'] ?? '');
        $name = $this->mermaidLabel((string) ($relation['name'] ?? 'relation'));
        $relatedClass = (string) ($relation['related'] ?? '');
        $toTable = $this->mermaidId($this->classToTable[$relatedClass] ?? '');

        if ($toTable === '' || $type === 'MorphTo') {
            return null;
        }

        return match ($type) {
            'HasMany', 'MorphMany' => sprintf('%s ||--o{ %s : %s', $fromTable, $toTable, $name),
            'HasOne', 'MorphOne' => sprintf('%s ||--o| %s : %s', $fromTable, $toTable, $name),
            'BelongsTo' => sprintf('%s }o--|| %s : %s', $fromTable, $toTable, $name),
            'BelongsToMany', 'MorphToMany', 'MorphedByMany' => sprintf('%s }o--o{ %s : %s', $fromTable, $toTable, $name),
            default => sprintf('%s }o--o{ %s : %s', $fromTable, $toTable, $name),
        };
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function buildModelSection(string $class, array $payload): array
    {
        $lines = [
            '### `'.$class.'`',
            '',
            '- **Table:** `'.($payload['table'] ?? '—').'`',
            '- **Database:** `'.($payload['database'] ?? '—').'`',
        ];

        if (! empty($payload['policy'])) {
            $lines[] = '- **Policy:** `'.$payload['policy'].'`';
        }

        $lines[] = '';
        $lines[] = '#### Attributes';
        $lines[] = '';
        $lines[] = '| Column | Type | Cast | Flags | Default |';
        $lines[] = '|--------|------|------|-------|---------|';

        foreach ($payload['attributes'] ?? [] as $attribute) {
            if (! is_array($attribute)) {
                continue;
            }

            $flags = collect(['increments', 'unique', 'nullable', 'fillable', 'hidden', 'appended'])
                ->filter(fn (string $flag): bool => (bool) ($attribute[$flag] ?? false))
                ->values()
                ->all();

            $lines[] = sprintf(
                '| `%s` | %s | %s | %s | %s |',
                $attribute['name'] ?? '—',
                $attribute['type'] ?? '—',
                $attribute['cast'] ?? '—',
                $flags === [] ? '—' : implode(', ', $flags),
                $attribute['default'] ?? '—',
            );
        }

        $lines[] = '';
        $lines[] = '#### Relations';
        $lines[] = '';

        $relations = $payload['relations'] ?? [];

        if ($relations === []) {
            $lines[] = '_None._';
        } else {
            $lines[] = '| Name | Type | Related |';
            $lines[] = '|------|------|---------|';

            foreach ($relations as $relation) {
                if (! is_array($relation)) {
                    continue;
                }

                $lines[] = sprintf(
                    '| `%s` | %s | `%s` |',
                    $relation['name'] ?? '—',
                    $relation['type'] ?? '—',
                    $relation['related'] ?? '—',
                );
            }
        }

        $events = $payload['events'] ?? [];
        $observers = $payload['observers'] ?? [];

        if ($events !== []) {
            $lines[] = '';
            $lines[] = '#### Events';
            $lines[] = '';
            $lines[] = '| Event | Class |';
            $lines[] = '|-------|-------|';

            foreach ($events as $event) {
                if (! is_array($event)) {
                    continue;
                }

                $lines[] = sprintf(
                    '| `%s` | `%s` |',
                    $event['event'] ?? '—',
                    $event['class'] ?? '—',
                );
            }
        }

        if ($observers !== []) {
            $lines[] = '';
            $lines[] = '#### Observers';
            $lines[] = '';
            $lines[] = '| Event | Observer |';
            $lines[] = '|-------|----------|';

            foreach ($observers as $observer) {
                if (! is_array($observer)) {
                    continue;
                }

                $observerList = $observer['observer'] ?? [];
                $observerText = is_array($observerList)
                    ? implode(', ', $observerList)
                    : (string) $observerList;

                $lines[] = sprintf(
                    '| `%s` | `%s` |',
                    $observer['event'] ?? '—',
                    $observerText,
                );
            }
        }

        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';

        return $lines;
    }

    private function mermaidId(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9_]/', '_')
            ->trim('_')
            ->toString();
    }

    private function mermaidLabel(string $value): string
    {
        return Str::of($value)
            ->replace('"', "'")
            ->toString();
    }
}
