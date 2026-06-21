Yes. That is exactly the missing layer.

The image generator should **not** be trusted as the final creative authority. You need an **AI Creative Director** layer that sits above generation and does two jobs:

```text
1. Finish and strengthen the prompt before generation.
2. Judge the generated image after generation and decide whether to accept, revise, or regenerate.
```

So the pipeline becomes less like:

```text
Prompt → Image
```

and more like:

```text
Story → Visual DNA → Draft Prompt → Creative Director → Final Prompt → Image → Image Critic → Revision Loop → Approved Asset
```

# Recommended pipeline

```text
Story / Event
   ↓
Extract Visual DNA
   ↓
Select Hero Moment
   ↓
Build Draft Prompt
   ↓
AI Creative Director finishes prompt
   ↓
Generate 2 to 4 image candidates
   ↓
AI Image Critic reviews outputs
   ↓
Best image passes?
   ├── Yes → Save approved asset
   └── No → Creative Director writes revision prompt → regenerate
```

The key is this:

```text
Prompt Builder writes the first draft.
Creative Director makes it art-directable.
Image Critic decides if the result actually worked.
```

---

# Add these agents/classes

In Laravel I’d structure it like this:

```text
app/Ai/VisualPrompts/
  Agents/
    VisualDnaAgent.php
    HeroMomentAgent.php
    PromptDraftAgent.php
    CreativeDirectorAgent.php
    ImageCriticAgent.php
    PromptRevisionAgent.php

  Services/
    StoryImagePipelineService.php
    ImageGenerationService.php

  Jobs/
    ExtractVisualDnaJob.php
    SelectHeroMomentJob.php
    BuildDraftPromptJob.php
    CreativeDirectPromptJob.php
    GenerateImageCandidatesJob.php
    ReviewGeneratedImagesJob.php
    ReviseAndRegenerateImageJob.php
    ApproveStoryImageJob.php
```

The most important new one is:

```php
CreativeDirectorAgent.php
```

Its job is not to invent a totally new image. Its job is to **finish the prompt like an art director**.

---

# Creative Director Agent

Input:

```json
{
  "visual_dna": {},
  "hero_moment": {},
  "draft_prompt": "",
  "target_use": "story card / hero image / session thumbnail / marketing still"
}
```

Output:

```json
{
  "final_prompt": "",
  "creative_direction": "",
  "composition_lock": "",
  "lighting_lock": "",
  "style_guardrails": [],
  "avoid_rules": [],
  "success_criteria": []
}
```

What it does:

```text
Adds stronger creative direction.
Locks composition.
Clarifies camera language.
Adds lighting behavior.
Adds realism rules.
Adds story-specific avoid rules.
Removes generic words.
Prevents AI drift.
```

This is where your masquerade prompt became good. The Creative Director identified:

```text
This is not fantasy concept art.
This is a real editorial party photograph.
The left side must stay empty.
The masks must be wearable, not creature heads.
The room must stay dark.
```

That is creative direction.

---

# Image Critic Agent

After images are generated, run a vision model against them.

Input:

```json
{
  "final_prompt": "",
  "visual_dna": {},
  "hero_moment": {},
  "generated_images": [
    {
      "image_id": 1,
      "image_url": ""
    }
  ]
}
```

Output:

```json
{
  "best_image_id": 1,
  "approved": false,
  "score": 72,
  "failure_reasons": [
    "The room is too bright",
    "The masks look like full animal heads",
    "The group is centered instead of right-weighted"
  ],
  "revision_instruction": "Regenerate with stronger negative space on the left, darker exposure, and wearable half-face masks instead of creature helmets."
}
```

This lets the AI behave like a creative director after the fact.

---

# The acceptance rubric

Do not ask the AI, “Is this good?”

Ask it to score against a rubric.

```json
{
  "composition_score": 0,
  "story_fidelity_score": 0,
  "genre_accuracy_score": 0,
  "period_accuracy_score": 0,
  "lighting_score": 0,
  "character_score": 0,
  "prop_score": 0,
  "drift_risk_score": 0,
  "commercial_appeal_score": 0,
  "overall_score": 0
}
```

Suggested pass rule:

```php
$approved = $overallScore >= 85
    && $compositionScore >= 80
    && $storyFidelityScore >= 80
    && $driftRiskScore <= 25;
```

So an image can look beautiful but still fail.

Example:

```text
Beautiful fantasy masquerade poster: fail.
Real dark editorial masquerade party photo: pass.
```

---

# Database additions

## `story_image_attempts`

Each generated image candidate gets stored.

```php
Schema::create('story_image_attempts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('story_image_prompt_id')->constrained()->cascadeOnDelete();

    $table->integer('attempt_number')->default(1);
    $table->string('image_url')->nullable();
    $table->string('provider')->nullable();

    $table->json('generation_metadata')->nullable();
    $table->json('critic_review')->nullable();

    $table->integer('overall_score')->nullable();
    $table->boolean('approved')->default(false);

    $table->timestamps();
});
```

## `story_image_prompt_revisions`

Track prompt changes.

```php
Schema::create('story_image_prompt_revisions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('story_image_prompt_id')->constrained()->cascadeOnDelete();

    $table->integer('revision_number')->default(1);
    $table->longText('previous_prompt');
    $table->longText('revised_prompt');

    $table->json('revision_reason')->nullable();
    $table->json('failure_modes_addressed')->nullable();

    $table->timestamps();
});
```

---

# Laravel job chain

For one hero image:

```php
Bus::chain([
    new ExtractVisualDnaJob($storyId),
    new SelectHeroMomentJob($storyId),
    new BuildDraftPromptJob($storyId),
    new CreativeDirectPromptJob($storyId),
    new GenerateImageCandidatesJob($storyId, count: 4),
    new ReviewGeneratedImagesJob($storyId),
    new ApproveOrReviseImageJob($storyId),
])->dispatch();
```

Inside `ApproveOrReviseImageJob`:

```php
if ($bestAttempt->approved) {
    ApproveStoryImageJob::dispatch($bestAttempt->id);
    return;
}

if ($prompt->revision_count >= 3) {
    $prompt->update(['status' => 'needs_human_review']);
    return;
}

ReviseAndRegenerateImageJob::dispatch($storyId);
```

Limit the loop:

```text
Max 3 revision cycles.
Generate 2 to 4 images per cycle.
After that, send to human review.
```

That controls cost.

---

# The important design rule

The AI Creative Director should not just say:

```text
Make it better.
```

It should produce **specific repair instructions**.

Bad revision:

```text
Make it more cinematic and realistic.
```

Good revision:

```text
The generated image drifted into fantasy poster style. Revise the prompt to make the masks half-face wearable masquerade masks, reduce the armor-like costume details, restore the large empty dark ballroom space on the left, and keep the guests clustered on the right like a real flash-lit party photograph.
```

That is useful.

---

# Final architecture

```text
Visual DNA Agent
Defines the story’s visual soul.

Hero Moment Agent
Finds the most image-worthy scene.

Prompt Draft Agent
Creates the first structured prompt.

Creative Director Agent
Turns the draft into a strong art-directable prompt.

Image Generator
Creates candidates.

Image Critic Agent
Reviews actual outputs against the prompt and story DNA.

Revision Agent
Writes targeted fixes.

Approval Layer
Saves the final usable image.
```

The big upgrade is this:

```text
Do not make image generation a one-shot job.
Make it a creative loop.
```

That is how you get from “AI made a picture” to “the system art-directed a story asset.”
