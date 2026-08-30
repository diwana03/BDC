const fs=require('fs'),assert=require('assert');
const danceCup=fs.readFileSync('admin/dance-cup/projection-feed.php','utf8');
const jackJill=fs.readFileSync('live-display/feed.php','utf8');
const service=fs.readFileSync('app/Services/ProjectionNameService.php','utf8');
assert(danceCup.includes('use App\\Services\\ProjectionNameService;'),'Dance Cup feed must load the shared projection-name service');
for(const marker of [
  "$entries=ProjectionNameService::abbreviateRows($entries,['display_name'])",
  "$results=ProjectionNameService::abbreviateRows($results,['display_name'])",
  "$judges=ProjectionNameService::abbreviateRows($judges,['judge_name'])",
])assert(danceCup.includes(marker),'missing Dance Cup first-name projection path: '+marker);
assert(danceCup.indexOf('$entries=ProjectionNameService::abbreviateRows')<danceCup.indexOf('$active=null'),'active contestant must inherit the abbreviated projection name');
assert(jackJill.includes('$items = ProjectionNameService::abbreviateRows'),'Jack and Jill must retain shared first-name projection behavior');
assert(service.includes("$result[$key] = $first"),'projection service must default to the first name');
assert(service.includes("$result[$key] .= ' ' . self::firstCharacter($last)"),'duplicate first names must retain a surname initial');
console.log('dev507 Dance Cup and Jack and Jill first-name projection checks passed');
