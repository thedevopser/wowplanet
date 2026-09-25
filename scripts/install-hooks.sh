#!/bin/sh

# Install git pre-commit hook for quality checks
HOOK_DIR="$(git rev-parse --show-toplevel)/.git/hooks"

cat > "${HOOK_DIR}/pre-commit" << 'HOOK'
#!/usr/bin/env bash
#
# Pre-commit quality checks.
#
# Fixed order: Rector transforms, Pint formats the result, Larastan analyses what
# is left. Formatting before transforming lets unformatted code through, and
# analysing before transforming analyses code that no longer exists.
#
# Re-staging only ever touches paths that were already staged: a tool that edits a
# file outside the current commit leaves it in the working tree.

set -uo pipefail

BOLD=$(tput bold 2>/dev/null || printf '')
RESET=$(tput sgr0 2>/dev/null || printf '')

banner() {
    printf '\n%s========================================%s\n' "$BOLD" "$RESET"
    printf '%s  %s%s\n' "$BOLD" "$1" "$RESET"
    printf '%s========================================%s\n\n' "$BOLD" "$RESET"
}

fail() {
    printf '\nFAILED: %s\n' "$1" >&2
    printf 'Commit aborted. Fix it, then try again.\n' >&2
    exit 1
}

banner 'Pre-commit quality checks'

# --- Staged files ------------------------------------------------------------

mapfile -d '' STAGED < <(git diff --cached --name-only -z --diff-filter=ACMR)

if [ ${#STAGED[@]} -eq 0 ]; then
    echo 'Nothing staged to check.'
    exit 0
fi

has_staged() {
    local file
    for file in "${STAGED[@]}"; do
        if [[ $file =~ $1 ]]; then
            return 0
        fi
    done
    return 1
}

PHP_TOUCHED=false
JS_TOUCHED=false
has_staged '\.php$|^composer\.(json|lock)$|^phpstan\.neon$|^pint\.json$|^rector\.php$' && PHP_TOUCHED=true
has_staged '\.(js|ts|vue|css)$|^package(-lock)?\.json$|^vit(e|est)\.config\.js$' && JS_TOUCHED=true

if [ "$PHP_TOUCHED" = false ] && [ "$JS_TOUCHED" = false ]; then
    echo 'No PHP or front-end file staged, nothing to check.'
    exit 0
fi

# The suite runs on PostgreSQL, so the PHP steps need the stack, not just the app
# container. make test probes the database itself and reports it plainly.
if [ "$PHP_TOUCHED" = true ]; then
    if ! docker compose ps --status running --services 2>/dev/null | grep -qx app; then
        fail 'the app container is not running (make up).'
    fi

    if ! docker compose ps --status running --services 2>/dev/null | grep -qx postgres; then
        fail 'the postgres container is not running (make up).'
    fi
fi

# A staged file that also carries unstaged changes gets re-staged whole if a tool
# rewrites it: warn rather than sweeping it into the commit silently.
mapfile -d '' DIRTY_BEFORE < <(git diff --name-only -z -- "${STAGED[@]}")
if [ ${#DIRTY_BEFORE[@]} -gt 0 ]; then
    printf 'Warning: these files are only partially staged.\n'
    printf 'If a tool rewrites them, all of their content enters the commit:\n'
    printf '  - %s\n' "${DIRTY_BEFORE[@]}"
    printf '\n'
fi

restage() {
    git add -- "${STAGED[@]}" || fail 'could not re-stage.'
}

# --- PHP ---------------------------------------------------------------------

if [ "$PHP_TOUCHED" = true ]; then
    echo '[1/5] Refactor (Rector)...'
    make refactor || fail 'Rector errors found.'
    restage

    echo '[2/5] Lint (Pint)...'
    make lint || fail 'Pint errors found.'
    restage

    echo '[3/5] Static analysis (Larastan)...'
    make static || fail 'Larastan errors found.'

    echo '[4/5] PHP tests (Pest)...'
    make test || fail 'Pest tests failed.'
else
    echo '[1-4/5] PHP steps skipped: no PHP file staged.'
fi

# --- Front end ---------------------------------------------------------------

if [ "$JS_TOUCHED" = true ]; then
    echo '[5/5] JS tests (Vitest)...'
    make test-js || fail 'JS tests failed.'
else
    echo '[5/5] Front-end step skipped: no front-end file staged.'
fi

# --- Safety net --------------------------------------------------------------

# Rector, Pint and Larastan run over the whole project: they may have fixed files
# outside this commit. Those stay unstaged, and we say so.
mapfile -d '' DIRTY_AFTER < <(git diff --name-only -z)
if [ ${#DIRTY_AFTER[@]} -gt 0 ]; then
    printf '\nFixes left out of the commit, to handle separately:\n'
    printf '  - %s\n' "${DIRTY_AFTER[@]}"
fi

banner 'All checks passed!'
HOOK

chmod +x "${HOOK_DIR}/pre-commit"
echo "Pre-commit hook installed."
