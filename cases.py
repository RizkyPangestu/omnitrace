from datetime import datetime
from io import BytesIO
import re
from uuid import uuid4

from flask import (
    abort, current_app, flash, g, redirect, render_template, request, send_file, url_for,
)

from security import InputError, field, roles, source_url
from storage import db, transaction


STATUSES = {
    "submitted": "Under review",
    "assigned": "In progress",
    "in_review": "Report under review",
    "revisions": "In progress",
    "published": "Published",
    "rejected": "Rejected",
}
ROLE_TITLES = {"client": "Investigation workspace", "analyst": "Analyst desk", "admin": "Investigation control"}
FILE_TYPES = {
    "pdf": (b"%PDF-", "application/pdf"),
    "png": (bytes.fromhex("89504e470d0a1a0a"), "image/png"),
    "jpg": (bytes.fromhex("ffd8ff"), "image/jpeg"),
}
MAX_ATTACHMENT = 2 * 1024 * 1024
SOURCE_DATETIME = re.compile(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}")


def case_for(case_id):
    role = g.user["role"]
    query = """SELECT cases.*, owner.name AS client_name, analyst.name AS analyst_name
               FROM cases JOIN users AS owner ON owner.id = cases.client_id
               LEFT JOIN users AS analyst ON analyst.id = cases.analyst_id WHERE cases.id = ?"""
    params = (case_id,)
    if role == "client":
        query += " AND cases.client_id = ?"
        params += (g.user["id"],)
    elif role == "analyst":
        query += " AND cases.analyst_id = ?"
        params += (g.user["id"],)
    case = db().execute(query, params).fetchone()
    if case is None:
        abort(404, "Case not found.")
    return case


def record_event(connection, case_id, event):
    connection.execute(
        "INSERT INTO case_events (case_id, actor_id, event) VALUES (?, ?, ?)",
        (case_id, g.user["id"], event),
    )


def report_for(connection, case_id):
    return connection.execute("SELECT * FROM reports WHERE case_id = ?", (case_id,)).fetchone()


def load_attachment():
    attachment = request.files.get("attachment")
    if not attachment or not attachment.filename:
        return None, None
    data = attachment.stream.read(MAX_ATTACHMENT + 1)
    if len(data) > MAX_ATTACHMENT:
        raise InputError("Attachment must be 2 MB or smaller.")
    kind = next((key for key, (prefix, _) in FILE_TYPES.items() if data.startswith(prefix)), None)
    if kind is None:
        raise InputError("Attach a PDF, PNG, or JPEG file.")
    return data, kind


def save_admin_decision(connection, case, decision):
    case_id = case["id"]
    if decision == "assign" and case["status"] == "submitted":
        analyst_id = field("analyst_id", "Analyst", 20)
        analyst = connection.execute(
            "SELECT id FROM users WHERE id = ? AND role = 'analyst'", (analyst_id,)
        ).fetchone()
        if analyst is None:
            raise InputError("Choose a registered analyst.")
        connection.execute(
            "UPDATE cases SET status = 'assigned', analyst_id = ? WHERE id = ?", (analyst["id"], case_id)
        )
        record_event(connection, case_id, "Analyst assigned")
    elif decision == "reject" and case["status"] == "submitted":
        reason = field("reason", "Rejection reason", 1200)
        connection.execute(
            "UPDATE cases SET status = 'rejected', decision_note = ? WHERE id = ?", (reason, case_id)
        )
        record_event(connection, case_id, "Request rejected")
    elif decision == "publish" and case["status"] == "in_review" and report_for(connection, case_id):
        connection.execute("UPDATE cases SET status = 'published', decision_note = '' WHERE id = ?", (case_id,))
        record_event(connection, case_id, "Final report published")
    elif decision == "revise" and case["status"] == "in_review":
        reason = field("reason", "Revision notes", 1200)
        connection.execute(
            "UPDATE cases SET status = 'revisions', decision_note = ? WHERE id = ?", (reason, case_id)
        )
        record_event(connection, case_id, "Report returned for revision")
    else:
        abort(409, "The decision is unavailable for this case status.")


def register_case_routes(app):
    @app.context_processor
    def case_labels():
        return {"statuses": STATUSES, "role_titles": ROLE_TITLES}

    @app.get("/workspace")
    @roles()
    def dashboard():
        query = "SELECT id, title, status, service, created_at FROM cases"
        params = ()
        role = g.user["role"]
        if role == "client":
            query += " WHERE client_id = ?"
            params = (g.user["id"],)
        elif role == "analyst":
            query += " WHERE analyst_id = ?"
            params = (g.user["id"],)
        cases = db().execute(query + " ORDER BY created_at DESC, id", params).fetchall()
        return render_template("dashboard.html", cases=cases)

    @app.route("/cases/new", methods=["GET", "POST"])
    def new_case():
        plans = current_app.config["PLANS"]
        services = current_app.config["SERVICES"]
        plan = request.args.get("plan", "")
        if g.user is None:
            return redirect(url_for("login", plan=plan) if plan in plans else url_for("login"), code=303)
        if g.user["role"] != "client":
            abort(403)
        if request.method == "POST":
            plan = field("plan", "Plan", 20)
            service = field("service", "Service", 80)
            if plan not in plans or service not in services:
                raise InputError("Choose an available plan and service.")
            if request.form.get("consent") != "yes":
                raise InputError("Confirm that you are authorized to submit this information.")
            title = field("title", "Request title", 140)
            purpose = field("purpose", "Purpose", 4000)
            context = field("context", "Initial information", 8000, 0)
            case_id = uuid4().hex
            with transaction() as connection:
                connection.execute(
                    """INSERT INTO cases
                       (id, client_id, title, plan, service, purpose, context)
                       VALUES (?, ?, ?, ?, ?, ?, ?)""",
                    (case_id, g.user["id"], title, plan, service, purpose, context),
                )
                record_event(connection, case_id, "Investigation brief received")
            flash("Investigation request submitted.")
            return redirect(url_for("case_detail", case_id=case_id), code=303)
        return render_template("new_case.html", plan=plan, plans=plans, services=services)

    @app.get("/cases/<case_id>")
    @roles()
    def case_detail(case_id):
        case = case_for(case_id)
        connection = db()
        role = g.user["role"]
        report = report_for(connection, case_id) if role != "client" or case["status"] == "published" else None
        evidence = connection.execute(
            """SELECT id, title, url, accessed, notes, attachment_type FROM evidence
               WHERE case_id = ? ORDER BY rowid DESC""",
            (case_id,),
        ).fetchall() if role != "client" else []
        events = connection.execute(
            """SELECT case_events.event, case_events.created_at, users.role FROM case_events
               JOIN users ON users.id = case_events.actor_id WHERE case_id = ?
               ORDER BY case_events.id DESC""",
            (case_id,),
        ).fetchall()
        analysts = connection.execute(
            "SELECT id, name, email FROM users WHERE role = 'analyst' ORDER BY name, email"
        ).fetchall() if role == "admin" and case["status"] == "submitted" else []
        return render_template(
            "case.html", case=case, report=report, evidence=evidence, events=events, analysts=analysts,
            plan=current_app.config["PLANS"][case["plan"]],
        )

    @app.post("/cases/<case_id>/decision")
    @roles("admin")
    def decide_case(case_id):
        with transaction() as connection:
            case = case_for(case_id)
            save_admin_decision(connection, case, request.form.get("decision"))
        flash("Decision saved.")
        return redirect(url_for("case_detail", case_id=case_id), code=303)

    @app.post("/cases/<case_id>/evidence")
    @roles("analyst")
    def add_evidence(case_id):
        case_for(case_id)
        title = field("title", "Evidence title", 140)
        public_url = source_url(field("url", "Source URL", 2048))
        accessed = field("accessed", "Source access time", 16)
        if not SOURCE_DATETIME.fullmatch(accessed):
            raise InputError("Enter a valid source access date and time in UTC.")
        try:
            datetime.fromisoformat(accessed)
        except ValueError as error:
            raise InputError("Enter a valid source access date and time in UTC.") from error
        notes = field("notes", "Findings and context", 6000)
        attachment, kind = load_attachment()
        with transaction() as connection:
            case = case_for(case_id)
            if case["status"] not in {"assigned", "revisions"}:
                abort(409, "Evidence can be added while a case is in progress.")
            connection.execute(
                """INSERT INTO evidence (id, case_id, title, url, accessed, notes, attachment, attachment_type)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)""",
                (uuid4().hex, case_id, title, public_url, accessed, notes, attachment, kind),
            )
            record_event(connection, case_id, "Source evidence recorded")
        flash("Evidence note saved.")
        return redirect(url_for("case_detail", case_id=case_id, _anchor="evidence"), code=303)

    @app.get("/cases/<case_id>/evidence/<evidence_id>/attachment")
    @roles("analyst", "admin")
    def download_evidence(case_id, evidence_id):
        case_for(case_id)
        row = db().execute(
            "SELECT attachment, attachment_type FROM evidence WHERE id = ? AND case_id = ?",
            (evidence_id, case_id),
        ).fetchone()
        if row is None or row["attachment"] is None:
            abort(404, "Attachment not found.")
        kind = row["attachment_type"]
        return send_file(
            BytesIO(row["attachment"]), mimetype=FILE_TYPES[kind][1], as_attachment=True,
            download_name=f"evidence-{evidence_id}.{kind}",
        )

    @app.post("/cases/<case_id>/report")
    @roles("analyst")
    def save_report(case_id):
        case_for(case_id)
        mode = request.form.get("mode")
        if mode not in {"draft", "submit"}:
            raise InputError("Choose to save the draft or submit it for review.")
        summary = field("summary", "Executive summary", 6000)
        findings = field("findings", "Findings", 12000)
        limitations = field("limitations", "Limitations", 6000)
        with transaction() as connection:
            case = case_for(case_id)
            if case["status"] not in {"assigned", "revisions"}:
                abort(409, "The report is locked at this case status.")
            connection.execute(
                """INSERT INTO reports (case_id, summary, findings, limitations) VALUES (?, ?, ?, ?)
                   ON CONFLICT(case_id) DO UPDATE SET summary = excluded.summary, findings = excluded.findings,
                   limitations = excluded.limitations, updated_at = CURRENT_TIMESTAMP""",
                (case_id, summary, findings, limitations),
            )
            if mode == "submit":
                connection.execute("UPDATE cases SET status = 'in_review', decision_note = '' WHERE id = ?", (case_id,))
                record_event(connection, case_id, "Report submitted for review")
        flash("Report submitted for review." if mode == "submit" else "Report draft saved.")
        return redirect(url_for("case_detail", case_id=case_id, _anchor="report"), code=303)

    @app.get("/cases/<case_id>/report/download")
    @roles()
    def download_report(case_id):
        case = case_for(case_id)
        if case["status"] != "published":
            abort(404, "Published report not found.")
        report = report_for(db(), case_id)
        if report is None:
            abort(404, "Published report not found.")
        document = (
            f"OmniTrace | {case['title']}\nCase {case_id}\n\n"
            f"Executive summary\n{report['summary']}\n\n"
            f"Findings\n{report['findings']}\n\n"
            f"Limitations\n{report['limitations']}\n"
        )
        return send_file(
            BytesIO(document.encode("utf-8")), mimetype="text/plain", as_attachment=True,
            download_name=f"omnitrace-{case_id}.txt",
        )
