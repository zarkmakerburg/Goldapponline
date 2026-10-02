# GoldApp Online release policy

GoldApp stable releases use the tag format:

```text
vMAJOR.MINOR.PATCH-goldapp.N
```

Example:

```text
v0.6.0-goldapp.1
```

## Channels

- `release`, `stable`, and `latest` install/update only from GoldApp release tags.
- `beta` and `main` explicitly track the GoldApp `main` branch.
- `auto` prefers the newest GoldApp stable release. If no GoldApp release exists yet, first-time installation may bootstrap from `main`; an already installed system does not silently cross from stable to `main`.

## Installer self-update

The installer resolves its update source before normal command parsing so `--channel` and `--version` affect the installer itself as well as the application payload.

Downloaded installers must:

- be valid Bash;
- contain the GoldApp repository identity marker;
- contain the expected command entry point;
- pass a syntax check before replacing `/root/install.sh`.

The local/remote installer comparison uses SHA-256.

## Release assets

Each GitHub release workflow publishes:

- the hosting ZIP bundle;
- the ZIP SHA-256 file;
- a tagged installer script;
- the installer SHA-256 file;
- a release manifest containing the tag, commit SHA, and checksums.

The release workflow refuses tags outside the GoldApp naming convention.

## Controlled release trigger

`RELEASE_VERSION` is the release trigger for the default branch. A reviewed change to that file causes the release workflow to build from the exact merged commit, validate that the core version matches the root `version` file, create the Git tag on that commit, create the GitHub Release, and upload the integrity assets.

This keeps tag creation inside the tested release workflow instead of relying on an unreviewed local command.

