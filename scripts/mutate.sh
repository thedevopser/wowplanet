#!/bin/sh
#
# Mutation testing, one class at a time against its dedicated tests only.
#
# Usage: scripts/mutate.sh [--base=<ref>]
#   PEST          command running Pest, "vendor/bin/pest" by default
#   MUTATION_MIN  minimum score per class; unset, the scores are only reported
#
# Pest's --min applies to a whole run: one run per class is what turns it into a
# per-class threshold, and what keeps a widely used class from dragging every
# covering feature test into each of its mutants.
#
# Within a class, the mutants run in parallel, one process per core: the plugin hands
# each one a TEST_TOKEN, which gives it its own database and its own Redis key prefix.
# Never pass --processes: the plugin forwards it to every mutant run, which rejects it,
# fails, and so counts every mutant as killed.

PEST=${PEST:-vendor/bin/pest}

plan=$(php artisan mutation:scope "$@") || { printf '%s\n' "$plan"; exit 1; }

if [ -z "$plan" ]; then
    echo "No class of the mutation perimeter to mutate."
    exit 0
fi

work=$(mktemp -d)
# Beside phpunit.xml, whose relative paths it keeps, and visible from the container.
config=".phpunit-mutation-$$.xml"
trap 'rm -rf "$work" "$config"' EXIT
printf '%s\n' "$plan" > "$work/plan"

# Paratest takes a single test path: the dedicated tests of a class go through a copy
# of phpunit.xml whose only suite lists them.
write_config() {
    awk -v tests="$1" '
        /<testsuites>/ {
            print "    <testsuites>"
            print "        <testsuite name=\"Dedicated\">"
            n = split(tests, files, " ")
            for (i = 1; i <= n; i++) print "            <file>" files[i] "</file>"
            print "        </testsuite>"
            skip = 1
        }
        !skip { print }
        /<\/testsuites>/ { print; skip = 0 }
    ' phpunit.xml > "$config"
}

status=0

while IFS=' ' read -r file tests; do
    if [ -z "$tests" ]; then
        printf '%-10s %s\n' 'no test' "$file" >> "$work/summary"
        [ -n "${MUTATION_MIN:-}" ] && status=1
        continue
    fi

    write_config "$tests"

    # Pest runs with stdin detached: it would otherwise consume the rest of the plan.
    # shellcheck disable=SC2086
    $PEST --configuration="$config" --mutate --parallel --path="$file" ${MUTATION_MIN:+--min=$MUTATION_MIN} < /dev/null > "$work/run" 2>&1
    code=$?
    cat "$work/run"

    score=$(sed 's/\x1b\[[0-9;]*m//g' "$work/run" | awk '$1 == "Score:" { print $2 }')
    printf '%-10s %s\n' "${score:-error}" "$file" >> "$work/summary"
    [ "$code" -eq 0 ] || status=1
done < "$work/plan"

echo
echo "Mutation score per class${MUTATION_MIN:+, minimum $MUTATION_MIN %}:"
cat "$work/summary"

exit $status
