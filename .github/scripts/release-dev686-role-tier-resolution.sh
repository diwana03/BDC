#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json
p=Path('app/Services/RoleYesConfigurationService.php')
s=p.read_text()
old='''        self::ensure($pdo);$counts=self::counts($pdo,$roundId);$saved=[];
        $stmt=$pdo->prepare('SELECT dance_role,yes_count FROM bdc_scoring_role_yes_settings WHERE round_id=:round');$stmt->execute(['round'=>$roundId]);
        foreach($stmt->fetchAll() as $row)$saved[(string)$row['dance_role']]=(int)$row['yes_count'];
        $result=[];
        foreach(['leader','follower'] as $role){
            $recommend=self::recommended($counts[$role],$legacyYes);
            $has=array_key_exists($role,$saved);
            // Backward compatibility: old rounds keep their shared yes_count until role settings are explicitly saved.
            $yes=$has?max(1,$saved[$role]):max(1,$legacyYes);
            $result[$role]=['count'=>$counts[$role],'tier'=>$recommend['tier'],'yes'=>$yes,'saved'=>$has];
        }
        return $result;'''
new='''        self::ensure($pdo);$counts=self::counts($pdo,$roundId);$saved=[];
        $stmt=$pdo->prepare('SELECT dance_role,yes_count FROM bdc_scoring_role_yes_settings WHERE round_id=:round');$stmt->execute(['round'=>$roundId]);
        foreach($stmt->fetchAll() as $row)$saved[(string)$row['dance_role']]=(int)$row['yes_count'];
        // Before judging starts, an unsaved role must still follow its real participant-count tier.
        // Once judging has started, preserve the old shared yes_count for legacy rounds so a live event
        // can never change quota underneath judges. Saved role settings always win.
        $startedStmt=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_marks WHERE round_id=:round AND (mark_type<>'blank' OR weighted_score>0)");
        $startedStmt->execute(['round'=>$roundId]);
        $judgingStarted=(int)$startedStmt->fetchColumn()>0;
        $result=[];
        foreach(['leader','follower'] as $role){
            $recommend=self::recommended($counts[$role],$legacyYes);
            $has=array_key_exists($role,$saved);
            $yes=$has
                ? max(1,$saved[$role])
                : ($judgingStarted ? max(1,$legacyYes) : max(1,$recommend['yes']));
            $result[$role]=['count'=>$counts[$role],'tier'=>$recommend['tier'],'yes'=>$yes,'saved'=>$has];
        }
        return $result;'''
if old not in s: raise SystemExit('resolve anchor missing')
s=s.replace(old,new,1)
p.write_text(s)
vp=Path('VERSION.json');data=json.loads(vp.read_text());data['version']='2.3.6-dev686';data['build']=3393;data['release_date']='2026-09-07';feature='Role tier resolution now actually checks each Lead/Follow participant count before judging: unsaved fresh rounds use the correct recommended YES quota (for example 23 Lead = 10, 32 Follow = 15), saved role settings remain authoritative, and already-started legacy rounds keep their existing shared quota to avoid mid-event changes.';data.setdefault('features',[]).insert(0,feature);vp.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l app/Services/RoleYesConfigurationService.php
php -l judge-scoring/index.php
php -l admin/scoring/core.php
python3 <<'PY'
from pathlib import Path
s=Path('app/Services/RoleYesConfigurationService.php').read_text()
assert "if($count>=31)return ['tier'=>3,'yes'=>15]" in s
assert "if($count>=16)return ['tier'=>2,'yes'=>10]" in s
assert '$judgingStarted ? max(1,$legacyYes) : max(1,$recommend[\'yes\'])' in s
# Explicit regression example requested by event data.
def rec(n):
    return 15 if n>=31 else 10 if n>=16 else 5 if n>=5 else max(1,n)
assert rec(23)==10
assert rec(32)==15
print('dev686 role tier resolution assertions passed')
PY
git config user.name 'BDC Release Bot';git config user.email 'actions@users.noreply.github.com';git add app/Services/RoleYesConfigurationService.php VERSION.json;git commit -m 'Release dev686 real role tier resolution';git push origin HEAD:develop
