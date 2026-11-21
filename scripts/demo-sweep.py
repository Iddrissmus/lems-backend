"""Demo sweep: every demo role signs in and opens the screens it uses, so you know the API
(and the tunnel in front of it) works before the demo. Read only: it changes nothing except
issuing sign-in tokens (and their audit entries).

Usage (Python 3, no extra packages):
    PowerShell:  $env:DEMO_PASSWORD = "<your demo password>"
                 python scripts/demo-sweep.py https://<tunnel-address>/api/v1
    Bash:        DEMO_PASSWORD=<your demo password> python scripts/demo-sweep.py https://<tunnel-address>/api/v1
Use http://localhost:8000/api/v1 to test without the tunnel. The password is read from the
environment and never printed. It expects the seeded demo data (docs/demo-data.md)."""
import json, os, sys, time, urllib.request, urllib.error

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8000/api/v1').rstrip('/')
PW = os.environ.get('DEMO_PASSWORD') or sys.exit('Set DEMO_PASSWORD to the demo password first (see the usage at the top of this file).')
HDR = {'Accept': 'application/json', 'Content-Type': 'application/json', 'ngrok-skip-browser-warning': '1'}


def call(method, path, token=None, body=None):
    headers = dict(HDR)
    if token:
        headers['Authorization'] = 'Bearer ' + token
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(BASE + path, data=data, headers=headers, method=method)
    t0 = time.time()
    try:
        r = urllib.request.urlopen(req, timeout=30)
        status, text = r.status, r.read().decode()
    except urllib.error.HTTPError as e:
        status, text = e.code, e.read().decode()
    ms = int((time.time() - t0) * 1000)
    try:
        j = json.loads(text) if text else None
    except ValueError:
        j = None
    return status, j, ms


results = []


def check(label, got, want, ms, extra=''):
    ok = got == want
    results.append(ok)
    print(f"{'PASS' if ok else 'FAIL'}  {label:<58} {got} (want {want})  {ms}ms {extra}")


def login(email):
    s, j, ms = call('POST', '/auth/login', body={'email': email, 'password': PW, 'device_name': 'sweep'})
    check(f'sign in {email}', s, 200, ms)
    return j['data']['token'] if s == 200 else None


def get(token, path, label, want=200):
    s, j, ms = call('GET', path, token)
    check(label, s, want, ms)
    return j


# registrar
t = login('registrar@demo.test')
get(t, '/cohorts', 'registrar: cohorts')
get(t, '/students?per_page=5', 'registrar: students list')
get(t, '/registration-windows', 'registrar cannot manage windows', 403)

# lecturer
t = login('lecturer@demo.test')
j = get(t, '/lecturer/course-offerings', 'lecturer: my courses')
nur = next(o for o in j['data'] if o['course']['code'] == 'NUR 111')
print(f"      NUR 111: {nur['enrolled_count']} enrolled, status {nur['status']}")
j = get(t, f"/lecturer/course-offerings/{nur['id']}/marks", 'lecturer: NUR 111 class list')
get(t, '/grade-scale', 'lecturer: grade scale')
get(t, '/academics/course-offerings', 'lecturer cannot open the dashboard', 403)

# head
t = login('head_of_academics@demo.test')
j = get(t, '/academics/course-offerings', 'head: dashboard')
print('      summary:', j['meta']['summary'])
get(t, '/registration-windows', 'head: registration windows')

# student
t = login('student@demo.test')
get(t, '/me/student', 'student: my record')
j = get(t, '/me/registration', 'student: my registration')
print('      window:', (j['data']['window'] or {}).get('status'))
j = get(t, '/me/course-offerings', 'student: eligible courses')
print('      eligible:', [o['course']['code'] for o in j['data']])
get(t, '/me/results', 'student: my results')
get(t, '/lecturer/course-offerings', 'student cannot open marks', 403)

# ict admin and super admin
t = login('ict_admin@demo.test')
get(t, '/users?per_page=5', 'ict_admin: users')
t = login('super_admin@demo.test')
j = get(t, '/audit-logs?per_page=3', 'super_admin: audit log')
print('      latest audit ip:', j['data'][0].get('ip_address'))

# wrong password is refused, and docs are public
s, j, ms = call('POST', '/auth/login', body={'email': 'super_admin@demo.test', 'password': 'password', 'device_name': 'sweep'})
check('the old default password "password" is refused', s, 401, ms)

print(f"\n{sum(results)} of {len(results)} checks passed")
sys.exit(0 if all(results) else 1)
