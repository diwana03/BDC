const fs=require('fs'),assert=require('assert');
for(const file of ['admin/scoring-tests/publish.php','admin/scoring/publish.php']) {
 const src=fs.readFileSync(file,'utf8');
 const start=src.indexOf('function refreshPublishedArchivedHtml(');
 const lookup=src.indexOf('PublicationArchiveLookup::recover($pdo,$roundId,$publicationId,$documentTable,$documents)',start);
 const backup=src.indexOf("$backupDirectory=",start);
 assert(lookup>start && lookup<backup,'lookup must run inside active refresh before backups or replacements');
}
const src=fs.readFileSync('app/Services/PublicationArchiveLookup.php','utf8');
for(const guard of ["a.action='salsa_special_approved'","p.id=:publication_id","a.round_id=:round_id","event_id=:event_id","document_category=:category","status='published'","source='scoring_engine'",'bdc_test_','Conflicting']) assert(src.includes(guard),guard);
assert(!/\b(INSERT|UPDATE|DELETE)\b/.test(src),'lookup must be read only');
console.log('Publication archive lookup integration v748 passed (PHP runtime test separate)');
