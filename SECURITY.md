# Security policy

How to report a security problem in Zana privately, and how fixes are
released.

## Reporting a vulnerability

Please do not open a public issue. Report it privately through GitHub:
**Security → Report a vulnerability** on the repository concerned, or on
[zana-security](https://github.com/mjnable/zana-security/security/advisories/new)
when you are not sure which package it is.

Include what an attacker can do, the Zana version, and the steps or code that
show it. You will get an answer, and the fix is published with a GitHub
security advisory that credits you, unless you prefer otherwise.

## Supported versions

All Zana packages share one version number. Security fixes go to the latest
minor version, as a patch release (for example 2.19.1). Upgrade with
`composer update`.

## How Zana checks its own code

Every release passes a security check before it is tagged: who can reach the
code, what the worst outcome would be, and hostile inputs run against the
real code, with a test kept for every hole found. Applications built with Zana
get the same check in their `AGENTS.md`.
