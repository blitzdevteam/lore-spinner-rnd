<?php

declare(strict_types=1);

namespace App\Support\Adaptation;

use App\Models\Story;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class IpTrimmingIntegrity
{
    /**
     * @return Collection<int, \App\Models\Chapter>
     */
    public static function chaptersMissingFromIpTrimming(Story $story): Collection
    {
        $adaptation = $story->adaptation;

        if (empty($adaptation?->ip_trimming)) {
            return $story->chapters()->orderBy('position')->get();
        }

        $segmentChapterIds = collect($adaptation->ip_trimming['trimmed_source_text']['chapter_segments'] ?? [])
            ->pluck('chapter_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $story->chapters()
            ->orderBy('position')
            ->get()
            ->filter(fn ($chapter) => ! in_array((int) $chapter->id, $segmentChapterIds, true));
    }

    /**
     * @param  Collection<int, \App\Models\Chapter>  $missingChapters
     * @return array<int, int>
     */
    public static function sessionNumbersForChapters(Story $story, Collection $missingChapters): array
    {
        if ($missingChapters->isEmpty()) {
            return [];
        }

        return DB::table('events')
            ->whereIn('chapter_id', $missingChapters->pluck('id')->all())
            ->whereNotNull('session_number')
            ->distinct()
            ->orderBy('session_number')
            ->pluck('session_number')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
