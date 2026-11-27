from functools import wraps
from hashlib import sha256
import re
from secrets import compare_digest, token_urlsafe
import time
from urllib.parse import urlsplit

from flask import abort, g, redirect, request, session, url_for
from werkzeug.security import check_password_hash, generate_password_hash

from storage import db, transaction


EMAIL = re.compile(r"[^@\s]+@[^@\s]+\.[^@\s]+")
SESSION_AGE = 8 * 60 * 60
DUMMY_PASSWORD = generate_password_hash(token_urlsafe())


class InputError(ValueError):
    """A field failed validation and can be displayed back to the user."""


def field(name, label, max_length, min_length=1):
    value = request.form.get(name, "").strip()
    if not min_length <= len(value) <= max_length or "\x00" in value:
        raise InputError(f"{label} must contain {min_length}–{max_length} characters.")
    return value


def email_address(value):
    value = value.strip().lower()
    if len(value) > 254 or not EMAIL.fullmatch(value) or "\x00" in value:
        raise InputError("Enter a valid email address.")
    return value


def password_hash(password):
    if not 12 <= len(password) <= 128 or "\x00" in password:
        raise InputError("Use a password of 12–128 characters.")
    return generate_password_hash(password, method="scrypt")


def password_matches(user, password):
    if len(password) > 128:
        return False
    return check_password_hash(user["password_hash"] if user else DUMMY_PASSWORD, password)


def source_url(value):
    try:
        parsed = urlsplit(value)
        port = parsed.port
    except ValueError as error:
        raise InputError("Enter a valid public source URL.") from error
    if (
        parsed.scheme not in {"http", "https"} or not parsed.hostname
        or parsed.username is not None or parsed.password is not None or port == 0
        or any(char.isspace() or ord(char) < 32 for char in value) or "\\" in value
    ):
        raise InputError("Use an HTTP or HTTPS source URL without credentials.")
    return value


def digest(value):
    return sha256(value.encode()).hexdigest()


def limited(action, limit, window, subject):
    now = int(time.time())
    bucket = digest(f"{action}:{subject}")
    with transaction() as connection:
        connection.execute("DELETE FROM rate_limits WHERE expires <= ?", (now,))
        row = connection.execute(
            "SELECT attempts, expires FROM rate_limits WHERE bucket = ?", (bucket,)
        ).fetchone()
        if row and row["attempts"] >= limit:
            return True
        connection.execute(
            """INSERT INTO rate_limits (bucket, attempts, expires) VALUES (?, 1, ?)
               ON CONFLICT(bucket) DO UPDATE SET attempts = attempts + 1""",
            (bucket, now + window),
        )
    return False


def throttle(action, limit, window, subject=None):
    if limited(action, limit, window, subject or request.remote_addr or "unknown"):
        abort(429)


def csrf_token():
    if "csrf" not in session:
        session["csrf"] = token_urlsafe(32)
    return session["csrf"]


def verify_csrf():
    given = request.form.get("_csrf", "").encode()
    expected = session.get("csrf", "").encode()
    if not expected or not compare_digest(given, expected):
        abort(400, "The form expired. Reload the page and try again.")


def load_user():
    g.user = None
    if "sid" in session:
        g.user = db().execute(
            """SELECT users.* FROM users JOIN auth_sessions ON users.id = auth_sessions.user_id
               WHERE auth_sessions.token_hash = ? AND auth_sessions.expires > ?""",
            (digest(session["sid"]), int(time.time())),
        ).fetchone()
        if g.user is None:
            session.clear()


def create_session(user):
    sid = token_urlsafe(32)
    now = int(time.time())
    with transaction() as connection:
        current = connection.execute(
            "SELECT password_hash FROM users WHERE id = ?", (user["id"],)
        ).fetchone()
        if not current or current["password_hash"] != user["password_hash"]:
            abort(409, "The account changed. Sign in again.")
        connection.execute("DELETE FROM auth_sessions WHERE expires <= ?", (now,))
        if "sid" in session:
            connection.execute("DELETE FROM auth_sessions WHERE token_hash = ?", (digest(session["sid"]),))
        connection.execute(
            "INSERT INTO auth_sessions VALUES (?, ?, ?)",
            (digest(sid), user["id"], now + SESSION_AGE),
        )
    session.clear()
    session["sid"] = sid
    session.permanent = True


def roles(*allowed):
    def decorate(view):
        @wraps(view)
        def wrapped(*args, **kwargs):
            if g.user is None:
                return redirect(url_for("login"), code=303)
            if allowed and g.user["role"] not in allowed:
                abort(403)
            return view(*args, **kwargs)
        return wrapped
    return decorate
