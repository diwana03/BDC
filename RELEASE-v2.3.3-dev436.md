# BDC 2.3.3-dev436

## Outcome

- Replaces event selection on the public Dance Cup registration form with a reusable competitor profile.
- Captures competitor gender separately from scoring-category gender eligibility.
- Captures one or more Dance Cup styles, formats and levels, including Solo, Couple, Pro-Am and Team.
- Adds Mixed Gender, Female Only and Male Only to Dance Cup category creation in both isolated Testing and Live tables.
- Validates linked BDC competitors against approved style, format, level and category-gender eligibility in both Manual and Automatic roster assignment.
- Replaces the Automatic judge-tab presentation with the complete read-only competitor × judge × criterion matrix used by Manual scoring.
- Retains the existing two-second Automatic score refresh, live judge totals and calculated placement updates.

## Data model

- Competitor gender belongs to the registration identity.
- Event and gender eligibility belong to the scoring category.
- Registration never changes Novice, Intermediate or Advanced progression.
- Forward migration `20260827_0200_reusable_dance_cup_profiles` preserves prior request evidence and defaults existing event categories to Mixed Gender.

## Parity Gate

- Testing Dance Cup category schema: statically verified.
- Live Dance Cup category schema: statically verified through the shared migration and scoring service.
- Automatic shared workspace: statically verified with full criterion matrix and two-second live refresh.
- Manual scoring values, calculation rules and projector output are unchanged.
- Staging browser and PHP runtime validation: not runtime-tested; Production promotion remains blocked until Staging passes.
