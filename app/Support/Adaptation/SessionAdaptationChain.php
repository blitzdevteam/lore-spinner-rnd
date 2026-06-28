<?php

declare(strict_types=1);

namespace App\Support\Adaptation;

use App\Jobs\Adaptation\ChoiceDesignJob;
use App\Jobs\Adaptation\ConsequenceMappingJob;
use App\Jobs\Adaptation\EditorialVerificationJob;
use App\Jobs\Adaptation\EntryPointDiagnosisJob;
use App\Jobs\Adaptation\RuntimeNarratorAssemblyJob;
use App\Jobs\Adaptation\SessionArchitectureJob;
use App\Jobs\Adaptation\SessionCloseJob;
use App\Models\Story;
use Illuminate\Support\Facades\Bus;

final class SessionAdaptationChain
{
    /**
     * @return array<int, object>
     */
    public static function jobsForSession(Story $story, int $sessionNumber): array
    {
        return [
            new EntryPointDiagnosisJob($story, $sessionNumber),
            new SessionArchitectureJob($story, $sessionNumber),
            new ChoiceDesignJob($story, $sessionNumber),
            new ConsequenceMappingJob($story, $sessionNumber),
            new SessionCloseJob($story, $sessionNumber),
            new EditorialVerificationJob($story, $sessionNumber),
            new RuntimeNarratorAssemblyJob($story, $sessionNumber),
        ];
    }

    /**
     * @param  array<int, int>  $sessionNumbers
     */
    public static function dispatchBatch(Story $story, array $sessionNumbers, ?callable $finally = null): void
    {
        $sessionNumbers = array_values(array_unique(array_map('intval', $sessionNumbers)));

        if ($sessionNumbers === []) {
            return;
        }

        $storyId = $story->id;
        $sessionChains = [];

        foreach ($sessionNumbers as $sessionNumber) {
            $sessionChains[] = self::jobsForSession($story, $sessionNumber);
        }

        $batch = Bus::batch($sessionChains)->onQueue('adaptation');

        if ($finally !== null) {
            $batch->finally($finally);
        }

        $batch->dispatch();
    }
}
