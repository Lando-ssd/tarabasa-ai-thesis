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
jget() { php -r '$d=json_decode(file_get_contents("php://stdin"),true); $p=explode(".", $argv[1]); foreach($p as $k){ $d = $d[$k] ?? null; } echo is_bool($d) ? ($d?"true":"false") : $d;' "$1"; }

echo "############ SEED: create Admin, Teacher (Active), Parent via API ############"

# Admin (seeded directly in DB — no self-registration exists)
sqlite3 /home/claude/tarabasa-backend/database/tarabasa.sqlite <<SQL
INSERT INTO users (first_name, last_name, email, password, user_type, status)
VALUES ('System', 'Admin', 'admin@tarabasa.ai', '$(php -r "echo password_hash('adminpass123', PASSWORD_BCRYPT);")', 'Admin', 'Active');
SQL
RESP=$(curl -s -X POST "$BASE/api/auth/login" -H "Content-Type: application/json" -d '{"email":"admin@tarabasa.ai","password":"adminpass123"}')
ADMIN_TOKEN=$(echo "$RESP" | jget token)

RESP=$(curl -s -X POST "$BASE/api/auth/register-teacher" -H "Content-Type: application/json" \
  -d '{"first_name":"Jenny","last_name":"Reyes","email":"jenny@deped.gov.ph","password":"password123","school_name":"Rizal Elementary","employee_id":"EMP-100"}')
TEACHER_ID=$(echo "$RESP" | jget teacher.id)
TEACHER_TOKEN=$(echo "$RESP" | jget token)

RESP=$(curl -s -X POST "$BASE/api/auth/register-parent" -H "Content-Type: application/json" \
  -d '{"first_name":"Maria","last_name":"Cruz","email":"mariacruz@email.com","password":"password123"}')
PARENT_TOKEN=$(echo "$RESP" | jget token)

echo ""
echo "=== ADMIN MODULE ==="

echo "--- pending-teachers lists Jenny ---"
RESP=$(curl -s -w "\n%{http_code}" "$BASE/api/admin/pending-teachers" -H "Authorization: Bearer $ADMIN_TOKEN")
CODE=$(echo "$RESP" | tail -1); BODY=$(echo "$RESP" | head -n -1)
check "pending-teachers returns 200" "200" "$CODE"
FOUND=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); $f=array_filter($d["pending_teachers"],fn($t)=>$t["employee_id"]=="EMP-100"); echo count($f)>0?"1":"0";')
check "Jenny appears in pending list" "1" "$FOUND"

echo "--- non-admin cannot access admin routes ---"
RESP=$(curl -s -w "\n%{http_code}" "$BASE/api/admin/pending-teachers" -H "Authorization: Bearer $TEACHER_TOKEN")
CODE=$(echo "$RESP" | tail -1)
check "Teacher token on admin route returns 403" "403" "$CODE"

echo "--- Admin activates Jenny ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/admin/teacher-status" -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" -d "{\"teacher_id\":$TEACHER_ID,\"status\":\"Active\"}")
CODE=$(echo "$RESP" | tail -1)
check "activate teacher returns 200" "200" "$CODE"

echo "--- all-users excludes Admin itself ---"
RESP=$(curl -s "$BASE/api/admin/all-users" -H "Authorization: Bearer $ADMIN_TOKEN")
ADMIN_IN_LIST=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); $f=array_filter($d["users"],fn($u)=>$u["user_type"]=="Admin"); echo count($f);')
check "Admin never appears in all-users list" "0" "$ADMIN_IN_LIST"

echo "--- toggle-user-status flips a Parent Active->Inactive->Active ---"
PARENT_USER_ID=$(curl -s "$BASE/api/admin/all-users" -H "Authorization: Bearer $ADMIN_TOKEN" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); foreach($d["users"] as $u){ if($u["email"]=="mariacruz@email.com") echo $u["id"]; }')
RESP=$(curl -s -X POST "$BASE/api/admin/toggle-user-status" -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" -d "{\"user_id\":$PARENT_USER_ID}")
NEWSTATUS=$(echo "$RESP" | jget status)
check "toggle flips Parent to Inactive" "Inactive" "$NEWSTATUS"
RESP=$(curl -s -X POST "$BASE/api/auth/login" -H "Content-Type: application/json" -d '{"email":"mariacruz@email.com","password":"password123"}')
LOGIN_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/api/auth/login" -H "Content-Type: application/json" -d '{"email":"mariacruz@email.com","password":"password123"}')
check "Inactive parent blocked from logging in" "403" "$LOGIN_CODE"
curl -s -X POST "$BASE/api/admin/toggle-user-status" -H "Authorization: Bearer $ADMIN_TOKEN" -H "Content-Type: application/json" -d "{\"user_id\":$PARENT_USER_ID}" > /dev/null

echo "--- cannot toggle an Admin account ---"
ADMIN_USER_ID=$(sqlite3 /home/claude/tarabasa-backend/database/tarabasa.sqlite "SELECT id FROM users WHERE email='admin@tarabasa.ai';")
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/admin/toggle-user-status" -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" -d "{\"user_id\":$ADMIN_USER_ID}")
CODE=$(echo "$RESP" | tail -1)
check "toggling Admin's own account returns 403" "403" "$CODE"

echo ""
echo "=== PARENT -> LEARNER MODULE ==="

echo "--- create learner (happy path) ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/learner/create" -H "Authorization: Bearer $PARENT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"first_name":"Miguel","last_name":"Cruz","grade_level":"Grade 2","pin":"1234","learning_style":"Visual","placement_answers":[true,true,false]}')
CODE=$(echo "$RESP" | tail -1); BODY=$(echo "$RESP" | head -n -1)
check "create learner returns 201" "201" "$CODE"
MASTERY=$(echo "$BODY" | jget learner.mastery_level)
check "2 yes answers -> Developing" "Developing" "$MASTERY"
LEARNER_CODE=$(echo "$BODY" | jget learner.learner_code)
LEARNER_ID=$(echo "$BODY" | jget learner.id)
check "learner_code was generated" "1" "$([ -n "$LEARNER_CODE" ] && echo 1 || echo 0)"

echo "--- invalid PIN (not 4 digits) rejected ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/learner/create" -H "Authorization: Bearer $PARENT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"first_name":"X","grade_level":"Grade 1","pin":"12","placement_answers":[true,true,true]}')
CODE=$(echo "$RESP" | tail -1)
check "3-digit PIN rejected with 422" "422" "$CODE"

echo "--- 0 yes answers -> Beginning ---"
RESP=$(curl -s -X POST "$BASE/api/learner/create" -H "Authorization: Bearer $PARENT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"first_name":"Sofia","grade_level":"Grade 1","pin":"5678","placement_answers":[false,false,false]}')
MASTERY=$(echo "$RESP" | jget learner.mastery_level)
check "0 yes answers -> Beginning" "Beginning" "$MASTERY"

echo "--- 3 yes answers -> Proficient ---"
RESP=$(curl -s -X POST "$BASE/api/learner/create" -H "Authorization: Bearer $PARENT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"first_name":"Ana","grade_level":"Grade 3","pin":"9999","placement_answers":[true,true,true]}')
MASTERY=$(echo "$RESP" | jget learner.mastery_level)
check "3 yes answers -> Proficient" "Proficient" "$MASTERY"

echo "--- my-learners lists all 3 created ---"
RESP=$(curl -s "$BASE/api/learner/my-learners" -H "Authorization: Bearer $PARENT_TOKEN")
COUNT=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo count($d["learners"]);')
check "my-learners returns 3 learners" "3" "$COUNT"
ENROLLED=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["learners"][0]["enrolled_in_class"]?"true":"false";')
check "new learner shows enrolled_in_class=false (not yet in a class)" "false" "$ENROLLED"

echo "--- second parent links existing child via code ---"
RESP=$(curl -s -X POST "$BASE/api/auth/register-parent" -H "Content-Type: application/json" \
  -d '{"first_name":"Pedro","last_name":"Cruz","email":"pedro@email.com","password":"password123"}')
PARENT2_TOKEN=$(echo "$RESP" | jget token)
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/learner/link-parent" -H "Authorization: Bearer $PARENT2_TOKEN" \
  -H "Content-Type: application/json" -d "{\"learner_code\":\"$LEARNER_CODE\",\"relationship\":\"Father\"}")
CODE=$(echo "$RESP" | tail -1)
check "link-parent returns 201" "201" "$CODE"

echo "--- linking same learner twice rejected ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/learner/link-parent" -H "Authorization: Bearer $PARENT2_TOKEN" \
  -H "Content-Type: application/json" -d "{\"learner_code\":\"$LEARNER_CODE\",\"relationship\":\"Father\"}")
CODE=$(echo "$RESP" | tail -1)
check "duplicate link returns 409" "409" "$CODE"

echo "--- linking nonexistent code rejected ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/learner/link-parent" -H "Authorization: Bearer $PARENT2_TOKEN" \
  -H "Content-Type: application/json" -d '{"learner_code":"TB-00000","relationship":"Father"}')
CODE=$(echo "$RESP" | tail -1)
check "linking nonexistent code returns 404" "404" "$CODE"

echo ""
echo "=== TEACHER -> CLASS MODULE ==="

echo "--- Pending teacher blocked from creating a class ---"
RESP=$(curl -s -X POST "$BASE/api/auth/register-teacher" -H "Content-Type: application/json" \
  -d '{"first_name":"New","last_name":"Teacher","email":"newteacher@deped.gov.ph","password":"password123","school_name":"Test School","employee_id":"EMP-999"}')
PENDING_TEACHER_TOKEN=$(echo "$RESP" | jget token)
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/class/create" -H "Authorization: Bearer $PENDING_TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d '{"name":"CCS","grade_level":"Grade 1","section":"CCS","school_year":"2026-2027"}')
CODE=$(echo "$RESP" | tail -1)
check "Pending teacher creating a class returns 403" "403" "$CODE"

echo "--- Active teacher creates a class (current year) ---"
CURRENT_SY=$(curl -s "$BASE/api/class/my-classes" -H "Authorization: Bearer $TEACHER_TOKEN" | jget current_school_year)
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/class/create" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"name\":\"CCS\",\"grade_level\":\"Grade 2\",\"section\":\"CCS\",\"school_year\":\"$CURRENT_SY\"}")
CODE=$(echo "$RESP" | tail -1); BODY=$(echo "$RESP" | head -n -1)
check "class create returns 201" "201" "$CODE"
CLASS_ID=$(echo "$BODY" | jget class.id)

echo "--- class creation missing school_year rejected ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/class/create" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d '{"name":"NoYear","grade_level":"Grade 1","section":"A"}')
CODE=$(echo "$RESP" | tail -1)
check "class create without school_year returns 422" "422" "$CODE"

echo "--- create a PAST year class directly in DB (simulating an old record) ---"
sqlite3 /home/claude/tarabasa-backend/database/tarabasa.sqlite \
  "INSERT INTO classes (teacher_id, name, grade_level, section, school_year) VALUES ($TEACHER_ID, 'CCS', 'Grade 1', 'CCS', '2025-2026');"
PAST_CLASS_ID=$(sqlite3 /home/claude/tarabasa-backend/database/tarabasa.sqlite "SELECT id FROM classes WHERE school_year='2025-2026' AND teacher_id=$TEACHER_ID;")

echo "--- my-classes default (no filter) shows only current year ---"
RESP=$(curl -s "$BASE/api/class/my-classes" -H "Authorization: Bearer $TEACHER_TOKEN")
COUNT=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo count($d["classes"]);')
check "default my-classes shows only 1 (current year) class" "1" "$COUNT"

echo "--- my-classes with explicit past-year filter shows it, marked read_only ---"
RESP=$(curl -s "$BASE/api/class/my-classes?school_year=2025-2026" -H "Authorization: Bearer $TEACHER_TOKEN")
READONLY=$(echo "$RESP" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["classes"][0]["read_only"]?"true":"false";')
check "past-year class flagged read_only=true" "true" "$READONLY"

echo "--- join learner into CURRENT year class (happy path) ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/learner/join-class" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"learner_code\":\"$LEARNER_CODE\",\"class_id\":$CLASS_ID}")
CODE=$(echo "$RESP" | tail -1)
check "join-class returns 200" "200" "$CODE"

echo "--- joining an already-in-a-class learner rejected ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/learner/join-class" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"learner_code\":\"$LEARNER_CODE\",\"class_id\":$CLASS_ID}")
CODE=$(echo "$RESP" | tail -1)
check "re-joining already-classed learner returns 409" "409" "$CODE"

echo "--- joining a learner into a PAST-year class rejected ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/learner/join-class" -H "Authorization: Bearer $TEACHER_TOKEN" \
  -H "Content-Type: application/json" -d "{\"learner_code\":\"TB-00001\",\"class_id\":$PAST_CLASS_ID}")
CODE=$(echo "$RESP" | tail -1)
check "joining a past-year class returns 403" "403" "$CODE"

echo "--- roster shows the joined learner with trajectory + promotion history ---"
RESP=$(curl -s -w "\n%{http_code}" "$BASE/api/class/roster?class_id=$CLASS_ID" -H "Authorization: Bearer $TEACHER_TOKEN")
CODE=$(echo "$RESP" | tail -1); BODY=$(echo "$RESP" | head -n -1)
check "roster returns 200" "200" "$CODE"
COUNT=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo count($d["roster"]);')
check "roster shows 1 learner" "1" "$COUNT"
PROMO_HEADLINE=$(echo "$BODY" | jget roster.0.promotion_history.headline)
check "new learner shows 'no prior grade history'" "New to the system — no prior grade history." "$PROMO_HEADLINE"
TRAJECTORY=$(echo "$BODY" | jget roster.0.proficiency_trajectory.text)
check "no sessions yet -> shows starting level text" "1" "$(echo "$TRAJECTORY" | grep -q "No reading sessions recorded yet" && echo 1 || echo 0)"

echo "--- another teacher cannot view this roster ---"
RESP=$(curl -s -X POST "$BASE/api/auth/register-teacher" -H "Content-Type: application/json" \
  -d '{"first_name":"Other","last_name":"Teacher","email":"other@deped.gov.ph","password":"password123","school_name":"Other School","employee_id":"EMP-500"}')
OTHER_TEACHER_TOKEN=$(echo "$RESP" | jget token)
RESP=$(curl -s -w "\n%{http_code}" "$BASE/api/class/roster?class_id=$CLASS_ID" -H "Authorization: Bearer $OTHER_TEACHER_TOKEN")
CODE=$(echo "$RESP" | tail -1)
check "other teacher accessing this roster returns 404 (ownership enforced)" "404" "$CODE"

echo ""
echo "--- promotion history WITH a claimed promotion (simulate via direct DB insert) ---"
# Create the source class (a past-year class this learner was released from)
PROMO_FROM_CLASS_ID=$(sqlite3 /home/claude/tarabasa-backend/database/tarabasa.sqlite \
  "INSERT INTO classes (teacher_id, name, grade_level, section, school_year) VALUES ($TEACHER_ID, 'CCS', 'Grade 2', 'CCS', '2025-2026'); SELECT last_insert_rowid();")
sqlite3 /home/claude/tarabasa-backend/database/tarabasa.sqlite <<SQL
INSERT INTO promotion_records (learner_id, released_by_teacher_id, claimed_by_teacher_id, next_grade, status, released_at, claimed_at, released_from_class_id, claimed_into_class_id)
VALUES ($LEARNER_ID, $TEACHER_ID, $TEACHER_ID, 'Grade 3', 'Claimed', '2026-03-01 00:00:00', '2026-06-15 00:00:00', $PROMO_FROM_CLASS_ID, $CLASS_ID);
SQL
RESP=$(curl -s "$BASE/api/class/roster?class_id=$CLASS_ID" -H "Authorization: Bearer $TEACHER_TOKEN")
PROMO_HEADLINE=$(echo "$RESP" | jget roster.0.promotion_history.headline)
check "claimed promotion shows correct headline" "1" "$(echo "$PROMO_HEADLINE" | grep -q "Promoted from a previous class" && echo 1 || echo 0)"
FROM_GRADE=$(echo "$RESP" | jget roster.0.promotion_history.chain.0.from_grade)
check "chain infers correct from_grade (Grade 3 promo -> from Grade 2)" "Grade 2" "$FROM_GRADE"
DISPLAY_LINE=$(echo "$RESP" | jget roster.0.promotion_history.chain.0.display)
check "rich display line includes source class name+year+teacher" "1" "$(echo "$DISPLAY_LINE" | grep -q "SY 2025-2026, Teacher: Jenny Reyes" && echo 1 || echo 0)"
check "rich display line includes destination class name+year+teacher" "1" "$(echo "$DISPLAY_LINE" | grep -q "SY $CURRENT_SY, Teacher: Jenny Reyes" && echo 1 || echo 0)"

echo ""
echo "================================"
echo "RESULTS: $PASS passed, $FAIL failed"
echo "================================"
