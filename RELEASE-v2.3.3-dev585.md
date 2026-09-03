# BDC v2.3.3-dev585

## Test scoring source recovery

- Restores the complete Test Jack and Jill dashboard from the last intact source.
- Removes literal tool truncation output that had replaced 739 workflow lines and caused a PHP parse error.
- Reapplies the Super Admin archived-round filter, count, Archive action and Restore action without removing existing scoring behavior.
- Adds a regression gate that rejects truncated source files and checks critical roster, judge, score, callback, Final, report and archive paths.

## Validation

- Candidate must pass the complete GitHub PHP 8.1 lint and JavaScript regression workflow before merge.
- Staging runtime verification remains required before Production promotion.
- No database migration and no data mutation.
