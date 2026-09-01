#!/usr/bin/env node
'use strict';
const fs=require('fs'),assert=require('assert');
const page=fs.readFileSync('admin/competitors/index.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));

assert(page.includes("if ((string)(\$_GET['export'] ?? '') === 'csv')"),'active CSV export branch missing');
assert(page.includes('Auth::requireSuperAdmin();'),'export is not Super Admin protected');
assert(page.includes('$baseListSql . " ORDER BY {$orderBy} {$orderSql}, c.id ASC"'),'export does not share the filtered list query and sorting');
assert(!page.match(/\$exportStmt[^;]*LIMIT/s),'export must not be limited to the visible page');
for(const column of ['Exact Name','Email','Phone','Instagram','Country','Total Points','Status','Created At','Updated At']){
  assert(page.includes("'"+column+"'"),column+' export column missing');
}
for(const fragment of ["['bachata','salsa']","$label.' Role'","$label.' Division'","$label.' Points'"]){
  assert(page.includes(fragment),'dynamic discipline export columns missing: '+fragment);
}
for(const safety of ['text/csv; charset=UTF-8','X-Content-Type-Options: nosniff','competitors_exported',"preg_match('/^[=+\\-@]/u",'\\xEF\\xBB\\xBF']){
  assert(page.includes(safety),safety+' export safety missing');
}
assert(page.includes("Auth::isSuperAdmin()): ?><a class=\"btn btn-outline-success\""),'export button is visible outside Super Admin guard');
assert(page.includes("queryUrl(['export' => 'csv', 'page' => null])"),'export button does not preserve active filters');
const dev=Number(String(version.version).match(/^2\.3\.3-dev(\d+)$/)?.[1]||0);
assert(dev>=411&&version.build>=3117,'VERSION.json predates dev411 build 3117');
console.log('PASS Super Admin filtered competitor CSV export v411');
