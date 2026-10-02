# Upstream policy

GoldApp Online is an independent fork of [mahdiMGF2/mirzabot](https://github.com/mahdiMGF2/mirzabot) and remains licensed under AGPL-3.0-or-later.

## Source-of-truth rules

- Production install, self-update, release discovery, and source downloads must use `zarkmakerburg/Goldapponline`.
- The upstream Mirza repository is a reference and update source only; production systems must never auto-update directly from upstream.
- Upstream commits are reviewed before synchronization. Do not reset GoldApp branches to upstream or overwrite GoldApp-specific changes.
- Keep the legacy `mirza` CLI entry point for compatibility while exposing `goldapp` as the preferred command.
- Preserve upstream attribution and the project license when modifying or redistributing the software.

## Sync procedure

1. Review upstream commits and their changed files.
2. Apply only reviewed changes to a GoldApp integration branch.
3. Resolve conflicts in favor of GoldApp product behavior and security requirements.
4. Run GoldApp CI, installer syntax checks, and relevant smoke tests.
5. Merge only after the integration branch passes review.

## Current fork baseline

The fork was created from Mirza Bot and phase 1 independence was started from GoldApp commit `8ae4bd6852bfebadf3bcc949dc8a23ebea231d6a`. The upstream commit immediately following that baseline, `8e551ecf73d18dac8cb4b0cbada64c041e660f32`, was reviewed and its `panels.php` S-UI fix was synchronized into the phase-1 branch.
