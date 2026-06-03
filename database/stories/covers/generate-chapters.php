<?php

$apiKey = getenv('OPENAI_API_KEY') ?: die("Set OPENAI_API_KEY env var\n");
$outDir = __DIR__.'/chapters';

$chapters = json_decode(file_get_contents(__DIR__.'/../chapter images.json'), true)['chapters'];

$storyCategories = [
    "Hemingway's War" => 'Historical Adventure',
    'High Stakes' => 'Fantasy Adventure',
    'Pieces of Eight' => 'Adventure',
    'Time Machine' => 'Science Fiction',
    'B.U.G.S.' => 'Techno-Thriller',
    'Dream Police' => 'Supernatural Thriller',
    'Necropolis' => 'Supernatural Thriller',
    "PJ's" => 'Military Drama',
    'Wasteland' => 'Dystopian',
    'The Wonderful Wizard of Oz' => 'Fantasy Adventure',
];

$storyTeasers = [
    "Hemingway's War" => 'During World War II, Ernest Hemingway defies his role as a war correspondent and charges toward Paris.',
    'High Stakes' => 'A thrill seeker enters a secret interdimensional game to find his dead best friend alive inside a deadly world.',
    'Pieces of Eight' => 'A dive-shop owner and his son locate a legendary treasure ship and become targets of a brutal pirate king.',
    'Time Machine' => 'A disgraced young physicist is recruited by a billionaire to build a full-scale time machine.',
    'B.U.G.S.' => 'A renegade team of underground operatives uncovers a nuclear smuggling plot tied to a shadow conspiracy.',
    'Dream Police' => 'A black-ops agent who polices the dream world hunts a rogue scientist weaponizing dreams.',
    'Necropolis' => 'A federal investigator dies in a bombing and awakens as a Shadow Walker in a war between angels and demons.',
    "PJ's" => "A team of elite Air Force PJs discover that the hardest battlefield may be the one where there's no enemy to shoot, only lives to save and ghosts to outrun.",
    'Wasteland' => "Abandoned in a desert built from humanity's castoffs, an engineer must decide whether to escape or help the people that the world chose to forget.",
    'The Wonderful Wizard of Oz' => 'A storm carries you into the magical land of Oz, where witches whisper, lions tremble, and every step down the Yellow Brick Road changes who you are becoming.',
];

$slugMap = [
    "Hemingway's War" => 'hemingways-war',
    'High Stakes' => 'high-stakes',
    'Pieces of Eight' => 'pieces-of-eight',
    'Time Machine' => 'time-machine',
    'B.U.G.S.' => 'bugs',
    'Dream Police' => 'dream-police',
    'Necropolis' => 'necropolis',
    "PJ's" => 'pjs',
    'Wasteland' => 'wasteland',
    'The Wonderful Wizard of Oz' => 'the-wonderful-wizard-of-oz',
];

$total = count($chapters);
$done = 0;
$failed = 0;

foreach ($chapters as $ch) {
    $story = $ch['story'];
    $slug = $slugMap[$story] ?? 'unknown';
    $pos = $ch['position'];
    $file = "{$outDir}/{$slug}-ch{$pos}.png";

    if (file_exists($file)) {
        echo "SKIP: {$story} ch{$pos} (exists)\n";
        $done++;
        continue;
    }

    $category = $storyCategories[$story] ?? 'Fantasy';
    $storyTeaser = $storyTeasers[$story] ?? '';

    $prompt = "Create a cinematic, atmospheric scene illustration for a chapter in an interactive story.\n\n"
        ."STORY: \"{$story}\" - {$storyTeaser}\n"
        ."CHAPTER: \"{$ch['title']}\"\n"
        ."CHAPTER TEASER: {$ch['teaser']}\n"
        ."GENRE: {$category}\n\n"
        ."STYLE REQUIREMENTS:\n"
        ."- Dark, moody atmosphere with deep blacks and rich shadows\n"
        ."- Accent lighting using teal/cyan (#54f4da) and warm golden highlights\n"
        ."- Cinematic composition with dramatic depth of field\n"
        ."- Painterly digital art style, NOT photorealistic, NOT cartoonish\n"
        ."- Evocative scene that captures the chapter mood and setting\n"
        ."- No text, no letters, no words, no titles, no watermarks\n"
        ."- No UI elements, no borders, no frames\n"
        ."- Square composition suitable as a chapter thumbnail";

    echo "[".($done + 1)."/{$total}] {$story} ch{$pos}: {$ch['title']}... ";

    $payload = json_encode([
        'model' => 'gpt-image-1',
        'prompt' => $prompt,
        'n' => 1,
        'size' => '1024x1024',
        'quality' => 'low',
    ]);

    $curl = curl_init('https://api.openai.com/v1/images/generations');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer '.$apiKey],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
    ]);

    $resp = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($code !== 200) {
        $failed++;
        echo "FAILED (HTTP {$code})\n";
        continue;
    }

    $json = json_decode($resp, true);
    $b64 = $json['data'][0]['b64_json'] ?? null;

    if (! $b64) {
        $failed++;
        echo "NO DATA\n";
        continue;
    }

    file_put_contents($file, base64_decode($b64));
    $done++;
    $size = round(filesize($file) / 1024);
    echo "OK ({$size}KB)\n";
}

echo "\nDone: {$done}/{$total} generated, {$failed} failed.\n";
