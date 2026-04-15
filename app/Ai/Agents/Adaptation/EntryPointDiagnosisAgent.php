<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;
use Throwable;

#[Model('gpt-5.2')]
#[Temperature(0.6)]
#[Timeout(120)]
class EntryPointDiagnosisAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.entry-point-diagnosis.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'editorial_diagnosis' => $schema
                ->string()
                ->required()
                ->title('Editorial Diagnosis')
                ->description('Full editorial diagnosis block explaining what was cut and why.'),
            'format_specific_cut' => $schema
                ->object([
                    'cut_point' => $schema->string()->required()->title('Cut Point')->description('Chapter/paragraph or scene heading of the cut point.'),
                    'original_before_cut' => $schema->string()->required()->title('Original Before Cut')->description('Word count or scene count before the cut.'),
                    'cut_eliminates' => $schema->string()->required()->title('Cut Eliminates')->description('What type of content was cut: backstory, setting description, internal monologue, teaser, etc.'),
                    'must_reintroduce' => $schema->string()->required()->title('Must Reintroduce')->description('Crucial world-building or character establishment that was cut and must be re-introduced through action.'),
                ])->required()->withoutAdditionalProperties()->title('Format-Specific Cut'),
            'cold_open' => $schema
                ->string()
                ->required()
                ->title('Cold Open')
                ->description('Second-person present tense cold open prose, 120-180 words. Sensory grounding within first 50 words.'),
            'emotional_promise' => $schema
                ->string()
                ->required()
                ->title('Emotional Promise')
                ->description('One sentence: "The emotional promise of this cold open is: [NOUN]. A user arrives feeling [ADJECTIVE] and wanting to [VERB]."'),
        ];
    }
}
