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

RESP=$(curl -s -X POST "$BASE/api/auth/register-parent" -H "Content-Type: application/json" \
  -d '{"first_name":"Maria","last_name":"Cruz","email":"maria@email.com","password":"password123"}')
PARENT_TOKEN=$(echo "$RESP" | jget token)
RESP=$(curl -s -X POST "$BASE/api/learner/create" -H "Authorization: Bearer $PARENT_TOKEN" -H "Content-Type: application/json" \
  -d '{"first_name":"Miguel","grade_level":"Grade 2","pin":"1234","placement_answers":[true,true,false]}')
LEARNER_ID=$(echo "$RESP" | jget learner.id)
LEARNER_CODE=$(echo "$RESP" | jget learner.learner_code)

RESP=$(curl -s -X POST "$BASE/api/class/create" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"CCS","grade_level":"Grade 2","section":"CCS","school_year":"2026-2027"}')
CLASS_ID=$(echo "$RESP" | jget class.id)
curl -s -X POST "$BASE/api/learner/join-class" -H "Authorization: Bearer $TEACHER_TOKEN" -H "Content-Type: application/json" \
  -d "{\"learner_code\":\"$LEARNER_CODE\",\"class_id\":$CLASS_ID}" > /dev/null

# Seed a Struggling word for Miguel so we can verify re-serving
sqlite3 "$DB" "INSERT INTO personal_word_bank (learner_id, word, mastery_status, times_drilled) VALUES ($LEARNER_ID, 'balloon', 'Struggling', 1);"

echo ""
echo "=== GENERATE ==="

echo "--- happy path: Read Aloud, no target learner, curriculum match found ---"
CREDITS_START=$(sqlite3 "$DB" "SELECT free_generation_credits_remaining FROM teachers WHERE id=$TEACHER_ID;")
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"topic":"Phonics — Letter Blending","grade_level":"Grade 1","game_type":"Read Aloud"}')
CODE=$(echo "$RESP" | tail -1); BODY=$(echo "$RESP" | head -n -1)
check "generate Read Aloud returns 201" "201" "$CODE"
MATCHED=$(echo "$BODY" | jget activity.curriculum_match.matched)
check "curriculum match found for exact topic" "true" "$MATCHED"
CREDITS=$(echo "$BODY" | jget free_generation_credits_remaining)
check "credits decremented by exactly 1" "$((CREDITS_START-1))" "$CREDITS"
DIFF=$(echo "$BODY" | jget activity.difficulty_tier)
check "no target learner -> difficulty defaults to Medium" "Medium" "$DIFF"

echo "--- no curriculum match for made-up topic ---"
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"topic":"Nonexistent Topic XYZ","grade_level":"Grade 1","game_type":"Read Aloud"}')
MATCHED=$(echo "$RESP" | jget activity.curriculum_match.matched)
check "no match found for made-up topic" "false" "$MATCHED"

echo "--- Letter-Sound Match: min 5 pairs, plausible (real) distractor sounds ---"
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"topic":"Phonics — Consonant Sounds","grade_level":"Grade 1","game_type":"Letter-Sound Match"}')
PAIR_COUNT=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo count($d["activity"]["content"]["letterSoundPairs"]);')
check "Letter-Sound Match has >= 5 pairs" "5" "$PAIR_COUNT"
FIRST_DISTRACTOR=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["activity"]["content"]["letterSoundPairs"][0]["distractorSounds"][0];')
REAL_SOUNDS="buh muh duh puh tuh sss nuh luh ruh fff guh huh kuh juh wuh vvv yuh zzz"
check "distractor is a real consonant sound, not gibberish" "1" "$(echo "$REAL_SOUNDS" | grep -qw "$FIRST_DISTRACTOR" && echo 1 || echo 0)"

echo "--- Word Builder: uses curriculum sampleVocabulary when present ---"
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"topic":"Phonics — Letter Blending","grade_level":"Grade 1","game_type":"Word Builder"}')
WORDS=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo implode(",", array_column($d["activity"]["content"]["targetWords"],"word"));')
check "targetWords drawn from seeded sampleVocabulary (cat/dog/sun/etc)" "1" "$(echo "$WORDS" | grep -qE "cat|dog|sun|mat|hat" && echo 1 || echo 0)"
WORD_COUNT=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo count($d["activity"]["content"]["targetWords"]);')
check "Word Builder has >= 5 target words" "1" "$([ "$WORD_COUNT" -ge 5 ] && echo 1 || echo 0)"

echo "--- Word Builder targeting Miguel: re-serves his Struggling word 'balloon' ---"
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"topic\":\"Phonics — Letter Blending\",\"grade_level\":\"Grade 1\",\"game_type\":\"Word Builder\",\"target_learner_id\":$LEARNER_ID}")
WORDS=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo implode(",", array_column($d["activity"]["content"]["targetWords"],"word"));')
check "struggling word 'balloon' woven into new activity" "1" "$(echo "$WORDS" | grep -qw "balloon" && echo 1 || echo 0)"
DIFF=$(echo "$RESP" | jget activity.difficulty_tier)
check "target learner Developing -> difficulty tier Medium" "Medium" "$DIFF"

echo "--- Sentence Scramble + Picture-Word Match + Trace-and-Write shapes are correct ---"
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d '{"topic":"Vocabulary — Family and School","grade_level":"Grade 1","game_type":"Sentence Scramble"}')
HAS_SCRAMBLED=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo isset($d["activity"]["content"]["scrambledSentences"])?"1":"0";')
check "Sentence Scramble produces scrambledSentences" "1" "$HAS_SCRAMBLED"

RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d '{"topic":"Vocabulary — Family and School","grade_level":"Grade 1","game_type":"Picture-Word Match"}')
HAS_PAIRS=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo isset($d["activity"]["content"]["wordImagePairs"])?"1":"0";')
check "Picture-Word Match produces wordImagePairs" "1" "$HAS_PAIRS"

RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d '{"topic":"Vocabulary — Family and School","grade_level":"Grade 1","game_type":"Trace-and-Write"}')
HAS_TRACE=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo isset($d["activity"]["content"]["traceWords"])?"1":"0";')
check "Trace-and-Write produces traceWords" "1" "$HAS_TRACE"

echo "--- invalid game_type rejected ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d '{"topic":"X","grade_level":"Grade 1","game_type":"Rhyme Finder"}')
CODE=$(echo "$RESP" | tail -1)
check "fake gameType 'Rhyme Finder' rejected with 422" "422" "$CODE"

echo "--- credit exhaustion blocks generation, correct message per status ---"
sqlite3 "$DB" "UPDATE teachers SET free_generation_credits_remaining=0 WHERE id=$TEACHER_ID;"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d '{"topic":"X","grade_level":"Grade 1","game_type":"Read Aloud"}')
CODE=$(echo "$RESP" | tail -1); BODY=$(echo "$RESP" | head -n -1)
check "0 credits blocks generation with 403" "403" "$CODE"
ERR=$(echo "$BODY" | jget error)
check "Active teacher at 0 credits told to share, not to wait" "1" "$(echo "$ERR" | grep -q "Share an approved activity" && echo 1 || echo 0)"

echo "--- Pending teacher at 0 credits gets 'wait for approval' message ---"
RESP=$(curl -s -X POST "$BASE/api/auth/register-teacher" -H "Content-Type: application/json" \
  -d '{"first_name":"New","last_name":"Teacher","email":"pending@deped.gov.ph","password":"password123","school_name":"S","employee_id":"EMP-777"}')
PENDING_TOKEN=$(echo "$RESP" | jget token)
PENDING_TEACHER_ID=$(echo "$RESP" | jget teacher.id)
sqlite3 "$DB" "UPDATE teachers SET free_generation_credits_remaining=0 WHERE id=$PENDING_TEACHER_ID;"
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $PENDING_TOKEN" \
  -H "Content-Type: application/json" -d '{"topic":"X","grade_level":"Grade 1","game_type":"Read Aloud"}')
ERR=$(echo "$RESP" | jget error)
check "Pending teacher at 0 credits told to wait for approval" "1" "$(echo "$ERR" | grep -q "Wait for Admin approval" && echo 1 || echo 0)"

# restore credits for the rest of the tests
sqlite3 "$DB" "UPDATE teachers SET free_generation_credits_remaining=5 WHERE id=$TEACHER_ID;"

echo ""
echo "=== DECISION (Approve / Edit / Reject) ==="

RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d '{"topic":"Sight Words — High Frequency","grade_level":"Grade 1","game_type":"Read Aloud"}')
DRAFT_ID=$(echo "$RESP" | jget activity.id)

echo "--- Approve as-is ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/decision" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$DRAFT_ID,\"decision\":\"Approve\"}")
CODE=$(echo "$RESP" | tail -1); BODY=$(echo "$RESP" | head -n -1)
check "approve returns 200" "200" "$CODE"
check "status becomes Approved" "Approved" "$(echo "$BODY" | jget status)"

echo "--- cannot decide on an already-Approved activity again ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/decision" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$DRAFT_ID,\"decision\":\"Reject\"}")
CODE=$(echo "$RESP" | tail -1)
check "deciding on non-Draft returns 409" "409" "$CODE"

echo "--- Edit + Approve combined action ---"
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d '{"topic":"Sight Words — High Frequency","grade_level":"Grade 1","game_type":"Read Aloud"}')
DRAFT2_ID=$(echo "$RESP" | jget activity.id)
RESP=$(curl -s -X POST "$BASE/api/activity/decision" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"activity_id\":$DRAFT2_ID,\"decision\":\"Edit\",\"passage_text\":\"Teacher-edited passage.\",\"content\":{\"passageText\":\"Teacher-edited passage.\"}}")
check "edit+approve returns Approved in one action" "Approved" "$(echo "$RESP" | jget status)"

echo "--- Edit with <5 items gives soft warning, does NOT block ---"
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d '{"topic":"Phonics — Letter Blending","grade_level":"Grade 1","game_type":"Word Builder"}')
DRAFT3_ID=$(echo "$RESP" | jget activity.id)
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/decision" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"activity_id\":$DRAFT3_ID,\"decision\":\"Edit\",\"content\":{\"passageText\":\"x\",\"targetWords\":[{\"word\":\"cat\",\"scrambledLetters\":[\"t\",\"a\",\"c\"],\"imageHint\":\"cat\"}]}}")
CODE=$(echo "$RESP" | tail -1); BODY=$(echo "$RESP" | head -n -1)
check "editing down to 1 word is ALLOWED (200, not blocked)" "200" "$CODE"
check "response includes a soft warning, not a hard error" "1" "$(echo "$BODY" | grep -q '"warning"' && echo 1 || echo 0)"

echo "--- Reject ---"
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d '{"topic":"X","grade_level":"Grade 1","game_type":"Read Aloud"}')
DRAFT4_ID=$(echo "$RESP" | jget activity.id)
RESP=$(curl -s -X POST "$BASE/api/activity/decision" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$DRAFT4_ID,\"decision\":\"Reject\"}")
check "reject sets status to Rejected" "Rejected" "$(echo "$RESP" | jget status)"

echo "--- another teacher cannot decide on this teacher's activity ---"
RESP=$(curl -s -X POST "$BASE/api/auth/register-teacher" -H "Content-Type: application/json" \
  -d '{"first_name":"Other","last_name":"Teacher","email":"other2@deped.gov.ph","password":"password123","school_name":"S","employee_id":"EMP-888"}')
OTHER_TOKEN=$(echo "$RESP" | jget token)
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/decision" -H "Authorization: Bearer $OTHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$DRAFT_ID,\"decision\":\"Approve\"}")
CODE=$(echo "$RESP" | tail -1)
check "other teacher deciding on this activity returns 404 (ownership enforced)" "404" "$CODE"

echo ""
echo "=== ASSIGN ==="

echo "--- assign to exactly one target: learner_id (happy path) ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/assign" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$DRAFT_ID,\"learner_id\":$LEARNER_ID}")
CODE=$(echo "$RESP" | tail -1)
check "assign to learner returns 201" "201" "$CODE"

echo "--- assign with ZERO targets rejected ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/assign" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$DRAFT_ID}")
CODE=$(echo "$RESP" | tail -1)
check "zero targets returns 422" "422" "$CODE"

echo "--- assign with MULTIPLE targets rejected ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/assign" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$DRAFT_ID,\"learner_id\":$LEARNER_ID,\"class_id\":$CLASS_ID}")
CODE=$(echo "$RESP" | tail -1)
check "learner_id + class_id together returns 422" "422" "$CODE"

echo "--- assigning a non-Approved (Draft) activity rejected ---"
RESP=$(curl -s -X POST "$BASE/api/activity/generate" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d '{"topic":"X","grade_level":"Grade 1","game_type":"Read Aloud"}')
STILL_DRAFT_ID=$(echo "$RESP" | jget activity.id)
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/assign" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$STILL_DRAFT_ID,\"class_id\":$CLASS_ID}")
CODE=$(echo "$RESP" | tail -1)
check "assigning a Draft activity returns 422" "422" "$CODE"

echo "--- assign to class_id (happy path) ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/assign" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$DRAFT2_ID,\"class_id\":$CLASS_ID}")
CODE=$(echo "$RESP" | tail -1)
check "assign to class_id returns 201" "201" "$CODE"

echo ""
echo "=== SHARE ==="

echo "--- share Free (happy path), credits +2 ---"
CREDITS_BEFORE=$(sqlite3 "$DB" "SELECT free_generation_credits_remaining FROM teachers WHERE id=$TEACHER_ID;")
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/share" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$DRAFT_ID,\"price_type\":\"Free\"}")
CODE=$(echo "$RESP" | tail -1); BODY=$(echo "$RESP" | head -n -1)
check "share returns 201" "201" "$CODE"
CREDITS_AFTER=$(echo "$BODY" | jget free_generation_credits_remaining)
check "credits increased by exactly 2" "$((CREDITS_BEFORE+2))" "$CREDITS_AFTER"

echo "--- sharing the SAME activity twice rejected ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/share" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$DRAFT_ID,\"price_type\":\"Free\"}")
CODE=$(echo "$RESP" | tail -1)
check "re-sharing same activity returns 409" "409" "$CODE"

echo "--- share Paid without a price rejected ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/share" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$DRAFT2_ID,\"price_type\":\"Paid\"}")
CODE=$(echo "$RESP" | tail -1)
check "Paid without price returns 422" "422" "$CODE"

echo "--- share Paid with a price (happy path) ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/activity/share" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"activity_id\":$DRAFT2_ID,\"price_type\":\"Paid\",\"price\":50}")
CODE=$(echo "$RESP" | tail -1)
check "Paid share with price returns 201" "201" "$CODE"

echo ""
echo "=== MY-ACTIVITIES (verify listing/rating shape) ==="
RESP=$(curl -s "$BASE/api/activity/my-activities" -H "Authorization: Bearer $TEACHER_TOKEN")
SHARED_COUNT=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo count(array_filter($d["activities"],fn($a)=>$a["shared_to_repository"]));')
check "my-activities shows 2 shared activities" "2" "$SHARED_COUNT"
RATING_NULL_OK=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); $shared=array_values(array_filter($d["activities"],fn($a)=>$a["shared_to_repository"])); echo ($shared[0]["rating"]["count"]===0)?"1":"0";')
check "shared activity with no ratings yet shows count=0 (not error)" "1" "$RATING_NULL_OK"

echo ""
echo "================================"
echo "RESULTS: $PASS passed, $FAIL failed"
echo "================================"
