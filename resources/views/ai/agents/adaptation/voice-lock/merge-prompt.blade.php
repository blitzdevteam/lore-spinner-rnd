SOURCE IP: {{ $title }}, {{ $author }}, {{ $year }}, {{ $format }}

FORMAT DETECTION:
{{ json_encode($formatDetection, JSON_PRETTY_PRINT) }}

PHASE 1 AUDIT (scorecard — constitutional input):
{{ json_encode($ipAudit, JSON_PRETTY_PRINT) }}

---

You are synthesizing the complete Voice DNA Profile from per-chapter observation fragments. Each chapter was analysed independently. Produce ONE unified profile matching Deliverable 1A (NOVEL) or 1B (SCREENPLAY) based on detected_format.

Total chapters processed: {{ $totalChapters }}

PER-CHAPTER VOICE OBSERVATION FRAGMENTS (in chapter order):

@foreach($voiceFragments as $i => $fragment)
--- CHAPTER {{ $i + 1 }} (Chapter ID: {{ $fragment['chapter_id'] ?? '?' }}) ---
{{ json_encode($fragment, JSON_PRETTY_PRINT) }}

@endforeach

---

SYNTHESIS INSTRUCTIONS:

1. Set profile_type to NOVELIST or SCREENWRITER based on format detection.

2. SIGNATURE WRITING TECHNIQUES (8-12): merge across chapters; prefer techniques appearing in multiple chapters; include frequency.

3. SENTENCE-LEVEL PATTERNS / ACTION LINE METRICS: synthesize coherent metrics from all fragments.

4. DICTION FINGERPRINT: merge vocabulary clusters and distinctive quotes.

5. NOVELIST ONLY: narrator_perspective, paragraph_architecture, dialogue_tag_patterns.

6. SCREENWRITER ONLY: action_line_metrics, screenplay_structure_metrics, emotional_vocabulary_hierarchy, screenplay_to_prose_protocol.

7. DIALOGUE FINGERPRINT PER MAJOR CHARACTER: merge cross-chapter observations; require 3+ distinguishing markers per character.

8. EMOTIONAL RANGE MAP: strongest quote per register across all chapters.

9. COLLOCATION FINGERPRINT: deduplicate and expand to 15-20 pairs from chapter collocation_candidates.

10. NEGATIVE SPACE MAP: deduplicate to minimum 5 entries from chapter negative_space_candidates.

11. SHOW/EXPLAIN RATIO and COMPARATIVE EXCLUSION (2-3 neighbors): synthesize from full fragment set.

12. MASTER RULE 1: universal_bans_acknowledged true; 6-10 IP-specific bans from ban candidates.

13. 14-POINT AUDIT PROTOCOL: exactly 14 IP-specific runtime audit points.

Return the complete voice profile matching the full required schema.
