#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
p=Path('.github/scripts/release-dev679-role-yes-settings.sh')
s=p.read_text()
old="assert 'leader_yes_count' in a and 'follower_yes_count' in a and 'special_role_settings_lock' in a"
new="assert 'special_role_settings_lock' in a and 'RoleYesConfigurationService::recommended' in a and \"['_leader_impossible_marker']\" not in a"
if old not in s: raise SystemExit('dev679 assertion anchor missing')
p.write_text(s.replace(old,new,1))
PY
bash .github/scripts/release-dev679-role-yes-settings.sh
