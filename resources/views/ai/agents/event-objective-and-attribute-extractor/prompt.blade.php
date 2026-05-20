@php
    use Illuminate\Support\Collection;

    /**
     * @var Collection<int, \App\Models\Event>|null $previousEvents Events before target (positions -5 to -1)
     * @var \App\Models\Event $targetEvent The target event (position 0)
     * @var Collection<int, \App\Models\Event>|null $nextEvents Events after target (positions +1 to +3)
     *
     * Content is capped at 3000 chars per event to stay within token budget.
     * The target event gets a larger cap (5000) since it is the primary focus.
     */
    $cap = fn(string $text, int $limit): string =>
        mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) . '…' : $text;
@endphp
<events>
    @foreach($previousEvents ?? [] as $event)
        <event position="-{{ $previousEvents->count() - $loop->index }}">
            <title>{{ $event->title }}</title>
            <objective>
                {{ $event->objectives }}
            </objective>
            <attributes>
                {{ $event->attributes }}
            </attributes>
            <content>
                {{ $cap($event->content, 3000) }}
            </content>
        </event>
    @endforeach

    <event position="0">
        <title>{{ $targetEvent->title }}</title>
        <objective></objective>
        <attributes></attributes>
        <content>
            {{ $cap($targetEvent->content, 5000) }}
        </content>
    </event>

    @foreach($nextEvents ?? [] as $event)
        <event position="+{{ $loop->iteration }}">
            <title>{{ $event->title }}</title>
            <objective></objective>
            <attributes></attributes>
            <content>
                {{ $cap($event->content, 3000) }}
            </content>
        </event>
    @endforeach
</events>
