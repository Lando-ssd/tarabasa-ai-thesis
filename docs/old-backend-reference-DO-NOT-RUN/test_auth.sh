#!/bin/bash
BASE="http://127.0.0.1:8000"
PASS=0
FAIL=0

check() {
  local desc="$1"
  local expected="$2"
  local actual="$3"
  if [ "$expected" == "$actual" ]; then
    echo "  PASS: $desc (got $actual)"
    PASS=$((PASS+1))
  else
    echo "  FAIL: $desc (expected $expected, got $actual)"
    FAIL=$((FAIL+1))
  fi
}

echo "=== 1. Register Teacher (happy path) ==="
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/auth/register-teacher" \
  -H "Content-Type: application/json" \
  -d '{"first_name":"Juana","last_name":"Reyes","email":"juana@deped.gov.ph","password":"password123","school_name":"Rizal Elementary","employee_id":"EMP-001"}')
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | head -n -1)
check "register-teacher returns 201" "201" "$CODE"
TEACHER_TOKEN=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["token"] ?? "";')
TEACHER_STATUS=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["teacher"]["status"] ?? "";')
TEACHER_CREDITS=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["teacher"]["free_generation_credits_remaining"] ?? "";')
check "new teacher status is Pending" "Pending" "$TEACHER_STATUS"
check "new teacher has 2 free credits" "2" "$TEACHER_CREDITS"
check "register-teacher issues a usable token" "1" "$([ -n "$TEACHER_TOKEN" ] && echo 1 || echo 0)"

echo ""
echo "=== 2. Duplicate email rejected ==="
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/auth/register-teacher" \
  -H "Content-Type: application/json" \
  -d '{"first_name":"Juana","last_name":"Reyes","email":"juana@deped.gov.ph","password":"password123","school_name":"Rizal Elementary","employee_id":"EMP-002"}')
CODE=$(echo "$RESP" | tail -1)
check "duplicate teacher email returns 409" "409" "$CODE"

echo ""
echo "=== 3. Password too short rejected ==="
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/auth/register-teacher" \
  -H "Content-Type: application/json" \
  -d '{"first_name":"X","last_name":"Y","email":"short@test.com","password":"abc","school_name":"S","employee_id":"E"}')
CODE=$(echo "$RESP" | tail -1)
check "password <8 chars returns 422" "422" "$CODE"

echo ""
echo "=== 4. Pending Teacher CAN log in (Step 2 rule) ==="
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"juana@deped.gov.ph","password":"password123"}')
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | head -n -1)
check "Pending teacher login returns 200" "200" "$CODE"
LOGIN_STATUS=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["teacher"]["status"] ?? "";')
check "login response shows Pending status" "Pending" "$LOGIN_STATUS"

echo ""
echo "=== 5. Wrong password rejected (generic message) ==="
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"juana@deped.gov.ph","password":"wrongpassword"}')
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | head -n -1)
ERR_MSG=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["error"] ?? "";')
check "wrong password returns 401" "401" "$CODE"
check "wrong password message is generic (doesn't reveal which field)" "Invalid email or password." "$ERR_MSG"

echo ""
echo "=== 6. Register Parent (happy path, Active immediately) ==="
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/auth/register-parent" \
  -H "Content-Type: application/json" \
  -d '{"first_name":"Maria","last_name":"Cruz","email":"maria@email.com","password":"password123"}')
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | head -n -1)
check "register-parent returns 201" "201" "$CODE"
PARENT_TOKEN=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["token"] ?? "";')
check "register-parent issues a usable token" "1" "$([ -n "$PARENT_TOKEN" ] && echo 1 || echo 0)"

echo ""
echo "=== 7. /api/auth/me with valid token ==="
RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE/api/auth/me" -H "Authorization: Bearer $PARENT_TOKEN")
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | head -n -1)
ME_TYPE=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["user"]["user_type"] ?? "";')
check "/me returns 200" "200" "$CODE"
check "/me identifies correct user_type" "Parent" "$ME_TYPE"

echo ""
echo "=== 8. /api/auth/me with garbage token ==="
RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE/api/auth/me" -H "Authorization: Bearer garbage_token_123")
CODE=$(echo "$RESP" | tail -1)
check "/me with invalid token returns 401" "401" "$CODE"

echo ""
echo "=== 9. Logout invalidates token ==="
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/auth/logout" -H "Authorization: Bearer $PARENT_TOKEN")
CODE=$(echo "$RESP" | tail -1)
check "logout returns 200" "200" "$CODE"
RESP2=$(curl -s -w "\n%{http_code}" -X GET "$BASE/api/auth/me" -H "Authorization: Bearer $PARENT_TOKEN")
CODE2=$(echo "$RESP2" | tail -1)
check "token is unusable after logout" "401" "$CODE2"

echo ""
echo "=== 10. Rejected teacher blocked at login (manual DB edit to simulate Admin rejecting) ==="
sqlite3 /home/claude/tarabasa-backend/database/tarabasa.sqlite "UPDATE teachers SET status='Rejected' WHERE employee_id='EMP-001';"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"juana@deped.gov.ph","password":"password123"}')
CODE=$(echo "$RESP" | tail -1)
check "Rejected teacher login returns 403" "403" "$CODE"
sqlite3 /home/claude/tarabasa-backend/database/tarabasa.sqlite "UPDATE teachers SET status='Active' WHERE employee_id='EMP-001';"

echo ""
echo "=== 11. Inactive (deactivated) user blocked at login ==="
sqlite3 /home/claude/tarabasa-backend/database/tarabasa.sqlite "UPDATE users SET status='Inactive' WHERE email='maria@email.com';"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"maria@email.com","password":"password123"}')
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | head -n -1)
ERR_MSG=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["error"] ?? "";')
check "Inactive user login returns 403" "403" "$CODE"
check "Inactive user gets specific deactivation message" "1" "$(echo "$ERR_MSG" | grep -qi deactivat && echo 1 || echo 0)"
sqlite3 /home/claude/tarabasa-backend/database/tarabasa.sqlite "UPDATE users SET status='Active' WHERE email='maria@email.com';"

echo ""
echo "=== 12. Learner PIN login (setup a learner directly in DB) ==="
sqlite3 /home/claude/tarabasa-backend/database/tarabasa.sqlite <<'SQL'
INSERT INTO learners (learner_code, first_name, last_name, grade_level, pin, avatar_id, mastery_level, points, streak, status)
VALUES ('TB-99999', 'Miguel', 'Santos', 'Grade 2', '1234', 'lion', 'Developing', 100, 3, 'Active');
SQL
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/learner/login-pin" \
  -H "Content-Type: application/json" \
  -d '{"learner_code":"TB-99999","pin":"1234"}')
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | head -n -1)
LEARNER_TOKEN=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["token"] ?? "";')
HAS_DIAG=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["has_completed_diagnostic"] ? "true" : "false";')
check "learner PIN login returns 200" "200" "$CODE"
check "learner login issues a usable token" "1" "$([ -n "$LEARNER_TOKEN" ] && echo 1 || echo 0)"
check "new learner has_completed_diagnostic is false (first login)" "false" "$HAS_DIAG"

echo ""
echo "=== 13. Learner wrong PIN rejected (generic message, doesn't confirm code validity) ==="
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/learner/login-pin" \
  -H "Content-Type: application/json" \
  -d '{"learner_code":"TB-99999","pin":"0000"}')
CODE=$(echo "$RESP" | tail -1)
check "wrong learner PIN returns 401" "401" "$CODE"

echo ""
echo "=== 14. Learner PIN lockout after 5 failed attempts ==="
sqlite3 /home/claude/tarabasa-backend/database/tarabasa.sqlite "DELETE FROM login_attempts WHERE identifier='TB-99999';"
for i in 1 2 3 4 5; do
  curl -s -o /dev/null -X POST "$BASE/api/learner/login-pin" \
    -H "Content-Type: application/json" \
    -d '{"learner_code":"TB-99999","pin":"0000"}'
done
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/learner/login-pin" \
  -H "Content-Type: application/json" \
  -d '{"learner_code":"TB-99999","pin":"1234"}')
CODE=$(echo "$RESP" | tail -1)
check "5th+ attempt locks out even with CORRECT pin" "429" "$CODE"
sqlite3 /home/claude/tarabasa-backend/database/tarabasa.sqlite "DELETE FROM login_attempts WHERE identifier='TB-99999';"

echo ""
echo "=== 15. Nonexistent learner code gives same generic error (no enumeration) ==="
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/learner/login-pin" \
  -H "Content-Type: application/json" \
  -d '{"learner_code":"TB-00000","pin":"1234"}')
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | head -n -1)
ERR_MSG=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["error"] ?? "";')
check "nonexistent learner code returns 401 (same as wrong PIN)" "401" "$CODE"
check "message is generic 'Incorrect PIN', not 'code not found'" "Incorrect PIN." "$ERR_MSG"

echo ""
echo "=== 16. /api/learner/me + /api/learner/logout ==="
RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE/api/learner/me" -H "Authorization: Bearer $LEARNER_TOKEN")
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | head -n -1)
LNAME=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["learner"]["first_name"] ?? "";')
check "learner/me returns 200" "200" "$CODE"
check "learner/me returns correct learner" "Miguel" "$LNAME"

RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/learner/logout" -H "Authorization: Bearer $LEARNER_TOKEN")
CODE=$(echo "$RESP" | tail -1)
check "learner logout returns 200" "200" "$CODE"

RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE/api/learner/me" -H "Authorization: Bearer $LEARNER_TOKEN")
CODE=$(echo "$RESP" | tail -1)
check "learner token unusable after logout" "401" "$CODE"

echo ""
echo "=== 17. Admin can log in (seeded manually), Rejected/Inactive rules don't apply to Admin ==="
sqlite3 /home/claude/tarabasa-backend/database/tarabasa.sqlite <<SQL
INSERT INTO users (first_name, last_name, email, password, user_type, status)
VALUES ('System', 'Admin', 'admin@tarabasa.ai', '$(php -r "echo password_hash('adminpass123', PASSWORD_BCRYPT);")', 'Admin', 'Active');
SQL
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@tarabasa.ai","password":"adminpass123"}')
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | head -n -1)
ADMIN_TYPE=$(echo "$BODY" | php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["user"]["user_type"] ?? "";')
check "Admin login returns 200" "200" "$CODE"
check "Admin user_type correct" "Admin" "$ADMIN_TYPE"

echo ""
echo "================================"
echo "RESULTS: $PASS passed, $FAIL failed"
echo "================================"
