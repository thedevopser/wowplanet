*[Français](CONTRIBUTING.md) · English*

# Contributing to WowPlanet

WowPlanet is a personal project. The repository is public, contributions are welcome, and the direction of the project stays with the maintainer: a technically sound proposal may still be turned down because it does not fit what is planned. Better said before you write any code.

If you are unsure, open an issue first. A ten-line discussion costs less than a pull request that gets refused.

## Before you start

The [README](README.en.md) covers the full setup: Docker for the application, PHP 8.4 and Node 26 on your machine for the tooling. The stack must be running for anything that touches the database, tests included.

Check that everything passes before you change anything:

```bash
make quality
```

If that command fails on a freshly cloned repository, it is the setup that needs fixing, not your code.

## The rule that decides everything: test first

Development is done with TDD, without exception and whatever part of the code you are in. You write the failing test, make it pass with the least code possible, then refactor while keeping the tests green.

**A bug fix starts with a test that reproduces the bug.** Without it, nothing proves the fix fixes anything. A fix submitted without a regression test will be sent back for that reason and no other.

Unit tests (`tests/Unit`) cover logic in isolation — no database, no network, no filesystem. Feature tests (`tests/Feature`) check the assembled parts through their real entry points: controller, command, job. End-to-end tests are not systematic; we add one when a critical path warrants it.

Minimum coverage is **80 %, measured file by file**. A global average hiding a file at 20 % is worthless. Anything genuinely untestable is excluded explicitly in the coverage configuration, with a justification; we never lower a threshold to let through a file nobody felt like testing.

## What the CI demands

The pipeline runs on every branch and every pull request. **A red pull request does not get merged**, with no exceptions.

| Step | Command | What it rejects |
| --- | --- | --- |
| Refactor | `make refactor` | Code Rector would rewrite |
| Style | `make lint` | Any deviation from Pint's Laravel preset |
| Static analysis | `make static` | A single PHPStan error at max level |
| Tests | `make test`, `make test-js` | A failing test |
| Coverage | `make coverage` | Anything below the thresholds |
| Documentation | `make docs-coverage` | A class created and left undocumented |
| Typing | `make mixed-check` | A `mixed` outside its declared boundaries |
| Parallel tests | `make temp-paths-check` | A `uniqid()` under `tests/`: temporary paths go through `testTempPath()` |

`make quality` chains all of it locally. Run it before pushing — it is exactly what the CI will replay.

No tool modifies a file in CI: Rector runs with `--dry-run`, Pint with `--test`. If a step reports something, the code gets fixed.

**The `make docs-coverage` ceiling never goes back up.** Any class you create under `app/` must be documented in `documentation/`, or the CI turns red. That is deliberate: it is what stops the documentation drifting while code is being written.

**No `mixed` outside its boundaries.** A value of unknown shape — JSON from an API, a raw database row — is narrowed on the line that receives it: through a `ResponsePayload` for a JSON response, through `is_array()` or `is_string()` elsewhere. The few files where the word `mixed` remains legitimate are listed and justified in `mixed-boundaries.txt`; adding one takes a real reason.

The `make install-hooks` hook replays these steps at commit time and re-stages whatever Rector and Pint corrected. It requires the `app` and `postgres` containers as soon as a PHP file is staged.

## Code conventions

**Strict typing in PHP.** `declare(strict_types=1)` at the top of every file; every parameter, return and property typed, `void` included. `mixed` is forbidden: if a value really can take several shapes, write the exact union. Arrays are never left as a bare `array` — a docblock states key and value (`array<string, Product>`, `list<Order>`, or the full shape `array{id: int, name: string}`). `mixed` only enters at the boundaries — `json_decode`, request data, untyped libraries — and is narrowed on the very line that receives it.

**Defensive programming.** Code validates what it receives and fails early, through guard clauses at the top of a function rather than nested `if`s. Invalid input throws a typed exception: never a silent return, never a `null` to signal an error. An impossible state must be impossible to construct.

**Pure functions wherever possible.** Effects — database, network, files, clock, randomness — are pushed to the boundaries and injected, so the core stays deterministic and testable without mock scaffolding. Immutability is the default.

**Comments are the exception.** A correct function name replaces a comment. A comment is written only when the information is not in the code: a non-obvious business constraint, a workaround for an external bug with its reference, a choice dictated by performance. Type docblocks are the exception; they carry what the code does not say.

**Languages.** Code is in English: variables, functions, classes, files, branches, configuration keys, technical log messages, test names. Only end-user strings follow the product's language. Published documentation is bilingual, French and English.

## Branches, commits and pull requests

One branch per feature or fix, named in English. **Never commit directly to `main`**, not even for a one-liner.

A commit matches one intent and leaves the repository in a coherent state. If a change is too big to fit in a readable commit, split it into coherent sub-features, one per commit. We do not lump several intents into a catch-all commit, and we do not slice an atomic change into meaningless ones.

**Commit messages and pull request descriptions are written in French**, whatever the language of the repository. A commit message describes what the change does. A pull request description covers the context, the change, and what needs checking.

What has no place in those texts: the story of how you got there, a list of what you explored, the tests you ran while figuring it out, your hesitations. Describe the change and its effect, not the path walked.

## What review looks at

In this order: does the test fail without the fix; is the CI green; does the change do what the description says and nothing else; does the code read without a comment to explain it; are edge cases handled or explicitly ruled out.

A pull request touching the Blizzard import gets extra attention: the quota is a shared, limited resource, and a mistake in a guard is paid for in hours of waiting. Any new bulk call to the API must go through the rate-limiting middleware and the hourly budget guard.

## Reporting a bug or suggesting an idea

Go through the [issues](https://github.com/thedevopser/wowplanet/issues); the templates are there for that. A security flaw does not go in an issue — see [SECURITY.md](SECURITY.md) for how to report one.

Exchanges on the repository follow the [code of conduct](CODE_OF_CONDUCT.md).

## Licence

The project is under the [GNU AGPL-3.0](LICENSE). You may use, modify and redistribute it, commercially included, on one condition: anyone who hosts a modified version and makes it reachable over a network must publish its complete source code under the same licence. By submitting a contribution, you agree to it being distributed under that licence.
