SOURCE IP: {{ $title }}, {{ $author }}, {{ $year }}, {{ $format }}

FORMAT DETECTION:
{{ json_encode($formatDetection, JSON_PRETTY_PRINT) }}

IP AUDIT (for context):
{{ json_encode($ipAudit, JSON_PRETTY_PRINT) }}

---

You are synthesizing the complete Author Voice DNA Profile from per-chapter observation fragments. Each chapter was analysed independently for voice characteristics. Your job is to produce ONE complete, unified Voice DNA Profile covering the full source.

Total chapters processed: {{ $totalChapters }}

PER-CHAPTER VOICE OBSERVATION FRAGMENTS (in chapter order):

@foreach($voiceFragments as $i => $fragment)
--- CHAPTER {{ $i + 1 }} (Chapter ID: {{ $fragment['chapter_id'] ?? '?' }}) ---
{{ json_encode($fragment, JSON_PRETTY_PRINT) }}

@endforeach

---

SYNTHESIS INSTRUCTIONS:

1. SIGNATURE WRITING TECHNIQUES (8-12 total): From the per-chapter observations, identify the 8-12 most distinctive and consistent techniques. Prefer techniques that appear across multiple chapters. For each, select the most compelling direct quote from the fragments as evidence.

2. SENTENCE-LEVEL PATTERNS: Synthesize a coherent picture of the author's sentence rhythm from all chapter observations. Use the demonstrative quotes from fragments as evidence.

3. DICTION FINGERPRINT: Synthesize vocabulary clusters, register, and word frequency patterns from all chapters. Select 5-6 of the most distinctive diction examples from the fragments.

4. DIALOGUE FINGERPRINT PER MAJOR CHARACTER: For each character who appeared across multiple chapters, merge their dialogue observations into a single entry. If a character only appeared in one chapter, use that chapter's observation directly.

5. EMOTIONAL RANGE MAP: Synthesize across all chapters. For each register (TENSION, HUMOR, GRIEF, WONDER, FEAR, VIOLENCE, INTIMACY): use the strongest quote from any chapter's fragment. If a register is ABSENT across all chapters, mark it ABSENT.

6. PARAGRAPH ARCHITECTURE: Synthesize across all chapters.

7. MASTER RULE 1 — HARD BANS: Set universal_bans_acknowledged to true. For IP-specific bans: collect all ban candidates from all chapter fragments, deduplicate, select the strongest 6-10.

8. 14-POINT AUDIT PROTOCOL: Produce all 14 audit points specific to this IP, informed by the voice observations across all chapters.

Return the complete voice profile matching the full required schema.
