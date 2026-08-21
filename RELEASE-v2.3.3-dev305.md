# BDC 2.3.3-dev305 · Build 3011

## Projection name clarity

- Uses compact first names throughout every shared Test and Live audience projection.
- Automatically adds the last-name initial when the current projection contains duplicate first names, such as `Ashish D` and `Ashish S`.
- Applies the rule to competitor cards, callbacks, Flights, Final couples, matching, matrices, podiums, results and projected judges.
- Leaves full names unchanged in the database, scoring dashboards, judge forms and administration screens.

## Verification

- Covers single names, ordinary full names, duplicate first names and Unicode-safe initials.
- Uses the same shared projection naming service in Test and Live modes.

## Parity Gate

- **Testing:** Test projection tokens use the shared collision-safe display-name formatter.
- **Live:** Live projection tokens use the same formatter and retain full database names.
- **Projector:** shared feed and standalone Final placement projection both apply the compact-name rule.
- Static parity checks completed; staging runtime confirmation remains pending in Release Manager.
