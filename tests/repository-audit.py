#!/usr/bin/env python3
"""Read-only, tracked-file Pontifex audit. Flags are leads, not confirmed bugs.

Run: python3 tests/repository-audit.py --root . --outdir /tmp/pontifex-audit
No imports beyond Python standard library; it does not send data to providers.
"""
from __future__ import annotations

import argparse
import collections
import hashlib
import json
import re
import subprocess
from pathlib import Path

RULES = {
    "php": [
        ("raw-request", r"\$_(?:POST|GET|REQUEST|FILES|COOKIE)\b", "User-input boundary requires targeted inspection"),
        ("generic-request", r"\$_REQUEST\b", "Broad request input: check allowed keys and expected method"),
        ("error-log", r"\berror_log\s*\(", "Check diagnostic log content for PII/secrets"),
        ("sql-direct", r"\$wpdb->(?:query|prepare|get_var|get_results|insert|update|delete)\s*\(", "Review SQL preparation, table identifiers, and transactions"),
        ("dynamic-code", r"\b(?:eval|extract|unserialize|create_function)\s*\(", "Dynamic execution/deserialization requires security review"),
        ("html-output", r"\b(?:echo|print)\s+(?!esc_|wp_kses|htmlspecialchars)", "Review context-dependent HTML output escaping"),
        ("open-permission", r"['\"]permission_callback['\"]\s*=>\s*['\"]__return_true['\"]", "Public REST route: verify only public information is returned"),
        ("mail-effect", r"\bwp_mail\s*\(", "External email side effect: check recipient, PII and retry semantics"),
        ("curl-raw", r"\b(?:curl_init|file_get_contents)\s*\(\s*['\"]https?://", "External network call: prefer WP HTTP API for plugin"),
        ("global-scoping", r"\bglobal\s+\$", "Audit mutable globals and bootstrapping"),
        ("debug-marker", r"AI-PATCH|FIXME|TODO|HACK", "Residual patch note or incomplete implementation"),
    ],
    "javascript": [
        ("html-sink", r"\.html\s*\(|\.append\s*\(|innerHTML\s*=|insertAdjacentHTML\s*\(", "Check data-to-HTML encoding/DOM construction"),
        ("debug-console", r"\bconsole\.(?:log|debug|info|warn|error)\s*\(", "Check PII, nonces and noisy logs"),
        ("browser-store", r"\b(?:localStorage|sessionStorage)\b", "Check PII, persistence and data lifecycle"),
        ("dynamic-code", r"\b(?:eval|Function)\s*\(|document\.write\s*\(", "Unsafe browser dynamic code requires review"),
        ("ajax-network", r"\b(?:fetch|ajax)\s*\(", "Review endpoint authorization, failover, error and concurrency"),
        ("jquery-global", r"\$\s*\(|\bjQuery\s*\(", "jQuery dependency and scope: verify enqueue order"),
        ("debug-marker", r"AI-PATCH|FIXME|TODO|HACK", "Residual patch note"),
    ],
    "css": [
        ("important", r"!important", "Specificity debt: validate rendered cascade before removing"),
        ("global-selector", r"(?m)^\s*(?:html|body|h[1-6]|button|input|select|textarea|p)\s*[,{\[]", "Global selector may style unrelated WordPress UI"),
        ("url-import", r"@import\s+|url\(\s*['\"]?https?://", "External style resource; check privacy/CSP/dependencies"),
    ],
    "other": [
        ("audit-marker", r"(?i)\b(?:TODO|FIXME|AI-PATCH)\b", "Incomplete documentation or generated marker"),
    ],
}

FUNCTIONS = {
    "php": r"\bfunction\s+([A-Za-z_][A-Za-z_0-9]*)\s*\(",
    "javascript": r"\bfunction\s+([A-Za-z_$][A-Za-z_$0-9]*)\s*\(|\b(?:const|let)\s+([A-Za-z_$][A-Za-z_$0-9]*)\s*=\s*(?:async\s*)?\([^)]*\)\s*=>",
}

def file_type(path: str) -> str:
    if path.endswith(".php"):
        return "php"
    if path.endswith(".js"):
        return "javascript"
    if path.endswith(".css"):
        return "css"
    return "other"

def search_rules(data: str, kind: str) -> list[dict]:
    findings = []
    for rule_id, expression, explanation in RULES[kind]:
        matches = list(re.finditer(expression, data))
        if matches:
            lines = sorted({data.count("\n", 0, match.start()) + 1 for match in matches})
            findings.append({
                "rule": rule_id,
                "count": len(matches),
                "lines": lines[:12],
                "summary": explanation,
                "classification": "review-signal-only",
            })
    return findings

def symbols_of(text: str, kind: str) -> list[dict]:
    pattern = FUNCTIONS.get(kind)
    if pattern is None:
        return []
    out = []
    for match in re.finditer(pattern, text):
        name = next((part for part in match.groups() if part), None)
        if name:
            out.append({"symbol": name, "line": text.count("\n", 0, match.start()) + 1})
    return out

def markdown_safe(s: str) -> str:
    return s.replace("|", r"\|").replace("\n", " ")

def file_checks(root: Path, allpaths: set[str]) -> list[dict]:
    checks = []
    composer = root / "composer.json"
    if composer.is_file():
        try:
            obj = json.loads(composer.read_text(encoding="utf-8"))
        except (json.JSONDecodeError, UnicodeError) as e:
            checks.append({"status": "failure", "id": "composer-json-invalid", "detail": str(e)})
        else:
            classmap = (obj.get("autoload") or {}).get("classmap", [])
            missing = [p for p in classmap if p not in allpaths]
            checks.append({
                "id": "composer-classmap-files",
                "status": "failure" if missing else "pass",
                "detail": "Missing: " + ", ".join(missing) if missing else f"{len(classmap)} classmap files exist",
            })
            checks.append({
                "id": "composer-php-floor",
                "status": "pass" if obj.get("require", {}).get("php") == "^8.1" else "review",
                "detail": f"Composer PHP constraint: {obj.get('require', {}).get('php')}",
            })
    main_php = root / "pontifex-oi.php"
    readme = root / "readme.txt"
    if main_php.exists() and readme.exists():
        import re
        h1 = re.search(r"Requires PHP:\s*([^\r\n]+)", main_php.read_text(errors="replace"))
        h2 = re.search(r"Requires PHP:\s*([^\r\n]+)", readme.read_text(errors="replace"))
        checks.append({
            "id": "php-header-consistency",
            "status": "pass" if h1 and h2 and h1.group(1).strip() == h2.group(1).strip() else "failure",
            "detail": f"plugin={h1.group(1).strip() if h1 else 'absent'} readme={h2.group(1).strip() if h2 else 'absent'}",
        })
    tree = [p for p in allpaths if p.startswith("assets/")]
    checks.append({"id": "asset-file-inventory", "status": "pass", "detail": f"{len(tree)} tracked asset files"})
    checks.append({
        "id": "runtime-vendor-present-in-source",
        "status": "pass" if any(p.startswith("vendor/") for p in allpaths) else "review",
        "detail": "vendor/ not tracked; release ZIP requires deterministic no-dev Composer install",
    })
    for p in sorted(p for p in allpaths if p.endswith((".yml", ".yaml"))):
        text = (root / p).read_text(errors="replace")
        bad = []
        for line_no, line in enumerate(text.splitlines(), start=1):
            match = re.search(r"^\s*uses:\s*([^\s#]+)", line)
            if match and not re.search(r"@[0-9a-fA-F]{40}$", match.group(1)):
                bad.append(line_no)
        checks.append({"id": f"action-sha-pinning:{p}",
                       "status": "review" if bad else "pass",
                       "detail": f"unpinned uses lines: {bad}" if bad else "all uses commit pinned"})
    return checks

def run(root: Path, outdir: Path) -> dict:
    names = subprocess.check_output(["git", "-C", str(root), "ls-files", "-z"]).split(b"\0")
    paths = sorted({n.decode("utf-8") for n in names if n})
    if not paths:
        raise RuntimeError("No tracked files; GitHub checkout is required")
    records = []
    total_bytes = 0
    total_lines = 0
    for p in paths:
        raw = (root / p).read_bytes()
        text = raw.decode("utf-8", errors="replace")
        kind = file_type(p)
        lines = len(text.splitlines())
        total_bytes += len(raw)
        total_lines += lines
        record = {
            "path": p,
            "folder": p.rsplit("/", 1)[0] if "/" in p else ".",
            "kind": kind,
            "bytes": len(raw),
            "lines": lines,
            "sha256": hashlib.sha256(raw).hexdigest(),
            "signals": search_rules(text, kind),
            "symbols": symbols_of(text, kind),
        }
        records.append(record)
    counts = collections.Counter(r["kind"] for r in records)
    folder_counts = collections.Counter(r["folder"] for r in records)
    total_signals = sum(sum(sig["count"] for sig in r["signals"]) for r in records)
    git_sha = subprocess.check_output(["git", "-C", str(root), "rev-parse", "HEAD"], text=True).strip()
    result = {
        "schema_version": "1.0",
        "scope": "all tracked files on current checked-out commit; findings are heuristic until reviewed",
        "head_sha": git_sha,
        "files_count": len(records),
        "total_bytes": total_bytes,
        "total_lines": total_lines,
        "file_types": dict(counts),
        "folders": dict(folder_counts),
        "signal_occurrences": total_signals,
        "structure_checks": file_checks(root, set(paths)),
        "files": records,
    }
    outdir.mkdir(parents=True, exist_ok=True)
    (outdir / "repository-audit.json").write_text(
        json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    output = [
        "# Pontifex — complete tracked-file static audit",
        "",
        f"Commit: `{git_sha}` — files: **{len(records)}** — source bytes: **{total_bytes}** "
        f"— lines: **{total_lines}** — heuristic pattern occurrences: **{total_signals}**.",
        "",
        "**Boundary:** Every tracked file was read and SHA256-hashed. Rules are mechanical REVIEW "
        "signals; this does not demonstrate that every function behaves correctly, nor does it "
        "execute SOAP, Mollie, SMTP, accessibility or a live site.",
        "",
        "## Structural checks",
        "",
        "| Check | Result | Explanation |",
        "|---|---|---|",
    ]
    for check in result["structure_checks"]:
        output.append(f"| {markdown_safe(check['id'])} | {check['status']} | "
                      f"{markdown_safe(check['detail'])} |")
    output += ["", "## Complete per-file evidence matrix", "",
               "| Path | Type | Bytes | Lines | SHA256 (first 12) | Heuristic signals | Functions/symbols |",
               "|---|---|---:|---:|---|---|---|"]
    for rec in records:
        note = ", ".join(f"{x['rule']}:{x['count']} @{','.join(map(str, x['lines'][:3]))}"
                         for x in rec["signals"])
        symbol_names = ", ".join(f"{s['symbol']}:{s['line']}" for s in rec["symbols"][:10])
        if len(rec["symbols"]) > 10:
            symbol_names += f" (+{len(rec['symbols']) - 10} others in JSON)"
        output.append(f"| `{rec['path']}` | {rec['kind']} | {rec['bytes']} | {rec['lines']} | "
                      f"`{rec['sha256'][:12]}` | {markdown_safe(note or '-')} | "
                      f"{markdown_safe(symbol_names or '-')} |")
    output += ["", "## Folder coverage", "",
               "| Folder | Files |", "|---|---:|"]
    output += [f"| `{key}` | {n} |" for key, n in sorted(folder_counts.items())]
    output += ["", "## Interpretation and next steps", "",
               "- Look up each flagged file and its full lines in the JSON artifact, not just the first three line numbers.",
               "- Manually check public requests, output encoding, global state, payment + mail side effects and migrations.",
               "- Run PHP/JS syntax, WordPress compatibility smoke, Composer, WPCS/Plugin Check and stage-based runtime verification separately.",
               "- No automatic severity or 'fixed' claim comes from a regex count. All release-critical behaviour needs external proof."]
    (outdir / "repository-audit.md").write_text("\n".join(output) + "\n", encoding="utf-8")
    print(f"AUDIT_RESULT|head={git_sha}|files={len(records)}|bytes={total_bytes}|"
          f"lines={total_lines}|heuristic_occurrences={total_signals}")
    for rec in records:
        ids = ",".join(f"{x['rule']}:{x['count']}" for x in rec["signals"]) or "-"
        print(f"AUDIT_FILE|{rec['path']}|{rec['kind']}|{rec['lines']}|{ids}")
    for check in result["structure_checks"]:
        print(f"AUDIT_CHECK|{check['status']}|{check['id']}|{check['detail']}")
    return result

def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", default=".")
    parser.add_argument("--outdir", default="audit-results")
    args = parser.parse_args()
    root = Path(args.root).resolve()
    result = run(root, Path(args.outdir))
    return 0 if not any(check["status"] == "failure"
                        for check in result["structure_checks"]) else 2

if __name__ == "__main__":
    raise SystemExit(main())
