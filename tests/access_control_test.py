"""
Pathways to Scholarships
Iteration 7 - Automated Access-Control Testing

Tests application endpoints across all five roles.
Passwords are entered interactively and are never saved.
Administrative POST requests use invalid CSRF tokens
to test security without modifying existing records.
"""

import csv
import getpass
import http.cookiejar
import urllib.request
import urllib.parse
import urllib.error

from pathlib import Path
from html.parser import HTMLParser
from datetime import datetime


BASE_URL = "http://localhost:8888"

ROOT = Path(__file__).resolve().parent.parent
DOCS = ROOT / "docs"

DOCS.mkdir(exist_ok=True)

ROLES = {
    "Visitor": None,
    "Student": "student@example.test",
    "Parent": "parent@example.test",
    "Administrator": "admin@example.test",
    "Super-admin": "superadmin@example.test"
}

SCHOLARSHIP_ID = 7
PROVIDER_ID = 17
BATCH_ID = 2
USER_ID = 6

results = []


# Prevent automatic redirects so we can inspect the
# original HTTP response and its destination.
class NoRedirect(urllib.request.HTTPRedirectHandler):

    def redirect_request(
        self, req, fp, code, msg, headers, newurl
    ):
        return None


# Extract CSRF tokens from HTML forms.
class TokenParser(HTMLParser):

    def __init__(self):
        super().__init__()
        self.token = None

    def handle_starttag(self, tag, attrs):

        attributes = dict(attrs)

        if (
            tag == "input"
            and attributes.get("name") == "csrf_token"
        ):
            self.token = attributes.get("value")


# Each role receives its own independent cookie session.
def create_session():

    cookies = http.cookiejar.CookieJar()

    return urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(cookies),
        NoRedirect()
    )


# Send an HTTP request and capture the actual response.
def request(session, path, method="GET", data=None):

    encoded = None

    if method == "POST":

        encoded = urllib.parse.urlencode(
            data or {}
        ).encode("utf-8")

    req = urllib.request.Request(
        BASE_URL + path,
        data=encoded,
        method=method
    )

    try:

        with session.open(req, timeout=30) as response:

            return (
                response.status,
                response.headers.get("Location", ""),
                response.read().decode(
                    "utf-8", errors="replace"
                )
            )

    except urllib.error.HTTPError as error:

        return (
            error.code,
            error.headers.get("Location", ""),
            error.read().decode(
                "utf-8", errors="replace"
            )
        )


# Sign in using the application's real login form.
def login(session, email, password):

    status, location, body = request(
        session,
        "/pages/login.php"
    )

    parser = TokenParser()
    parser.feed(body)

    if status != 200 or not parser.token:

        raise RuntimeError(
            "Unable to retrieve login form or CSRF token."
        )

    status, location, body = request(
        session,
        "/pages/login.php",
        "POST",
        {
            "email": email,
            "password": password,
            "csrf_token": parser.token
        }
    )

    if status != 302 or location != "/":

        raise RuntimeError(
            f"Login failed for {email}: "
            f"HTTP {status}, redirect {location}"
        )

    # Verify that authentication actually succeeded.
    status, location, body = request(
        session,
        "/pages/profile.php"
    )

    if status != 200 or email not in body:

        raise RuntimeError(
            f"Authenticated profile check failed for {email}"
        )


# Store expected and actual outcomes for the final report.
def record(
    role,
    method,
    path,
    permission,
    expected,
    actual,
    location="",
    expected_location=None,
    note=""
):

    passed = actual == expected

    if expected_location is not None:

        passed = (
            passed and location == expected_location
        )

    result = {
        "Role": role,
        "Method": method,
        "Endpoint": path,
        "Expected Permission": permission,
        "Expected HTTP": expected,
        "Expected Redirect": expected_location or "",
        "Actual HTTP": actual,
        "Actual Redirect": location,
        "Result": "PASS" if passed else "FAIL",
        "Notes": note
    }

    results.append(result)

    print(
        f"[{result['Result']}] "
        f"{role:15} {method:4} "
        f"{path} -> {actual}"
    )


# Determine permissions from the expected access policy,
# independently of the application's actual response.
def expected_access(role, scope):

    if scope == "public":
        return "Allow", 200, None

    if role == "Visitor":

        return (
            "Redirect",
            302,
            "/pages/login.php"
        )

    if scope == "authenticated":
        return "Allow", 200, None

    if scope == "admin":

        if role in ["Administrator", "Super-admin"]:
            return "Allow", 200, None

        return "Forbidden", 403, None

    if scope == "super":

        if role == "Super-admin":
            return "Allow", 200, None

        return "Forbidden", 403, None

    raise ValueError("Unknown permission scope.")


# Public application pages.
PUBLIC_PAGES = [
    "/index.php",
    "/pages/about.php",
    "/pages/contact.php",
    "/pages/faq.php",
    "/pages/register.php",
    f"/pages/scholarship_detail.php?id={SCHOLARSHIP_ID}",
    "/pages/scholarship_facts.php",
    "/pages/scholarships.php"
]


# Pages available to all authenticated users.
ACCOUNT_PAGES = [
    "/pages/profile.php",
    "/pages/change_password.php",
    "/pages/logout.php"
]


# Pages available to Administrator and Super-admin.
ADMIN_PAGES = [
    "/admin/coverage_report.php",
    "/admin/dashboard.php",
    "/admin/data_quality.php",
    "/admin/import.php",
    "/admin/import_history.php",
    "/admin/import_template.php",
    f"/admin/import_validation.php?batch_id={BATCH_ID}",
    "/admin/provider_form.php",
    f"/admin/provider_form.php?id={PROVIDER_ID}",
    f"/admin/provider_merge.php?source_id={PROVIDER_ID}",
    "/admin/providers.php",
    "/admin/scholarship_export.php",
    "/admin/scholarship_form.php",
    f"/admin/scholarship_form.php?id={SCHOLARSHIP_ID}",
    f"/admin/scholarship_view.php?id={SCHOLARSHIP_ID}",
    "/admin/scholarships.php"
]


# Pages restricted to Super-admin.
SUPER_PAGES = [
    "/admin/audit_log.php",
    "/admin/reference_data.php",
    "/admin/reference_form.php?table=states",
    "/admin/reference_form.php?table=states&id=1",
    "/admin/user_form.php",
    f"/admin/user_form.php?id={USER_ID}",
    f"/admin/user_reset_password.php?id={USER_ID}",
    "/admin/users.php"
]


# Administrative endpoints that require POST requests.
ADMIN_ACTIONS = [
    "/admin/import_commit.php",
    "/admin/import_rollback.php",
    "/admin/provider_merge_save.php",
    "/admin/provider_save.php",
    "/admin/scholarship_archive.php",
    "/admin/scholarship_bulk.php",
    "/admin/scholarship_save.php",
    "/admin/scholarship_verify.php"
]


SUPER_ACTIONS = [
    "/admin/reference_delete.php",
    "/admin/reference_save.php",
    "/admin/user_save.php",
    "/admin/user_status.php"
]


# Test a normal page using the expected role permissions.
def test_page(session, role, path, scope):

    permission, expected, redirect = expected_access(
        role, scope
    )

    # The provider edit form requires an existing provider ID.
    if path == "/admin/provider_form.php" and permission == "Allow":
        expected = 400

    actual, location, body = request(
        session, path
    )

    record(
        role,
        "GET",
        path,
        permission,
        expected,
        actual,
        location,
        redirect,
        "Missing provider ID validation"
        if path == "/admin/provider_form.php"
        else ""
    )


# Test POST-only endpoints using GET.
# Authorized users should receive HTTP 405.
def test_action_method(session, role, path, scope):

    permission, expected, redirect = expected_access(
        role, scope
    )

    if permission == "Allow":
        expected = 405

    actual, location, body = request(
        session, path
    )

    record(
        role,
        "GET",
        path,
        permission,
        expected,
        actual,
        location,
        redirect,
        "POST-only endpoint; no database changes."
    )


# Submit an invalid CSRF token to test protected actions.
# No valid modification data is supplied.
def test_action_csrf(
    session, role, path, scope, extra=None
):

    permission, expected, redirect = expected_access(
        role, scope
    )

    if permission == "Allow":
        expected = 403

    data = {
        "csrf_token": "invalid-test-token"
    }

    if extra:
        data.update(extra)

    actual, location, body = request(
        session,
        path,
        "POST",
        data
    )

    note = "Invalid CSRF token; no mutation expected."

    # Distinguish CSRF rejection from role rejection.
    if permission == "Allow" and actual == 403:

        lower_body = body.lower()

        if not any(
            word in lower_body
            for word in ["csrf", "token", "security"]
        ):

            note += " Review rejection message manually."

    record(
        role,
        "POST",
        path,
        permission,
        expected,
        actual,
        location,
        redirect,
        note
    )


# Verify that navigation displays only the appropriate menus.
def test_navigation(session, role):

    status, location, body = request(
        session, "/index.php"
    )

    checks = [
        (
            "Administration menu",
            "/admin/dashboard.php",
            role in ["Administrator", "Super-admin"]
        ),
        (
            "Super-admin menu",
            "/admin/users.php",
            role == "Super-admin"
        ),
        (
            "Profile link",
            "/pages/profile.php",
            role != "Visitor"
        ),
        (
            "Change Password link",
            "/pages/change_password.php",
            role != "Visitor"
        ),
        (
            "Login link",
            "/pages/login.php",
            role == "Visitor"
        )
    ]

    for label, link, expected_visible in checks:

        actual_visible = f'href="{link}"' in body

        passed = (
            status == 200
            and actual_visible == expected_visible
        )

        results.append({
            "Role": role,
            "Method": "NAV",
            "Endpoint": label,
            "Expected Permission":
                "Visible" if expected_visible else "Hidden",
            "Expected HTTP": 200,
            "Expected Redirect": "",
            "Actual HTTP": status,
            "Actual Redirect": "",
            "Result": "PASS" if passed else "FAIL",
            "Notes":
                "Visible" if actual_visible else "Hidden"
        })


# Test login page behavior for each role.
def test_login_page(session, role):

    expected = 200 if role == "Visitor" else 302

    redirect = None if role == "Visitor" else "/"

    actual, location, body = request(
        session, "/pages/login.php"
    )

    record(
        role,
        "GET",
        "/pages/login.php",
        "Allow" if role == "Visitor" else "Redirect",
        expected,
        actual,
        location,
        redirect
    )


# Run all endpoint tests for one role.
def test_role(role, session):

    print("\n" + "=" * 65)
    print(f"TESTING ROLE: {role}")
    print("=" * 65)

    for path in PUBLIC_PAGES:
        test_page(session, role, path, "public")

    for path in ACCOUNT_PAGES:
        test_page(session, role, path, "authenticated")

    for path in ADMIN_PAGES:
        test_page(session, role, path, "admin")

    for path in SUPER_PAGES:
        test_page(session, role, path, "super")

    for path in ADMIN_ACTIONS:

        test_action_method(
            session, role, path, "admin"
        )

        test_action_csrf(
            session, role, path, "admin"
        )

    for path in SUPER_ACTIONS:

        test_action_method(
            session, role, path, "super"
        )

        test_action_csrf(
            session, role, path, "super"
        )

    # Test pages that support both GET and POST.
    test_action_csrf(
        session,
        role,
        "/admin/user_reset_password.php",
        "super",
        {"user_id": USER_ID}
    )

    test_action_csrf(
        session,
        role,
        "/pages/profile.php",
        "authenticated"
    )

    test_action_csrf(
        session,
        role,
        "/pages/change_password.php",
        "authenticated"
    )

    test_login_page(session, role)
    test_navigation(session, role)


# Generate CSV and Markdown reports.
def generate_reports():

    csv_path = DOCS / "access_control_matrix.csv"

    with csv_path.open(
        "w", newline="", encoding="utf-8"
    ) as file:

        writer = csv.DictWriter(
            file,
            fieldnames=list(results[0].keys())
        )

        writer.writeheader()
        writer.writerows(results)

    passed = sum(
        row["Result"] == "PASS"
        for row in results
    )

    failed = len(results) - passed

    report = [
        "# Iteration 7 - Access-Control Test Report",
        "",
        f"Test date: {datetime.now():%Y-%m-%d %H:%M:%S}",
        "",
        "## Automated Test Summary",
        "",
        f"- Total tests: {len(results)}",
        f"- Passed: {passed}",
        f"- Failed: {failed}",
        "",
        "## Results by Role",
        "",
        "| Role | Tests | Passed | Failed |",
        "|---|---:|---:|---:|"
    ]

    for role in ROLES:

        role_results = [
            row for row in results
            if row["Role"] == role
        ]

        role_passed = sum(
            row["Result"] == "PASS"
            for row in role_results
        )

        role_failed = len(role_results) - role_passed

        report.append(
            f"| {role} | {len(role_results)} "
            f"| {role_passed} | {role_failed} |"
        )

    report.extend([
        "",
        "## Failed Tests",
        ""
    ])

    if failed == 0:

        report.append(
            "No automated test failures were recorded."
        )

    else:

        for row in results:

            if row["Result"] == "FAIL":

                report.append(
                    f"- {row['Role']} | "
                    f"{row['Method']} {row['Endpoint']} | "
                    f"Expected {row['Expected HTTP']} "
                    f"{row['Expected Redirect']} | "
                    f"Actual {row['Actual HTTP']} "
                    f"{row['Actual Redirect']} | "
                    f"{row['Notes']}"
                )

    report.extend([
        "",
        "## Testing Scope",
        "",
        "Automated tests verify HTTP access restrictions, "
        "direct URL access, navigation visibility, "
        "request methods, and selected CSRF rejection.",
        "",
        "Successful database mutations are not verified "
        "by invalid-token requests.",
        "",
        "Additional functional and security tests must "
        "be documented separately.",
        "",
        "Detailed results: access_control_matrix.csv"
    ])

    report_path = DOCS / "access_control_report.md"

    report_path.write_text(
        "\n".join(report) + "\n",
        encoding="utf-8"
    )

    print("\n" + "=" * 65)
    print("ACCESS-CONTROL TEST SUMMARY")
    print("=" * 65)

    print(f"Total tests: {len(results)}")
    print(f"Passed:      {passed}")
    print(f"Failed:      {failed}")

    print(f"\nCSV report: {csv_path}")
    print(f"Markdown report: {report_path}")


def main():

    print("Pathways to Scholarships")
    print("Iteration 7 - Access-Control Testing\n")

    sessions = {}

    # Authenticate each role before beginning the tests.
    for role, email in ROLES.items():

        session = create_session()

        if email:

            password = getpass.getpass(
                f"Enter {role} password ({email}): "
            )

            login(session, email, password)

            del password

            print(f"{role} login successful.")

        sessions[role] = session

    for role, session in sessions.items():

        test_role(role, session)

    generate_reports()


if __name__ == "__main__":
    main()
