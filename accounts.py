from getpass import getpass
import sqlite3
import time
from secrets import token_urlsafe

import click
from flask import abort, flash, g, redirect, render_template, request, session, url_for

from security import (
    InputError, create_session, digest, email_address, field, password_hash,
    password_matches, roles, throttle,
)
from storage import db, init_db, transaction


def submitted_password():
    password = request.form.get("password", "")
    if password != request.form.get("confirm", ""):
        raise InputError("The passwords must match.")
    return password_hash(password)


def complete_password_change(connection, user_id, hashed):
    connection.execute("UPDATE users SET password_hash = ? WHERE id = ?", (hashed, user_id))
    connection.execute("DELETE FROM password_resets WHERE user_id = ?", (user_id,))
    connection.execute("DELETE FROM auth_sessions WHERE user_id = ?", (user_id,))


def register_account_routes(app):
    @app.route("/login", methods=["GET", "POST"])
    def login():
        plan = request.values.get("plan", "")
        if plan not in app.config["PLANS"]:
            plan = ""
        if g.user is not None:
            return redirect(url_for("dashboard"), code=303)
        if request.method == "POST":
            throttle("login-ip", 20, 900)
            email = email_address(request.form.get("email", ""))
            throttle("login-email", 8, 900, email)
            user = db().execute("SELECT * FROM users WHERE email = ?", (email,)).fetchone()
            valid = password_matches(user, request.form.get("password", ""))
            if not (valid and user):
                return render_template("auth.html", mode="login", plan=plan, error="Invalid email or password."), 401
            create_session(user)
            if plan and user["role"] == "client":
                return redirect(url_for("new_case", plan=plan), code=303)
            return redirect(url_for("dashboard"), code=303)
        return render_template("auth.html", mode="login", plan=plan)

    @app.route("/register", methods=["GET", "POST"])
    def register():
        if g.user is not None:
            return redirect(url_for("dashboard"), code=303)
        if request.method == "POST":
            throttle("register", 8, 3600)
            email = email_address(request.form.get("email", ""))
            name = field("name", "Full name", 100)
            hashed = submitted_password()
            try:
                db().execute(
                    "INSERT INTO users (email, name, password_hash, role) VALUES (?, ?, ?, 'client')",
                    (email, name, hashed),
                )
            except sqlite3.IntegrityError:
                pass
            flash("If the address is available, your account is ready. Sign in with your password.")
            plan = request.form.get("plan", "")
            return redirect(url_for("login", plan=plan) if plan in app.config["PLANS"] else url_for("login"), code=303)
        return render_template("auth.html", mode="register", plan=request.args.get("plan", ""))

    @app.route("/reset-password", methods=["GET", "POST"])
    def reset_password():
        if request.method == "POST":
            throttle("reset", 8, 900)
            code = field("code", "Reset code", 100)
            hashed_code = digest(code)
            now = int(time.time())
            row = db().execute(
                "SELECT user_id FROM password_resets WHERE token_hash = ? AND expires > ?",
                (hashed_code, now),
            ).fetchone()
            if row is None:
                raise InputError("The reset code is invalid or expired.")
            hashed = submitted_password()
            with transaction() as connection:
                row = connection.execute(
                    "SELECT user_id FROM password_resets WHERE token_hash = ? AND expires > ?",
                    (hashed_code, int(time.time())),
                ).fetchone()
                if row is None:
                    raise InputError("The reset code is invalid or expired.")
                complete_password_change(connection, row["user_id"], hashed)
            if g.user is not None:
                db().execute("DELETE FROM auth_sessions WHERE token_hash = ?", (digest(session["sid"]),))
            session.clear()
            flash("Password updated. Sign in again.")
            return redirect(url_for("login"), code=303)
        return render_template("auth.html", mode="reset")

    @app.post("/logout")
    @roles()
    def logout():
        db().execute("DELETE FROM auth_sessions WHERE token_hash = ?", (digest(session["sid"]),))
        session.clear()
        return redirect(url_for("login"), code=303)

    @app.route("/profile", methods=["GET", "POST"])
    @roles()
    def profile():
        if request.method == "POST":
            name = field("name", "Full name", 100)
            organization = field("organization", "Organization", 160, 0)
            db().execute("UPDATE users SET name = ?, organization = ? WHERE id = ?", (name, organization, g.user["id"]))
            flash("Profile saved.")
            return redirect(url_for("profile"), code=303)
        return render_template("profile.html")

    @app.post("/profile/password")
    @roles()
    def change_password():
        throttle("change-password", 8, 900, str(g.user["id"]))
        if not password_matches(g.user, request.form.get("current_password", "")):
            raise InputError("Current password is incorrect.")
        hashed = submitted_password()
        with transaction() as connection:
            current = connection.execute(
                "SELECT password_hash FROM users WHERE id = ?", (g.user["id"],)
            ).fetchone()
            if current["password_hash"] != g.user["password_hash"]:
                abort(409, "The account changed. Sign in again.")
            complete_password_change(connection, g.user["id"], hashed)
        session.clear()
        flash("Password updated. Sign in again.")
        return redirect(url_for("login"), code=303)

    @app.cli.command("init-db")
    def init_db_command():
        init_db()
        click.echo("Database initialized.")

    @app.cli.command("create-staff")
    @click.argument("role", type=click.Choice(["admin", "analyst"]))
    @click.argument("email")
    @click.argument("name")
    def create_staff(role, email, name):
        try:
            email = email_address(email)
            if not 1 <= len(name.strip()) <= 100:
                raise InputError("Name must contain 1–100 characters.")
            password = getpass("Password (12–128 characters): ")
            if password != getpass("Confirm password: "):
                raise InputError("The passwords must match.")
            db().execute(
                "INSERT INTO users (email, name, password_hash, role) VALUES (?, ?, ?, ?)",
                (email, name.strip(), password_hash(password), role),
            )
        except sqlite3.IntegrityError as error:
            raise click.ClickException("That email address is already registered.") from error
        except InputError as error:
            raise click.ClickException(str(error)) from error
        click.echo("Staff account created.")

    @app.cli.command("issue-reset")
    @click.argument("email")
    def issue_reset(email):
        try:
            email = email_address(email)
        except InputError as error:
            raise click.ClickException(str(error)) from error
        user = db().execute("SELECT id FROM users WHERE email = ?", (email,)).fetchone()
        if not user:
            raise click.ClickException("Account not found.")
        code = token_urlsafe(32)
        db().execute(
            """INSERT INTO password_resets VALUES (?, ?, ?)
               ON CONFLICT(user_id) DO UPDATE
               SET token_hash = excluded.token_hash, expires = excluded.expires""",
            (user["id"], digest(code), int(time.time()) + 900),
        )
        click.echo(code)
