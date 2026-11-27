from contextlib import contextmanager
from pathlib import Path
import sqlite3

from flask import current_app, g


def db():
    if "db" not in g:
        g.db = sqlite3.connect(current_app.config["DATABASE"], isolation_level=None, timeout=10)
        g.db.row_factory = sqlite3.Row
        g.db.execute("PRAGMA foreign_keys = ON")
    return g.db


def close_db(error=None):
    connection = g.pop("db", None)
    if connection is not None:
        connection.close()


def init_db():
    path = Path(current_app.config["DATABASE"])
    path.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
    path.touch(mode=0o600, exist_ok=True)
    connection = db()
    connection.execute("PRAGMA journal_mode = WAL")
    connection.executescript(Path(__file__).with_name("schema.sql").read_text(encoding="utf-8"))


@contextmanager
def transaction():
    connection = db()
    connection.execute("BEGIN IMMEDIATE")
    try:
        yield connection
        connection.commit()
    except BaseException:
        connection.rollback()
        raise
