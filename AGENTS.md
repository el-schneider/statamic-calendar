## Project Overview

**Statamic Calendar**. Recurring events and cached occurrences for Statamic v5/v6.

## Contributing

- Prefer inline documentation (docblocks, config comments, example templates) over README. The README should point to sources, not duplicate them.
- Pre-v1: prefer idiomatic Statamic APIs over preserving accidental BC. Reassess this once v1 stabilizes.
- Comments say why, not what changed. History belongs in the PR.
- Template/tag output changes: verify rendered output in a real Statamic app and say what you checked.
- Add nothing you can derive or reuse.
- Fix the cause, not the reported symptom.
- No abstraction with a single caller.
- Let failures surface. No try/catch for tidiness.
