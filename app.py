from datetime import timedelta
import os
from pathlib import Path

from flask import Flask, abort, render_template, request
from werkzeug.exceptions import HTTPException, SecurityError

from accounts import register_account_routes
from cases import register_case_routes
from security import InputError, SESSION_AGE, csrf_token, load_user, verify_csrf
from storage import close_db


PLANS = {"basic": "Basic · Rp420.000", "insight": "Insight · Rp760.000"}
SERVICES = ("Corporate due diligence", "Litigation support", "Digital footprint verification")


def create_app(config=None):
    app = Flask(__name__)
    local = os.environ.get("OMNITRACE_LOCAL") == "1"
    app.config.from_mapping(
        SECRET_KEY=os.environ.get("OMNITRACE_SECRET"),
        DATABASE=os.environ.get("OMNITRACE_DB", str(Path(app.instance_path) / "omni.sqlite3")),
        SESSION_COOKIE_NAME="omni_session" if local else "__Host-omni_session",
        SESSION_COOKIE_SECURE=not local,
        SESSION_COOKIE_HTTPONLY=True,
        SESSION_COOKIE_SAMESITE="Lax",
        SESSION_COOKIE_PATH="/",
        SESSION_REFRESH_EACH_REQUEST=False,
        PERMANENT_SESSION_LIFETIME=timedelta(seconds=SESSION_AGE),
        TRUSTED_HOSTS=os.environ.get("OMNITRACE_HOSTS", "localhost,127.0.0.1,[::1]").split(","),
        MAX_CONTENT_LENGTH=3 * 1024 * 1024,
        MAX_FORM_MEMORY_SIZE=128 * 1024,
        MAX_FORM_PARTS=16,
        PLANS=PLANS,
        SERVICES=SERVICES,
    )
    if config:
        app.config.update(config)
    if len(app.config["SECRET_KEY"] or "") < 32:
        raise RuntimeError("Set OMNITRACE_SECRET to a random secret of at least 32 characters.")

    @app.before_request
    def protect_request():
        if request.endpoint == "static":
            return
        if app.config["SESSION_COOKIE_SECURE"] and not request.is_secure:
            abort(400, "Use HTTPS to open OmniTrace.")
        if request.method == "POST":
            verify_csrf()
        load_user()

    @app.after_request
    def security_headers(response):
        response.headers["Content-Security-Policy"] = (
            "default-src 'none'; style-src 'self'; img-src 'self'; script-src 'none'; "
            "base-uri 'none'; form-action 'self'; frame-ancestors 'none'"
        )
        response.headers["X-Content-Type-Options"] = "nosniff"
        response.headers["X-Frame-Options"] = "DENY"
        response.headers["Referrer-Policy"] = "no-referrer"
        response.headers["Permissions-Policy"] = "camera=(), microphone=(), geolocation=()"
        if app.config["SESSION_COOKIE_SECURE"] and request.is_secure:
            response.headers["Strict-Transport-Security"] = "max-age=31536000"
        if request.endpoint != "static":
            response.headers["Cache-Control"] = "no-store"
        return response

    @app.context_processor
    def template_context():
        return {"csrf_token": csrf_token}

    @app.get("/")
    def home():
        return render_template("home.html")

    @app.get("/services")
    def services():
        return render_template("services.html")

    @app.get("/sample-report")
    def sample_report():
        return render_template("sample_report.html")

    @app.errorhandler(InputError)
    def invalid_input(error):
        return render_template("error.html", code=422, message=str(error)), 422

    @app.errorhandler(SecurityError)
    def invalid_host(error):
        return "Request host is unavailable.", 400, {"Content-Type": "text/plain; charset=utf-8"}

    @app.errorhandler(HTTPException)
    def http_error(error):
        response = error.get_response()
        response.set_data(render_template("error.html", code=error.code, message=error.description))
        response.content_type = "text/html; charset=utf-8"
        return response

    app.teardown_appcontext(close_db)
    register_account_routes(app)
    register_case_routes(app)
    return app
