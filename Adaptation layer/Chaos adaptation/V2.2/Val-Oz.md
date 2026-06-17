A - Total and section sizes in S1 (copy this whole line)

php artisan tinker --execute='$p=App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->adaptation->sessionAdaptations()->where("session_number",1)->first()->runtime_narrator_prompt??=""; preg_match_all("/=== SECTION \d+[^=]*===/", $p, $m, PREG_OFFSET_CAPTURE); foreach($m[0] as $i=>$s){$end=isset($m[0][$i+1])?$m[0][$i+1][1]:strlen($p); echo str_pad($end-$s[1],7)." ".substr($s[0],0,50)."\n";} echo "TOTAL: ".strlen($p)."\n";'

7065    === SECTION 1 — STORY SPINE (Tier 1, always load
24907   === SECTION 2 — WORLD RULES (Tier 1, always load
1969    === SECTION 3 — CHARACTER CANON — STORYGUARD L
1522    === SECTION 4 — NARRATIVE ANCHORS — STORYGUARD
4694    === SECTION 5 — VOICE / TONAL CANON — STORYGUA
19610   === SECTION 6 — VOICE PROFILE (Tier 1, always lo
3951    === SECTION 7 — HARD BANS (MASTER RULE 1) (Tier 
363     === SECTION 8 — SYMBOLIC MEMORY (Tier 2/3, runti
2426    === SECTION 9 — STORY-NATIVE ALIGNMENT (Tier 2, 
10781   === SECTION 10 — PERSISTENT STATE SCHEMA (Tier 1
4517    === SECTION 11 — REACTIVITY RULES (Tier 1, alway
17499   === SECTION 12 — SESSION BEAT MAP + SOURCE MATER
875     === SECTION 13 — COLD OPEN / OPENING HANDOFF (Ti
8199    === SECTION 14 — AUTHORED CHOICE MOMENTS (Tier 1
9475    === SECTION 15 — CONSEQUENCE MAP + FREEFORM GUID
156     === SECTION 16 — EDITORIAL VERIFICATION SIGNAL (
11062   === SECTION 17 — NARRATION CONTRACT ===
TOTAL: 129831

B— Voice profile raw size (same for all sessions)

php artisan tinker --execute='echo strlen(json_encode(App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->adaptation->voice_profile??[]));'

43455

C — Pipeline output sizes per session
php artisan tinker --execute='foreach(App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->adaptation->sessionAdaptations()->orderBy("session_number")->get() as $s){echo "S{$s->session_number} arch=".strlen(json_encode($s->session_architecture??[]))." choice=".strlen(json_encode($s->session_choice_design??[]))." conseq=".strlen(json_encode($s->choice_consequence_map??[]))."\n";}'


S1 arch=16247 choice=69949 conseq=54291
S2 arch=15128 choice=70494 conseq=67297
S3 arch=16817 choice=70400 conseq=70842
S4 arch=17150 choice=76056 conseq=64442
S5 arch=17008 choice=70113 conseq=64178
S6 arch=16052 choice=74915 conseq=57058

D — Event content totals per session
php artisan tinker --execute='$id=App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->id; for($n=1;$n<=6;$n++){$r=App\Models\Event::join("chapters","events.chapter_id","=","chapters.id")->where("chapters.story_id",$id)->where("events.session_number",$n)->selectRaw("count(*) as cnt, sum(length(events.content)) as total")->first(); echo "S{$n}: ".$r->cnt." events, ".$r->total." chars content\n";}'


S1: 49 events, 49512 chars content
S2: 49 events, 47291 chars content
S3: 45 events, 33118 chars content
S4: 33 events, 34930 chars content
S5: 26 events, 24299 chars content
S6: 15 events, 11275 chars content


E — S1 section 12 size vs section 14+15 size
php artisan tinker --execute='$p=App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->adaptation->sessionAdaptations()->where("session_number",1)->first()->runtime_narrator_prompt??""; $s12=strpos($p,"=== SECTION 12"); $s13=strpos($p,"=== SECTION 13"); $s14=strpos($p,"=== SECTION 14"); $s15=strpos($p,"=== SECTION 15"); $s16=strpos($p,"=== SECTION 16"); $s17=strpos($p,"=== SECTION 17"); echo "S12(source+beatmap): ".($s13-$s12)."\nS14(choices): ".($s15-$s14)."\nS15(consequences): ".($s16-$s15)."\nS17(contract): ".(strlen($p)-$s17)."\n";'

S12(source+beatmap): 17499
S14(choices): 8199
S15(consequences): 9475
S17(contract): 11062

F — S5 section breakdown (another working session for comparison)
php artisan tinker --execute='$p=App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->adaptation->sessionAdaptations()->where("session_number",5)->first()->runtime_narrator_prompt??""; $s12=strpos($p,"=== SECTION 12"); $s13=strpos($p,"=== SECTION 13"); $s14=strpos($p,"=== SECTION 14"); $s15=strpos($p,"=== SECTION 15"); $s16=strpos($p,"=== SECTION 16"); echo "S12: ".($s13-$s12)."\nS14: ".($s15-$s14)."\nS15: ".($s16-$s15)."\nTOTAL: ".strlen($p)."\n";'
S12: 14673
S14: 8708
S15: 9778
TOTAL: 127945


G — mb_strlen actual character counts for S1/S5/S6
php artisan tinker --execute='foreach([1,5,6] as $n){$p=App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->adaptation->sessionAdaptations()->where("session_number",$n)->first()->runtime_narrator_prompt??""; echo "S{$n}: strlen=".strlen($p)." mb_strlen=".mb_strlen($p)."\n";}'
This shows the actual character count vs byte count gap — tells you how much headroom you really have.


S1: strlen=129831 mb_strlen=127917
S5: strlen=127945 mb_strlen=125957
S6: strlen=125462 mb_strlen=123444


H — §2 World Rules entry count and evidence field weight
php artisan tinker --execute='$a=App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->adaptation; $wr=$a->ip_trimming["world_rules"]??[]; $cats=["physics_technology","creatures_entities","geography_locations","social_systems","what_cannot_exist"]; foreach($cats as $c){$rules=$wr[$c]??[]; $ruleChars=array_sum(array_map(fn($r)=>strlen($r["rule"]??""),(array)$rules)); $evChars=array_sum(array_map(fn($r)=>strlen($r["evidence"]??""),(array)$rules)); echo str_pad($c,30)." rules=".count($rules)." rule_chars={$ruleChars} evidence_chars={$evChars}\n";}'
This tells you exactly how much of §2's 24,907 bytes comes from the evidence field vs the rule text itself. 

physics_technology             rules=46 rule_chars=4248 evidence_chars=475
creatures_entities             rules=45 rule_chars=3793 evidence_chars=582
geography_locations            rules=53 rule_chars=4374 evidence_chars=577
social_systems                 rules=41 rule_chars=3695 evidence_chars=469
what_cannot_exist              rules=41 rule_chars=0 evidence_chars=0



I — §14 rendered field weight per session (source_moment and choice_question are the heavy fields)
php artisan tinker --execute='foreach(App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->adaptation->sessionAdaptations()->orderBy("session_number")->get() as $s){$cd=$s->session_choice_design??[]; $bcq=array_sum(array_map(fn($c)=>strlen($c["choice_question"]??""),(array)($cd["branching_choices"]??[]))); $bco=array_sum(array_map(fn($c)=>array_sum(array_map(fn($o)=>strlen($o["text"]??"")+strlen($o["downstream_effect"]??""),$c["options"]??[])),($cd["branching_choices"]??[]))); $esm=array_sum(array_map(fn($c)=>strlen($c["source_moment"]??""),(array)($cd["emotional_choices"]??[]))); $ecq=array_sum(array_map(fn($c)=>strlen($c["choice_question"]??""),(array)($cd["emotional_choices"]??[]))); echo "S{$s->session_number} bc_questions={$bcq} bc_options={$bco} ec_source_moment={$esm} ec_questions={$ecq}\n";}'


S1 bc_questions=164 bc_options=2241 ec_source_moment=425 ec_questions=201
S2 bc_questions=185 bc_options=2250 ec_source_moment=429 ec_questions=181
S3 bc_questions=310 bc_options=2588 ec_source_moment=427 ec_questions=293
S4 bc_questions=224 bc_options=2723 ec_source_moment=544 ec_questions=281
S5 bc_questions=305 bc_options=2495 ec_source_moment=534 ec_questions=275
S6 bc_questions=216 bc_options=2458 ec_source_moment=651 ec_questions=246



J — §15 rendered field weight per session (immediate_effect, echo, payoff, defining_line)
php artisan tinker --execute='foreach(App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->adaptation->sessionAdaptations()->orderBy("session_number")->get() as $s){$cm=$s->choice_consequence_map??[]; $ie=$echo=$pay=$dl=0; foreach(($cm["branching_consequences"]??[]) as $c){foreach(($c["paths"]??[]) as $p){$ie+=strlen($p["immediate_effect"]??"")); $echo+=strlen($p["current_session_echo"]??"")); $pay+=strlen($p["next_session_payoff"]??"")); $dl+=strlen($p["defining_line_captured"]??=""));}} echo "S{$s->session_number}: immediate={$ie} echo={$echo} payoff={$pay} defining_line={$dl}\n";}'
Actually that one has syntax issues with nested parens in the cloud CLI. Use this version:

php artisan tinker --execute='foreach(App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->adaptation->sessionAdaptations()->orderBy("session_number")->get() as $s){$cm=$s->choice_consequence_map??[]; $t=[0,0,0,0]; foreach($cm["branching_consequences"]??[] as $c){foreach($c["paths"]??[] as $p){$t[0]+=strlen($p["immediate_effect"]??""); $t[1]+=strlen($p["current_session_echo"]??""); $t[2]+=strlen($p["next_session_payoff"]??""); $t[3]+=strlen($p["defining_line_captured"]??"");}} echo "S{$s->session_number} immed={$t[0]} echo={$t[1]} payoff={$t[2]} defline={$t[3]} total=".array_sum($t)."\n";}'



S1 immed=1920 echo=2115 payoff=2276 defline=605
S2 immed=1909 echo=2189 payoff=2497 defline=713
S3 immed=2299 echo=2433 payoff=2474 defline=743
S4 immed=2191 echo=2516 payoff=2579 defline=703
S5 immed=2021 echo=2060 payoff=2362 defline=602
S6 immed=2139 echo=2218 payoff=2113 defline=636



K — §10 persistent state schema raw size (story-global, same for all — tells us the ceiling)
php artisan tinker --execute='$ps=App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->adaptation->story_session_map["persistent_state_schema"]??[]; echo "objects=".count($ps["objects"]??[])." npcs=".count($ps["npcs"]??[])." flags=".count($ps["world_flags"]??[])."\n"; echo "raw_schema_chars=".strlen(json_encode($ps))."\n";'


objects=15 npcs=13 flags=13
raw_schema_chars=32931


S1 active=11 dormant=30
S2 active=11 dormant=30
S3 active=13 dormant=28
S4 active=14 dormant=27
S5 active=11 dormant=30
S6 active=8 dormant=33