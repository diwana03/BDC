# BDC v2.3.3-dev396

Release date: 25 August 2026  
Build: 3102  
Branch: `develop`

## Approved competition history controls progression

- Separates the category entered for an event from the competitor's permanent BDC career division.
- Website profile requests may declare Bachata Rising, Bachata Open, Salsa Rising, Salsa Open, or another competition category without changing the stored permanent division.
- New competitors created from Manual, Automatic, Registration Desk, or isolated Test scoring begin as provisional Novice identities regardless of the category being tested.
- Existing competitors retain their permanent division when a website/profile request is approved; contact, identity, photo, and role changes still apply.
- Event-entry audits now record the entered category and explicitly record that no permanent division change occurred.

## Super Admin approval gate

- Eligibility and irreversible higher-division history now use only official `bdc_participant_results` and `bdc_point_transactions` records.
- Those official records are written only inside the existing Super Admin publication approval action.
- Draft rounds, unsubmitted results, rejected publications, Test scoring entries, and website category requests cannot promote or block a competitor.
- Bachata and Salsa points/history are scoped separately, including role-specific history.
- Special categories remain competition categories rather than permanent career divisions.

## Test and Live parity

- One shared registration hook applies the same rule to Manual and Automatic Live scoring, Registration Desk, and isolated Test scoring.
- Test entries remain in `bdc_test_scoring_*`; the official identity mirror retains the competitor's provisional/approved career state.
- Dance Cup and Jack & Jill scoring calculations are unchanged.
- Projector behavior is unchanged.

## Mandatory sanity gate

Automated test: `node tests/approved-category-progression-v396.js`

The gate verifies:

- provisional identity creation;
- no draft or Test entry is consulted for career progression;
- Salsa/Bachata and Leader/Follower history isolation;
- website and profile approval do not overwrite permanent divisions;
- Live/Test registration parity;
- all four publication paths require approval before official result and point history is created.

## Database and deployment

- Schema migration: none.
- Existing published participant results and point transactions remain the source of truth.
- Staging runtime validation: **not tested from Codex**.
- Production: **blocked until the exact develop commit passes Staging runtime validation**.
