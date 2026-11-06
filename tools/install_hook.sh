#!/usr/bin/env bash
# install_hook.sh - Install a git pre-commit hook that runs tools/run_checks.sh.
# This script does NOT install anything by itself: run it explicitly.
# It only writes .git/hooks/pre-commit (and refuses to overwrite an existing
# hook unless --force is given).
set -eu
cd "$(dirname "$0")/.."

ROOT="$(pwd)"
HOOK="$ROOT/.git/hooks/pre-commit"
FORCE="${1:-}"

if [ ! -d "$ROOT/.git" ]; then
  echo "install_hook: no .git directory found in $ROOT" >&2
  exit 1
fi

if [ -e "$HOOK" ] && [ "$FORCE" != "--force" ]; then
  echo "install_hook: $HOOK already exists, use --force to overwrite" >&2
  exit 1
fi

cat > "$HOOK" <<'HOOK'
#!/usr/bin/env bash
# Installed by tools/install_hook.sh - runs the cardiology quality gates.
set -u
cd "$(git rev-parse --show-toplevel)"
bash tools/run_checks.sh
HOOK
chmod +x "$HOOK"
echo "install_hook: installed $HOOK"
