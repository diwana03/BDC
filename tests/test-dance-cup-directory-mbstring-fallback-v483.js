const fs=require('fs'),assert=require('assert');
const endpoint=fs.readFileSync('admin/dance-cup/directory-search.php','utf8');
const client=fs.readFileSync('public/js/dance-cup-directory.js','utf8');
assert(endpoint.includes("function_exists('mb_strlen')"),'Directory search must tolerate hosts without mbstring');
assert(endpoint.includes("strlen($term)"),'Directory search must provide a core PHP fallback');
assert(endpoint.includes("JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES"),'Directory failures must remain valid JSON');
assert(endpoint.includes("error_log('Dance Cup directory search failed:"),'Server errors must be diagnosable without exposing details');
assert(client.includes("data.items")&&client.includes("directory-search.php"),'Contestant and judge type-ahead must retain the shared endpoint');
console.log('Dance Cup directory mbstring fallback v483 passed.');
