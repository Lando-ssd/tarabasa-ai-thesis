#!/bin/bash
BASE="http://127.0.0.1:8000"
PASS=0
FAIL=0

check() {
  local desc="$1"; local expected="$2"; local actual="$3"
  if [ "$expected" == "$actual" ]; then
    echo "  PASS: $desc (got $actual)"; PASS=$((PASS+1))
  else
    echo "  FAIL: $desc (expected $expected, got $actual)"; FAIL=$((FAIL+1))
  fi
}
jget() { php -r '$d=json_decode(file_get_contents("php://stdin"),true); $p=explode(".", $argv[1]); foreach($p as $k){ $d = $d[$k] ?? null; } echo is_bool($d) ? ($d?"true":"false") : (is_array($d)?json_encode($d):$d);' "$1"; }
DB=/home/claude/tarabasa-backend/database/tarabasa.sqlite

echo "############ SEED ############"
RESP=$(curl -s -X POST "$BASE/api/auth/register-teacher" -H "Content-Type: application/json" \
  -d '{"first_name":"Jenny","last_name":"Reyes","email":"jenny@deped.gov.ph","password":"password123","school_name":"Rizal Elementary","employee_id":"EMP-100"}')
TEACHER_TOKEN=$(echo "$RESP" | jget token)
TEACHER_ID=$(echo "$RESP" | jget teacher.id)
sqlite3 "$DB" "UPDATE teachers SET status='Active', free_generation_credits_remaining=20 WHERE id=$TEACHER_ID;"

RESP=$(curl -s -X POST "$BASE/api/class/create" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"CCS","grade_level":"Grade 2","section":"CCS","school_year":"2026-2027","group_tag":"phonics-group"}')
CLASS_ID=$(echo "$RESP" | jget class.id)

RESP=$(curl -s -X POST "$BASE/api/auth/register-parent" -H "Content-Type: application/json" \
  -d '{"first_name":"Maria","last_name":"Cruz","email":"maria@email.com","password":"password123"}')
PARENT_TOKEN=$(echo "$RESP" | jget token)

RESP=$(curl -s -X POST "$BASE/api/learner/create" -H "Authorization: Bearer $PARENT_TOKEN" -H "Content-Type: application/json" \
  -d '{"first_name":"Miguel","grade_level":"Grade 2","pin":"1234","placement_answers":[true,true,false]}')
LEARNER_ID=$(echo "$RESP" | jget learner.id)
LEARNER_CODE=$(echo "$RESP" | jget learner.learner_code)
curl -s -X POST "$BASE/api/learner/join-class" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d "{\"learner_code\":\"$LEARNER_CODE\",\"class_id\":$CLASS_ID}" > /dev/null

RESP=$(curl -s -X POST "$BASE/api/learner/login-pin" -H "Content-Type: application/json" \
  -d "{\"learner_code\":\"$LEARNER_CODE\",\"pin\":\"1234\"}")
LEARNER_TOKEN=$(echo "$RESP" | jget token)

# An Approved activity, directly assigned to Miguel
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d '{"topic":"Phonics — Letter Blending","grade_level":"Grade 2","game_type":"Read Aloud"}')
ACT1_ID=$(echo "$RESP" | jget activity.id)
curl -s -X POST "$BASE/api/activity/decision" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$ACT1_ID,\"decision\":\"Approve\"}" > /dev/null
curl -s -X POST "$BASE/api/activity/assign" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$ACT1_ID,\"learner_id\":$LEARNER_ID}" > /dev/null

# A second Approved activity assigned to the whole CLASS (should be lower priority than direct)
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d '{"topic":"Sight Words — High Frequency","grade_level":"Grade 2","game_type":"Read Aloud"}')
ACT2_ID=$(echo "$RESP" | jget activity.id)
curl -s -X POST "$BASE/api/activity/decision" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$ACT2_ID,\"decision\":\"Approve\"}" > /dev/null
curl -s -X POST "$BASE/api/activity/assign" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$ACT2_ID,\"class_id\":$CLASS_ID}" > /dev/null

# A third Approved activity, shared + unlocked via repository (Parent-initiated source)
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d '{"topic":"Vocabulary — Community Words","grade_level":"Grade 2","game_type":"Read Aloud"}')
ACT3_ID=$(echo "$RESP" | jget activity.id)
curl -s -X POST "$BASE/api/activity/decision" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$ACT3_ID,\"decision\":\"Approve\"}" > /dev/null
curl -s -X POST "$BASE/api/activity/share" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$ACT3_ID,\"price_type\":\"Free\"}" > /dev/null
LISTING_ID=$(sqlite3 "$DB" "SELECT id FROM open_repository_listings WHERE activity_id=$ACT3_ID;")
PARENT_ID=$(sqlite3 "$DB" "SELECT id FROM parents LIMIT 1;")
sqlite3 "$DB" "INSERT INTO repository_unlocks (listing_id, parent_id, learner_id, amount_paid) VALUES ($LISTING_ID, $PARENT_ID, $LEARNER_ID, 0);"

# A fourth Approved activity NOT assigned/unlocked to Miguel at all (for access-control rejection test)
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d '{"topic":"X","grade_level":"Grade 2","game_type":"Read Aloud"}')
UNRELATED_ACT_ID=$(echo "$RESP" | jget activity.id)
curl -s -X POST "$BASE/api/activity/decision" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$UNRELATED_ACT_ID,\"decision\":\"Approve\"}" > /dev/null

echo ""
echo "=== AVAILABLE ACTIVITIES (priority: direct > class > group) ==="
RESP=$(curl -s "$BASE/api/session/available-activities" -H "Authorization: Bearer $LEARNER_TOKEN")
COUNT=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["count"];')
echo "  (raw: $RESP)"
FIRST_SOURCE=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); $t=array_filter($d["options"],fn($o)=>$o["source"]=="Teacher"); echo count($t);')
check "direct assignment wins over class assignment (only 1 Teacher option, not 2)" "1" "$FIRST_SOURCE"
PARENT_OPT=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); $t=array_filter($d["options"],fn($o)=>$o["source"]=="Parent"); echo count($t);')
check "unlocked repository item also shows (Extra Practice)" "1" "$PARENT_OPT"

echo ""
echo "=== ACCESS CONTROL ==="
echo "--- learner cannot submit for an activity they have no access to ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/session/submit" -H "Authorization: Bearer $LEARNER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$UNRELATED_ACT_ID,\"audio_base64\":\"TESTSIM:85\"}")
CODE=$(echo "$RESP" | tail -1)
check "no-access activity returns 403" "403" "$CODE"

echo ""
echo "=== SUBMIT — happy paths & scoring ==="

echo "--- direct-assignment activity: high accuracy -> level up, points, streak, initiated_by=Teacher ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/session/submit" -H "Authorization: Bearer $LEARNER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$ACT1_ID,\"audio_base64\":\"TESTSIM:95\"}")
CODE=$(echo "$RESP" | tail -1); BODY=$(echo "$RESP" | head -n -1)
check "submit returns 201" "201" "$CODE"
check "95% accuracy -> level_after Proficient (from Developing)" "Proficient" "$(echo "$BODY" | jget level_after)"
check "level_changed true" "true" "$(echo "$BODY" | jget level_changed)"
POINTS=$(echo "$BODY" | jget points_earned)
check "points_earned = round(95/2) = 48" "48" "$POINTS"
INITIATED_BY=$(sqlite3 "$DB" "SELECT initiated_by FROM reading_sessions WHERE activity_id=$ACT1_ID;")
check "initiated_by correctly derived as Teacher (via assignment)" "Teacher" "$INITIATED_BY"
check "First Read badge awarded on first session" "1" "$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo in_array("First Read",$d["new_badges"])?1:0;')"

echo "--- repository-unlocked activity: initiated_by=Parent ---"
RESP=$(curl -s -X POST "$BASE/api/session/submit" -H "Authorization: Bearer $LEARNER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$ACT3_ID,\"audio_base64\":\"TESTSIM:85\"}")
INITIATED_BY=$(sqlite3 "$DB" "SELECT initiated_by FROM reading_sessions WHERE activity_id=$ACT3_ID;")
check "initiated_by correctly derived as Parent (via unlock)" "Parent" "$INITIATED_BY"

echo "--- low accuracy: level down, flagged, PersonalWordBank entries created ---"
WORDBANK_BEFORE=$(sqlite3 "$DB" "SELECT COUNT(*) FROM personal_word_bank WHERE learner_id=$LEARNER_ID;")
RESP=$(curl -s -X POST "$BASE/api/session/submit" -H "Authorization: Bearer $LEARNER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$ACT2_ID,\"audio_base64\":\"TESTSIM:50\"}")
check "50% accuracy -> level_after drops one tier (Proficient -> Developing)" "Developing" "$(echo "$RESP" | jget level_after)"
check "flagged_needs_attention true" "true" "$(echo "$RESP" | jget flagged_needs_attention)"
WORDBANK_AFTER=$(sqlite3 "$DB" "SELECT COUNT(*) FROM personal_word_bank WHERE learner_id=$LEARNER_ID;")
check "PersonalWordBank grew after <80% accuracy session" "1" "$([ "$WORDBANK_AFTER" -gt "$WORDBANK_BEFORE" ] && echo 1 || echo 0)"
NOTIF_ATTENTION=$(sqlite3 "$DB" "SELECT COUNT(*) FROM notifications WHERE learner_id=$LEARNER_ID AND type='Needs Attention';")
check "'Needs Attention' notification created" "1" "$([ "$NOTIF_ATTENTION" -ge 1 ] && echo 1 || echo 0)"

echo "--- mid-range accuracy: level stays the same ---"
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d '{"topic":"X","grade_level":"Grade 2","game_type":"Read Aloud"}')
ACT_MID_ID=$(echo "$RESP" | jget activity.id)
curl -s -X POST "$BASE/api/activity/decision" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$ACT_MID_ID,\"decision\":\"Approve\"}" > /dev/null
curl -s -X POST "$BASE/api/activity/assign" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$ACT_MID_ID,\"learner_id\":$LEARNER_ID}" > /dev/null
RESP=$(curl -s -X POST "$BASE/api/session/submit" -H "Authorization: Bearer $LEARNER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$ACT_MID_ID,\"audio_base64\":\"TESTSIM:80\"}")
check "80% accuracy -> level unchanged (Developing -> Developing)" "false" "$(echo "$RESP" | jget level_changed)"

echo ""
echo "=== UNCLEAR AUDIO HANDLING (3-attempt cap) ==="
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d '{"topic":"X","grade_level":"Grade 2","game_type":"Read Aloud"}')
ACT_UNCLEAR_ID=$(echo "$RESP" | jget activity.id)
curl -s -X POST "$BASE/api/activity/decision" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$ACT_UNCLEAR_ID,\"decision\":\"Approve\"}" > /dev/null
curl -s -X POST "$BASE/api/activity/assign" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$ACT_UNCLEAR_ID,\"learner_id\":$LEARNER_ID}" > /dev/null

RESP=$(curl -s -X POST "$BASE/api/session/submit" -H "Authorization: Bearer $LEARNER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$ACT_UNCLEAR_ID,\"audio_base64\":\"UNCLEARSIM\",\"attempt_number\":1}")
check "unclear attempt 1: unclear=true" "true" "$(echo "$RESP" | jget unclear)"
check "unclear attempt 1: not final_attempt" "false" "$(echo "$RESP" | jget final_attempt)"
SESSIONS_AFTER_UNCLEAR=$(sqlite3 "$DB" "SELECT COUNT(*) FROM reading_sessions WHERE activity_id=$ACT_UNCLEAR_ID;")
check "NO ReadingSession row created for an unclear attempt" "0" "$SESSIONS_AFTER_UNCLEAR"

RESP=$(curl -s -X POST "$BASE/api/session/submit" -H "Authorization: Bearer $LEARNER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$ACT_UNCLEAR_ID,\"audio_base64\":\"UNCLEARSIM\",\"attempt_number\":3}")
check "unclear attempt 3: final_attempt=true, graceful message" "true" "$(echo "$RESP" | jget final_attempt)"

echo ""
echo "=== ADAPTIVE DIAGNOSTIC STAIRCASE ==="

# Fresh learner for a clean diagnostic run (Developing preliminary estimate -> starts Medium)
RESP=$(curl -s -X POST "$BASE/api/learner/create" -H "Authorization: Bearer $PARENT_TOKEN" -H "Content-Type: application/json" \
  -d '{"first_name":"Sofia","grade_level":"Grade 2","pin":"5555","placement_answers":[true,true,false]}')
SOFIA_CODE=$(echo "$RESP" | jget learner.learner_code)
RESP=$(curl -s -X POST "$BASE/api/learner/login-pin" -H "Content-Type: application/json" -d "{\"learner_code\":\"$SOFIA_CODE\",\"pin\":\"5555\"}")
SOFIA_TOKEN=$(echo "$RESP" | jget token)

echo "--- Passage 1: Developing estimate -> starts at Medium tier ---"
RESP=$(curl -s "$BASE/api/session/next-diagnostic-passage" -H "Authorization: Bearer $SOFIA_TOKEN")
TIER1=$(echo "$RESP" | jget difficulty_tier)
ACT_D1=$(echo "$RESP" | jget activity_id)
check "Passage 1 starts at Medium (Developing preliminary estimate)" "Medium" "$TIER1"

echo "--- Passage 1 scores 95% -> staircase moves UP to Hard, not yet complete ---"
RESP=$(curl -s -X POST "$BASE/api/session/submit" -H "Authorization: Bearer $SOFIA_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$ACT_D1,\"audio_base64\":\"TESTSIM:95\",\"session_type\":\"Diagnostic\"}")
check "diagnostic_complete is false after passage 1 (95%, not yet at Hard)" "false" "$(echo "$RESP" | jget diagnostic_complete)"
check "diagnostic session awards 0 points (not a Practice session)" "0" "$(echo "$RESP" | jget points_earned)"

echo "--- Passage 2 correctly selected at Hard tier ---"
RESP=$(curl -s "$BASE/api/session/next-diagnostic-passage" -H "Authorization: Bearer $SOFIA_TOKEN")
TIER2=$(echo "$RESP" | jget difficulty_tier)
ACT_D2=$(echo "$RESP" | jget activity_id)
check "Passage 2 moved up to Hard" "Hard" "$TIER2"

echo "--- Passage 2 scores 95% at Hard -> staircase STOPS (already at ceiling), Proficient confirmed ---"
RESP=$(curl -s -X POST "$BASE/api/session/submit" -H "Authorization: Bearer $SOFIA_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$ACT_D2,\"audio_base64\":\"TESTSIM:95\",\"session_type\":\"Diagnostic\"}")
check "diagnostic_complete true at passage 2 (Hard + still >=90%)" "true" "$(echo "$RESP" | jget diagnostic_complete)"
check "final level_after is Proficient" "Proficient" "$(echo "$RESP" | jget level_after)"
check "First Reading Star badge awarded" "1" "$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo in_array("First Reading Star",$d["new_badges"])?1:0;')"

SOFIA_MASTERY=$(sqlite3 "$DB" "SELECT mastery_level FROM learners WHERE learner_code='$SOFIA_CODE';")
check "learner.mastery_level actually updated in DB to Proficient" "Proficient" "$SOFIA_MASTERY"

echo "--- requesting another diagnostic passage after completion is rejected ---"
RESP=$(curl -s -w "\n%{http_code}" "$BASE/api/session/next-diagnostic-passage" -H "Authorization: Bearer $SOFIA_TOKEN")
CODE=$(echo "$RESP" | tail -1)
check "next-diagnostic-passage after completion returns 409" "409" "$CODE"

echo "--- has_completed_diagnostic now true on learner/me ---"
RESP=$(curl -s "$BASE/api/learner/me" -H "Authorization: Bearer $SOFIA_TOKEN")
check "has_completed_diagnostic flips to true" "true" "$(echo "$RESP" | jget has_completed_diagnostic)"

echo ""
echo "--- Second fresh learner: mid-range accuracy stops after just 1 passage ---"
RESP=$(curl -s -X POST "$BASE/api/learner/create" -H "Authorization: Bearer $PARENT_TOKEN" -H "Content-Type: application/json" \
  -d '{"first_name":"Ana","grade_level":"Grade 1","pin":"7777","placement_answers":[false,false,false]}')
ANA_CODE=$(echo "$RESP" | jget learner.learner_code)
RESP=$(curl -s -X POST "$BASE/api/learner/login-pin" -H "Content-Type: application/json" -d "{\"learner_code\":\"$ANA_CODE\",\"pin\":\"7777\"}")
ANA_TOKEN=$(echo "$RESP" | jget token)
RESP=$(curl -s "$BASE/api/session/next-diagnostic-passage" -H "Authorization: Bearer $ANA_TOKEN")
ANA_TIER1=$(echo "$RESP" | jget difficulty_tier)
ANA_ACT1=$(echo "$RESP" | jget activity_id)
check "Beginning preliminary estimate -> starts at Easy" "Easy" "$ANA_TIER1"
RESP=$(curl -s -X POST "$BASE/api/session/submit" -H "Authorization: Bearer $ANA_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$ANA_ACT1,\"audio_base64\":\"TESTSIM:78\",\"session_type\":\"Diagnostic\"}")
check "78% (mid-range) stops after just 1 passage" "true" "$(echo "$RESP" | jget diagnostic_complete)"
check "final level is Beginning (matches Easy tier)" "Beginning" "$(echo "$RESP" | jget level_after)"

echo ""
echo "--- Third fresh learner: repeated low scores cap at 3 passages max ---"
RESP=$(curl -s -X POST "$BASE/api/learner/create" -H "Authorization: Bearer $PARENT_TOKEN" -H "Content-Type: application/json" \
  -d '{"first_name":"Ben","grade_level":"Grade 3","pin":"3333","placement_answers":[true,true,true]}')
BEN_CODE=$(echo "$RESP" | jget learner.learner_code)
RESP=$(curl -s -X POST "$BASE/api/learner/login-pin" -H "Content-Type: application/json" -d "{\"learner_code\":\"$BEN_CODE\",\"pin\":\"3333\"}")
BEN_TOKEN=$(echo "$RESP" | jget token)

# Proficient estimate -> starts Hard. Score low (55%) each time -> Hard->Medium->Easy, then cap.
RESP=$(curl -s "$BASE/api/session/next-diagnostic-passage" -H "Authorization: Bearer $BEN_TOKEN")
BEN_TIER1=$(echo "$RESP" | jget difficulty_tier); BEN_ACT1=$(echo "$RESP" | jget activity_id)
check "Ben starts at Hard (Proficient preliminary estimate)" "Hard" "$BEN_TIER1"
RESP=$(curl -s -X POST "$BASE/api/session/submit" -H "Authorization: Bearer $BEN_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$BEN_ACT1,\"audio_base64\":\"TESTSIM:55\",\"session_type\":\"Diagnostic\"}")
check "passage 1 (55% at Hard) not yet complete" "false" "$(echo "$RESP" | jget diagnostic_complete)"

RESP=$(curl -s "$BASE/api/session/next-diagnostic-passage" -H "Authorization: Bearer $BEN_TOKEN")
BEN_TIER2=$(echo "$RESP" | jget difficulty_tier); BEN_ACT2=$(echo "$RESP" | jget activity_id)
check "passage 2 moves DOWN to Medium" "Medium" "$BEN_TIER2"
RESP=$(curl -s -X POST "$BASE/api/session/submit" -H "Authorization: Bearer $BEN_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$BEN_ACT2,\"audio_base64\":\"TESTSIM:55\",\"session_type\":\"Diagnostic\"}")
check "passage 2 (55% at Medium) not yet complete" "false" "$(echo "$RESP" | jget diagnostic_complete)"

RESP=$(curl -s "$BASE/api/session/next-diagnostic-passage" -H "Authorization: Bearer $BEN_TOKEN")
BEN_TIER3=$(echo "$RESP" | jget difficulty_tier); BEN_ACT3=$(echo "$RESP" | jget activity_id)
check "passage 3 moves DOWN to Easy" "Easy" "$BEN_TIER3"
RESP=$(curl -s -X POST "$BASE/api/session/submit" -H "Authorization: Bearer $BEN_TOKEN" -H "Content-Type: application/json" \
  -d "{\"activity_id\":$BEN_ACT3,\"audio_base64\":\"TESTSIM:55\",\"session_type\":\"Diagnostic\"}")
check "passage 3 forces completion via the hard 3-passage cap (even at only 55%, still <70 which would normally continue)" "true" "$(echo "$RESP" | jget diagnostic_complete)"
check "final level uses whichever tier the LAST passage was at (Easy -> Beginning)" "Beginning" "$(echo "$RESP" | jget level_after)"

echo ""
echo "================================"
echo "RESULTS: $PASS passed, $FAIL failed"
echo "================================"
