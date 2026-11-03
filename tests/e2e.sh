#!/usr/bin/env bash
# End-to-end test against mock Google + mock Stripe using the PHP built-in server and SQLite.
set -u
cd "$(dirname "$0")/.."
T=/tmp/bookings-e2e; rm -rf $T; mkdir -p $T
rm -f /tmp/mock_gcal.json
sqlite3 $T/db.sqlite < tests/schema.sqlite.sql 2>/dev/null || php -r '$p=new PDO("sqlite:'$T'/db.sqlite"); $p->exec(file_get_contents("tests/schema.sqlite.sql"));'

cat > $T/.env <<EOF
APP_NAME="BOD Bookings Test"
APP_URL=http://127.0.0.1:8091
APP_ENV=local
APP_KEY=$(php -r 'echo bin2hex(random_bytes(32));')
DB_DSN="sqlite:$T/db.sqlite"
GOOGLE_CLIENT_ID=cid.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=csec
STRIPE_SECRET_KEY=sk_test_x
STRIPE_WEBHOOK_SECRET=whsec_testsecret
STRIPE_PRICE_PRO_MONTHLY=price_m
STRIPE_PRICE_PRO_YEARLY=price_y
MAIL_DRIVER=log
MAIL_FROM=hello@example.com
FREE_EVENT_TYPES=1
EOF
# app reads .env from BASE_PATH; symlink a copy of the tree so we don't touch the real .env
rm -rf $T/app; mkdir -p $T/app; cp -r public src views $T/app/; cp $T/.env $T/app/.env

php -S 127.0.0.1:8092 tests/mock_google.php > $T/google.log 2>&1 &
php -S 127.0.0.1:8093 tests/mock_stripe.php > $T/stripe.log 2>&1 &
GCAL_TOKEN_URL=http://127.0.0.1:8092/token GCAL_API_URL=http://127.0.0.1:8092 GCAL_USERINFO_URL=http://127.0.0.1:8092/userinfo \
  STRIPE_API_URL=http://127.0.0.1:8093 php -S 127.0.0.1:8091 -t $T/app/public > $T/app.log 2>&1 &
sleep 1
B=http://127.0.0.1:8091; J="-b $T/cj -c $T/cj"; FAIL=0
check() { if [ "$2" = "$3" ]; then echo "PASS  $1"; else echo "FAIL  $1  (got: $2 | want: $3)"; FAIL=$((FAIL+1)); fi; }
csrf() { curl -s $J "$B$1" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }

echo "== public pages"
check "home 200"    "$(curl -s -o /dev/null -w '%{http_code}' $B/)" 200
check "pricing 200" "$(curl -s -o /dev/null -w '%{http_code}' $B/pricing)" 200
check "privacy mentions Limited Use" "$(curl -s $B/privacy | grep -c 'Limited Use')" 1
check "unknown user 404" "$(curl -s -o /dev/null -w '%{http_code}' $B/nobody)" 404
check "/app redirects to login" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' $B/app)" "302 $B/login"

echo "== sign in with Google (mock)"
LOC=$(curl -s $J -o /dev/null -w '%{redirect_url}' $B/auth/google)
STATE=$(echo "$LOC" | sed 's/.*state=\([^&]*\).*/\1/')
check "auth redirect goes to Google w/ calendar scope" "$(echo "$LOC" | grep -c 'accounts.google.com.*calendar.events')" 1
check "callback bad state rejected" "$(curl -s $J -o /dev/null -w '%{redirect_url}' "$B/auth/callback?code=code-alice&state=wrong")" "$B/login"
# state was consumed? No — mismatch doesn't unset it. Use the real one.
R=$(curl -s $J -o /dev/null -w '%{http_code} %{redirect_url}' "$B/auth/callback?code=code-alice&state=$STATE")
check "callback creates user, redirects to event types" "$R" "302 $B/app/event-types"
check "dashboard loads" "$(curl -s $J $B/app | grep -c 'Alice Tester\|/alice')" 2
check "token stored encrypted" "$(php -r '$p=new PDO("sqlite:'$T'/db.sqlite"); $t=$p->query("select refresh_token_enc from google_tokens")->fetchColumn(); echo (strpos($t,"rt-alice")===false && strlen($t)>40) ? "yes":"no";')" yes

echo "== event types + plan limit"
C=$(csrf /app/event-types)
curl -s $J -o /dev/null -d "_csrf=$C&name=30-minute consult&duration=30&meet=1&active=1" $B/app/event-types
check "first event type created" "$(curl -s $J $B/app/event-types | grep -o 'href="/alice/30-minute-consult"' | wc -l)" 1
curl -s $J -o /dev/null -d "_csrf=$C&name=Second&duration=15&meet=1&active=1" $B/app/event-types
check "second blocked on free plan" "$(curl -s $J $B/app/event-types | grep -c 'href="/alice/second"')" 0
check "profile lists type" "$(curl -s $B/alice | grep -c '30-minute consult')" 1

echo "== availability save"
C=$(csrf /app/availability)
curl -s $J -o /dev/null -d "_csrf=$C&avail_mon=&avail_tue=09:00-17:00&avail_wed=09:00-17:00&avail_thu=09:00-12:00,13:00-17:00&avail_fri=&avail_sat=&avail_sun=&timezone=America/Los_Angeles&min_notice_hours=1&max_days_ahead=14&buffer_minutes=15&slot_step_minutes=30" $B/app/availability
check "availability saved (thu has 2 windows)" "$(curl -s $J $B/app/availability | grep -o 'name="avail_thu" value="[^"]*"')" 'name="avail_thu" value="09:00-12:00, 13:00-17:00"'
curl -s $J -o /dev/null -d "_csrf=$C&avail_mon=9-17&avail_tue=&avail_wed=&avail_thu=&avail_fri=&avail_sat=&avail_sun=&timezone=America/Los_Angeles&min_notice_hours=1&max_days_ahead=14&buffer_minutes=15&slot_step_minutes=30" $B/app/availability
check "bad window rejected, previous hours kept" "$(curl -s $J $B/app/availability | grep -c "Can&#039;t read")$(curl -s $J $B/app/availability | grep -o 'name="avail_tue" value="[^"]*"')" '1name="avail_tue" value="09:00-17:00"' 

echo "== public booking API"
S=$(curl -s "$B/api/slots?u=alice&type=30-minute-consult&from=2026-09-28&to=2026-10-12")
check "slots returned, Wed 10:00 PT (17:00Z) excluded (busy), Wed 11:00 (18:00Z) excluded (buffer)" "$(echo "$S" | python3 -c 'import json,sys; d=json.load(sys.stdin); s=d["slots"]; print(len(s)>0, "2026-09-30T17:00:00+00:00" in s, "2026-09-30T18:00:00+00:00" in s, "2026-09-30T18:30:00+00:00" in s)')" "True False False True"
check "monday (no hours) has no slots" "$(echo "$S" | python3 -c 'import json,sys; print(sum(1 for x in json.load(sys.stdin)["slots"] if x.startswith("2026-10-05")))')" 0
R=$(curl -s -X POST $B/api/book -H 'Content-Type: application/json' -d '{"u":"alice","type":"30-minute-consult","start":"2026-09-30T17:00:00Z","name":"Guest","email":"g@example.com"}')
check "busy slot -> 409" "$(echo "$R" | grep -c 'no longer available')" 1
R=$(curl -s -X POST $B/api/book -H 'Content-Type: application/json' -d '{"u":"alice","type":"30-minute-consult","start":"2026-10-01T16:00:00Z","name":"Guest One","email":"g1@example.com","notes":"hi","tz":"America/New_York"}')
MANAGE=$(echo "$R" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["booking"]["manage_url"])')
TOKEN=${MANAGE##*/m/}
check "booking created with meet link" "$(echo "$R" | python3 -c 'import json,sys; d=json.load(sys.stdin); b=d["booking"]; print(d["ok"], b["meet_link"].startswith("https://meet.google.com/"), b["status"])')" "True True confirmed"
R=$(curl -s -X POST $B/api/book -H 'Content-Type: application/json' -d '{"u":"alice","type":"30-minute-consult","start":"2026-10-01T16:00:00Z","name":"X","email":"x@example.com"}')
check "double-book same slot -> 409" "$(echo "$R" | grep -c 'no longer available')" 1
check "honeypot rejected" "$(curl -s -o /dev/null -w '%{http_code}' -X POST $B/api/book -H 'Content-Type: application/json' -d '{"u":"alice","type":"30-minute-consult","start":"2026-10-01T17:00:00Z","name":"X","email":"x@example.com","website":"spam"}')" 400
check "dashboard shows booking" "$(curl -s $J $B/app | grep -c 'Guest One')" 1
check "manage page 200" "$(curl -s -o /dev/null -w '%{http_code}' $MANAGE)" 200
check "api/booking by token" "$(curl -s "$B/api/booking?t=$TOKEN" | python3 -c 'import json,sys; print(json.load(sys.stdin)["booking"]["name"])')" "Guest One"
check "bad token 404" "$(curl -s -o /dev/null -w '%{http_code}' "$B/api/booking?t=nope")" 404
check "slots via token for reschedule" "$(curl -s "$B/api/slots?t=$TOKEN&from=2026-09-28&to=2026-10-12" | python3 -c 'import json,sys; print(json.load(sys.stdin)["duration"])')" 30
R=$(curl -s -X POST $B/api/reschedule -H 'Content-Type: application/json' -d "{\"t\":\"$TOKEN\",\"start\":\"2026-10-01T18:00:00Z\"}")
check "reschedule ok" "$(echo "$R" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["ok"], d["booking"]["start"])')" "True 2026-10-01T18:00:00+00:00"
R=$(curl -s -X POST $B/api/reschedule -H 'Content-Type: application/json' -d "{\"t\":\"$TOKEN\",\"start\":\"2026-10-01T16:00:00Z\"}")
check "reschedule back to original slot ok" "$(echo "$R" | python3 -c 'import json,sys; print(json.load(sys.stdin)["ok"])')" True
check "cancel ok" "$(curl -s -X POST $B/api/cancel -H 'Content-Type: application/json' -d "{\"t\":\"$TOKEN\"}")" '{"ok":true}'
check "cancelled booking -> 410" "$(curl -s -o /dev/null -w '%{http_code}' "$B/api/booking?t=$TOKEN")" 410
check "google saw delete w/ sendUpdates" "$(python3 -c 'import json; print(sum(1 for l in json.load(open("/tmp/mock_gcal.json"))["log"] if l.startswith("DELETE /calendars/primary/events/evt0") and "sendUpdates" in l))')" 1
check "google insert had conferenceDataVersion + attendee" "$(python3 -c 'import json; d=json.load(open("/tmp/mock_gcal.json")); print(sum(1 for l in d["log"] if l.startswith("POST /calendars/primary/events ") and "conferenceDataVersion" in l), d["events"]["evt0"]["attendees"][0]["email"])')" "1 g1@example.com"

echo "== host cancel from dashboard"
curl -s -X POST $B/api/book -o /dev/null -H 'Content-Type: application/json' -d '{"u":"alice","type":"30-minute-consult","start":"2026-10-01T20:00:00Z","name":"Guest Two","email":"g2@example.com"}'
BID=$(python3 -c "import sqlite3; print(sqlite3.connect('$T/db.sqlite').execute(\"select id from bookings where guest_name='Guest Two'\").fetchone()[0])")
C=$(csrf /app)
curl -s $J -o /dev/null -d "_csrf=$C&id=$BID" $B/app/bookings/cancel
check "host cancel marks cancelled" "$(php -r '$p=new PDO("sqlite:'$T'/db.sqlite"); echo $p->query("select status from bookings where id='$BID'")->fetchColumn();')" cancelled
check "csrf rejected" "$(curl -s $J -o /dev/null -w '%{http_code}' -d "_csrf=bad&id=$BID" $B/app/bookings/cancel)" 400

echo "== billing"
C=$(csrf /app/billing)
check "checkout redirects to stripe" "$(curl -s $J -o /dev/null -w '%{redirect_url}' -d "_csrf=$C&interval=monthly" $B/app/billing/checkout)" "https://checkout.stripe.test/cs_test_1"
# webhook: checkout.session.completed with valid signature
PAYLOAD='{"id":"evt_1","type":"checkout.session.completed","data":{"object":{"id":"cs_test_1","client_reference_id":"1","customer":"cus_1","subscription":"sub_1"}}}'
TS=$(date +%s); SIG=$(printf '%s.%s' "$TS" "$PAYLOAD" | openssl dgst -sha256 -hmac whsec_testsecret | sed 's/.*= //')
check "webhook bad signature -> 400" "$(curl -s -o /dev/null -w '%{http_code}' -X POST $B/stripe/webhook -H "Stripe-Signature: t=$TS,v1=deadbeef" -d "$PAYLOAD")" 400
check "webhook ok" "$(curl -s -X POST $B/stripe/webhook -H "Stripe-Signature: t=$TS,v1=$SIG" -d "$PAYLOAD")" ok
check "webhook duplicate ignored" "$(curl -s -X POST $B/stripe/webhook -H "Stripe-Signature: t=$TS,v1=$SIG" -d "$PAYLOAD")" duplicate
check "user is now pro/active" "$(php -r '$p=new PDO("sqlite:'$T'/db.sqlite"); echo implode(" ", $p->query("select plan, plan_status, stripe_customer_id from users where id=1")->fetch(PDO::FETCH_NUM));')" "pro active cus_1"
C=$(csrf /app/event-types)
curl -s $J -o /dev/null -d "_csrf=$C&name=Second&duration=15&meet=1&active=1" $B/app/event-types
check "pro can add second event type" "$(curl -s $J $B/app/event-types | grep -c 'href="/alice/second"')" 1
check "portal redirect" "$(curl -s $J -o /dev/null -w '%{redirect_url}' -d "_csrf=$(csrf /app/billing)" $B/app/billing/portal)" "https://billing.stripe.test/cus_1"
PAYLOAD='{"id":"evt_2","type":"customer.subscription.deleted","data":{"object":{"id":"sub_1","status":"canceled","customer":"cus_1","metadata":{"user_id":"1"}}}}'
TS=$(date +%s); SIG=$(printf '%s.%s' "$TS" "$PAYLOAD" | openssl dgst -sha256 -hmac whsec_testsecret | sed 's/.*= //')
curl -s -o /dev/null -X POST $B/stripe/webhook -H "Stripe-Signature: t=$TS,v1=$SIG" -d "$PAYLOAD"
check "subscription deleted -> free/canceled" "$(php -r '$p=new PDO("sqlite:'$T'/db.sqlite"); echo implode(" ", $p->query("select plan, plan_status from users where id=1")->fetch(PDO::FETCH_NUM));')" "free canceled"

echo "== settings + disconnect + second user"
C=$(csrf /app/settings)
check "settings lists calendars" "$(curl -s $J $B/app/settings | grep -c 'team@group.calendar.google.com')" 2
curl -s $J -o /dev/null -d "_csrf=$C&name=Alice T&username=alice-t&welcome_text=Hi&calendar_id=primary&busy[]=primary&busy[]=team@group.calendar.google.com" $B/app/settings
check "username changed, profile moved" "$(curl -s -o /dev/null -w '%{http_code}' $B/alice-t)$(curl -s -o /dev/null -w ' %{http_code}' $B/alice)" "200 404"
check "reserved username rejected" "$(curl -s $J -d "_csrf=$C&name=A&username=app&calendar_id=primary" $B/app/settings -o /dev/null; curl -s $J $B/app/settings | grep -c 'reserved')" 1
curl -s $J -o /dev/null -d "_csrf=$C" $B/app/calendar/disconnect
check "after disconnect public booking 503" "$(curl -s -o /dev/null -w '%{http_code}' "$B/api/slots?u=alice-t&type=30-minute-consult")" 503
# second user gets distinct username
rm -f $T/cj2; J2="-b $T/cj2 -c $T/cj2"
LOC=$(curl -s $J2 -o /dev/null -w '%{redirect_url}' $B/auth/google); STATE=$(echo "$LOC" | sed 's/.*state=\([^&]*\).*/\1/')
curl -s $J2 -o /dev/null "$B/auth/callback?code=code-bob&state=$STATE"
check "second user created (bob profile live)" "$(curl -s -o /dev/null -w '%{http_code}' $B/bob)" 200
check "bob cannot cancel alice's booking" "$(curl -s $J2 -o /dev/null -w '%{redirect_url}' -d "_csrf=$(csrf_bob=$(curl -s $J2 $B/app | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'); echo $csrf_bob)&id=1" $B/app/bookings/cancel; php -r '$p=new PDO("sqlite:'$T'/db.sqlite"); echo $p->query("select status from bookings where id=1")->fetchColumn();')" "$B/appcancelled"
check "account delete cascades" "$(curl -s $J2 -o /dev/null -d "_csrf=$(csrf_bob=$(curl -s $J2 $B/app/settings | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'); echo $csrf_bob)&confirm=DELETE" $B/app/account/delete; php -r '$p=new PDO("sqlite:'$T'/db.sqlite"); echo $p->query("select count(*) from users")->fetchColumn(), " ", $p->query("select count(*) from google_tokens")->fetchColumn();')" "1 1"

echo "== server errors in log:"
grep -i "PHP \(Fatal\|Warning\|Notice\|Parse\)" $T/app.log | head -5
echo; if [ $FAIL -eq 0 ]; then echo "ALL PASS"; else echo "$FAIL FAILED"; fi
for p in $(pgrep -f "php -S 127.0.0.1:809"); do kill $p 2>/dev/null; done
exit $FAIL
