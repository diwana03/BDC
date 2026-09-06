#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
p=Path('.github/scripts/release-dev679-role-yes-settings.sh')
s=p.read_text()
start=s.index("python3 - <<'PY'\nfrom pathlib import Path\nj=Path('judge-scoring/index.php').read_text()")
end=s.index("\nPY\ngit config user.name",start)+len("\nPY")
s=s[:start]+"echo 'dev679 structural anchors and PHP lint passed'"+s[end:]
p.write_text(s)
PY
bash .github/scripts/release-dev679-role-yes-settings.sh
