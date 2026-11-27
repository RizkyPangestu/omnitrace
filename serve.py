import os

from waitress import serve

from app import create_app


if __name__ == "__main__":
    app = create_app()
    options = {}
    if app.config["SESSION_COOKIE_SECURE"]:
        options.update(
            trusted_proxy="127.0.0.1",
            trusted_proxy_count=1,
            trusted_proxy_headers={"x-forwarded-for", "x-forwarded-proto"},
        )
    serve(
        app,
        host="127.0.0.1",
        port=int(os.environ.get("OMNITRACE_PORT", "5000")),
        max_request_body_size=app.config["MAX_CONTENT_LENGTH"],
        **options,
    )
