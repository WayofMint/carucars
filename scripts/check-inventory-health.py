#!/usr/bin/env python3
"""
CaruCars inventory health check.

Hits the Hostinger /health-check.php endpoint, evaluates the DealerCenter
-> inventory-data.js pipeline, and alerts loudly if sold cars might still
be listed or new arrivals are missing.

Designed for:
  - Scheduled GitHub Actions runs (3x/day, see .github/workflows/)
  - Manual local invocation: `python3 scripts/check-inventory-health.py`
  - Claude Code interactive runs

Exit codes:
  0 = healthy (possibly after an auto-rebuild triggered by the endpoint)
  1 = persistent failure, needs human attention

Silent success (one-line stdout), loud failure (multi-line stderr).
"""
import json
import os
import subprocess
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

ENDPOINT = "https://yellowgreen-emu-225498.hostingersite.com/health-check.php?key=carucars-health-2026"
LOG_FILE = Path(os.environ.get("CARUCARS_HEALTH_LOG", str(Path.home() / ".carucars-health.log")))
JS_MAX_AGE_HOURS = 24      # inventory-data.js must be rebuilt at least daily
CSV_MAX_AGE_HOURS = 24     # DealerCenter pushes 3x/day; >24h stale = FTP broken
RETRY_WAIT_SECONDS = 30    # Give endpoint's auto-rebuild time to run


def log(msg: str) -> None:
    ts = datetime.now(timezone.utc).isoformat(timespec="seconds")
    try:
        LOG_FILE.parent.mkdir(parents=True, exist_ok=True)
        with LOG_FILE.open("a") as f:
            f.write(f"[{ts}] {msg}\n")
    except OSError:
        pass  # logging must never break the check


def fetch() -> dict:
    # curl/8.0 UA because Hostinger 403s unknown user-agents (observed with
    # Claude's WebFetch UA). curl is universally accepted.
    req = urllib.request.Request(ENDPOINT, headers={"User-Agent": "curl/8.0"})
    with urllib.request.urlopen(req, timeout=30) as r:
        body = r.read().decode("utf-8")
    return json.loads(body)


def hours_since(iso_str: str | None) -> float | None:
    if not iso_str:
        return None
    try:
        d = datetime.fromisoformat(iso_str.replace("Z", "+00:00"))
    except ValueError:
        return None
    if d.tzinfo is None:
        d = d.replace(tzinfo=timezone.utc)
    return (datetime.now(timezone.utc) - d).total_seconds() / 3600


def evaluate(data: dict) -> list[str]:
    """Return list of problem strings. Empty list = healthy."""
    problems = []

    if not data.get("healthy"):
        problems.append("endpoint reports healthy=false")

    vc = int(data.get("vehicle_count") or 0)
    if vc <= 0:
        problems.append(f"vehicle_count={vc} (expected >0)")

    js_age = data.get("inventory_js_age_hours")
    try:
        js_age_f = float(js_age)
    except (TypeError, ValueError):
        js_age_f = None
    if js_age_f is None:
        problems.append("inventory_js_age_hours missing — inventory-data.js may not exist")
    elif js_age_f >= JS_MAX_AGE_HOURS:
        problems.append(f"inventory_js_age_hours={js_age_f:.1f} (>={JS_MAX_AGE_HOURS}h — JS not rebuilt)")

    csv_age = hours_since(data.get("latest_csv_modified"))
    if csv_age is None:
        problems.append("latest_csv_modified missing — no CSVs from DealerCenter")
    elif csv_age >= CSV_MAX_AGE_HOURS:
        problems.append(
            f"latest_csv is {csv_age:.1f}h old (>={CSV_MAX_AGE_HOURS}h — DealerCenter FTP likely broken)"
        )

    return problems


def try_notify(message: str) -> None:
    """Best-effort desktop notification. Silent if no notifier available."""
    for cmd in (
        ["osascript", "-e", f'display notification "{message}" with title "CaruCars ALERT"'],
        ["notify-send", "-u", "critical", "CaruCars ALERT", message],
    ):
        try:
            subprocess.run(cmd, check=False, timeout=5,
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            return
        except (FileNotFoundError, subprocess.SubprocessError):
            continue


def loud_alert(data: dict, problems: list[str]) -> None:
    csv_age = hours_since(data.get("latest_csv_modified"))
    csv_age_str = f"{csv_age:.1f}" if csv_age is not None else "unknown"

    lines = [
        "",
        "!" * 72,
        "!!! CARUCARS INVENTORY PIPELINE BROKEN — NEEDS HUMAN ATTENTION !!!",
        "!" * 72,
        "",
        f"  vehicle_count:          {data.get('vehicle_count')}",
        f"  inventory_js_age_hours: {data.get('inventory_js_age_hours')}",
        f"  latest_csv:             {data.get('latest_csv')}",
        f"  latest_csv_modified:    {data.get('latest_csv_modified')}",
        f"  latest_csv_age_hours:   {csv_age_str}",
        f"  auto_rebuild_triggered: {data.get('auto_rebuild_triggered')}",
        "",
        "  Problems detected:",
    ]
    for p in problems:
        lines.append(f"    - {p}")

    server_issues = data.get("issues") or []
    if server_issues:
        lines.append("  Server-reported issues:")
        for i in server_issues:
            lines.append(f"    - {i}")

    if csv_age is not None and csv_age >= CSV_MAX_AGE_HOURS:
        lines += [
            "",
            "  >>> DealerCenter FTP appears BROKEN (no new CSV in 24h+).",
            "  >>> ACTION: Armani — contact Julius Pascua",
            "  >>>         Julius.Pascua@dealercenter.com   DCID 12327535",
        ]
    else:
        lines += [
            "",
            "  >>> CSV is arriving but conversion to JSON is failing.",
            "  >>> Check Hostinger: /home/.../sync-log.txt and cron-sync.php",
        ]

    lines += [
        "",
        "  Endpoint:  " + ENDPOINT.split("?")[0],
        "  Log file:  " + str(LOG_FILE),
        "",
    ]
    print("\n".join(lines), file=sys.stderr)
    try_notify("Inventory pipeline broken — see log/terminal.")


def short_summary(data: dict) -> str:
    return (
        f"CaruCars inventory OK — {data.get('vehicle_count')} vehicles, "
        f"JS {data.get('inventory_js_age_hours')}hr old, "
        f"latest CSV {data.get('latest_csv')}"
    )


def main() -> int:
    # Attempt 1
    try:
        data = fetch()
    except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, OSError) as e:
        log(f"ATTEMPT1 http-error: {e}")
        print(f"\n!!! CaruCars health endpoint unreachable: {e} !!!\n", file=sys.stderr)
        try_notify(f"Health endpoint unreachable: {e}")
        return 1
    except json.JSONDecodeError as e:
        log(f"ATTEMPT1 json-error: {e}")
        print(f"\n!!! CaruCars health endpoint returned invalid JSON: {e} !!!\n", file=sys.stderr)
        try_notify("Health endpoint returned invalid JSON")
        return 1

    problems = evaluate(data)
    if not problems:
        log(f"OK vehicles={data.get('vehicle_count')} js_age={data.get('inventory_js_age_hours')} "
            f"csv={data.get('latest_csv')}")
        print(short_summary(data))
        return 0

    # Endpoint auto-triggers rebuild on staleness — wait and recheck.
    log(f"ATTEMPT1 FAIL problems={problems} auto_rebuild={data.get('auto_rebuild_triggered')}")
    time.sleep(RETRY_WAIT_SECONDS)

    try:
        data = fetch()
    except Exception as e:  # noqa: BLE001
        log(f"ATTEMPT2 http-error: {e}")
        loud_alert(data, problems + [f"retry fetch failed: {e}"])
        return 1

    problems2 = evaluate(data)
    if not problems2:
        log(f"RECOVERED vehicles={data.get('vehicle_count')} (after auto-rebuild)")
        print("CaruCars inventory RECOVERED after auto-rebuild — " + short_summary(data).split("— ", 1)[1])
        return 0

    log(f"ATTEMPT2 STILL FAILING problems={problems2}")
    loud_alert(data, problems2)
    return 1


if __name__ == "__main__":
    sys.exit(main())
