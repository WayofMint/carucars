#!/usr/bin/env python3
"""
CaruCars inventory health check.

Hits the Hostinger /health-check.php endpoint and reports on the pipeline:
  DealerCenter CSV  →  feed.php  →  GitHub Actions  →  carucars.com

Exit 0 on healthy, 1 on persistent failure.
Quiet on success, loud on failure.
"""
import json
import sys
import time
import urllib.error
import urllib.request

ENDPOINT = "https://yellowgreen-emu-225498.hostingersite.com/health-check.php?key=carucars-health-2026"
RETRY_WAIT = 60  # seconds — give in-flight sync workflow time to recover


def fetch() -> dict:
    req = urllib.request.Request(ENDPOINT, headers={"User-Agent": "curl/8.0"})
    with urllib.request.urlopen(req, timeout=30) as r:
        return json.loads(r.read().decode("utf-8"))


def summary(d: dict) -> str:
    prod = d.get("production", {}).get("vehicle_count", "?")
    csv_age = d.get("csv", {}).get("age_hours", "?")
    return f"CaruCars OK — prod {prod} vehicles, CSV {csv_age}h old"


def main() -> int:
    try:
        data = fetch()
    except (urllib.error.URLError, TimeoutError, OSError) as e:
        print(f"Health endpoint unreachable: {e}", file=sys.stderr)
        return 1
    except json.JSONDecodeError as e:
        print(f"Health endpoint returned invalid JSON: {e}", file=sys.stderr)
        return 1

    if data.get("healthy"):
        print(summary(data))
        return 0

    # Wait for any in-flight sync to complete, then recheck.
    time.sleep(RETRY_WAIT)
    try:
        data = fetch()
    except Exception as e:
        print(f"Recheck failed: {e}", file=sys.stderr)
        return 1

    if data.get("healthy"):
        print("RECOVERED — " + summary(data).split("— ", 1)[1])
        return 0

    # Loud failure
    print("", file=sys.stderr)
    print("!" * 60, file=sys.stderr)
    print("CaruCars inventory pipeline BROKEN", file=sys.stderr)
    print("!" * 60, file=sys.stderr)
    for issue in data.get("issues", []):
        print(f"  - {issue}", file=sys.stderr)
    print("", file=sys.stderr)
    print("  CSV on Hostinger:   " + str(data.get("csv")), file=sys.stderr)
    print("  feed.php response:  " + str(data.get("inventory_feed")), file=sys.stderr)
    print("  carucars.com state: " + str(data.get("production")), file=sys.stderr)
    print("", file=sys.stderr)

    # Guidance based on where it's broken.
    issues = " ".join(data.get("issues", []))
    if "CSV_STALE" in issues or "CSV_MISSING" in issues:
        print("  > DealerCenter FTP push appears broken. Check DC export config.", file=sys.stderr)
    elif "FEED_" in issues:
        print("  > Hostinger feed.php is failing. Check Hostinger PHP logs.", file=sys.stderr)
    elif "PROD_" in issues:
        print("  > Sync workflow not committing fresh data.", file=sys.stderr)
        print("  > Run: gh workflow run sync-inventory.yml -R WayofMint/carucars", file=sys.stderr)
    return 1


if __name__ == "__main__":
    sys.exit(main())
