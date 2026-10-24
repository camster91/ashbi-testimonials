# Security reporting

Do not disclose an unpatched exploit or customer data in a public issue. Use GitHub's **Report a vulnerability** option on this repository's Security tab if it is available. Private reporting availability has not been confirmed; this document does not claim that a private channel or security email is configured. If it is unavailable, open a public issue containing only “Please provide a private security reporting channel”, with no vulnerability details, then wait for the maintainer to arrange a private exchange. Never send credentials or real customer data.

Include the affected tag/commit, required attacker role, minimal fictional-data reproduction, expected/observed result, impact and any proposed fix. Test only on systems you are authorised to assess. There is no promised response SLA or bounty programme.

The next approved release is the supported maintenance target. v1.0.0 has known issues addressed by the reviewed development branches; the development candidate is not a published release. Before distribution the maintainer must confirm a private reporting channel, support owner and supported-version policy. Do not represent draft work as patched for users who still have the old ZIP.

## Maintainer response

Validate privately, record affected versions and the trust boundary, reproduce with synthetic data, implement and independently review a regression-tested fix, and prepare an advisory and recovery guidance. Coordinate publication and contact with explicit authorisation. Never silently remove reviews, change order records, reset mail claims or deploy a fix to client sites. A bad candidate can be withdrawn without deleting site data; see the recovery guide.
