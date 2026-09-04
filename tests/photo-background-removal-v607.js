const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const service = fs.readFileSync(path.join(root, 'app/Services/PhotoBackgroundRemovalService.php'), 'utf8');
const page = fs.readFileSync(path.join(root, 'admin/competitors/photo-adjust.php'), 'utf8');
const config = fs.readFileSync(path.join(root, 'config/config.example.php'), 'utf8');

for (const marker of [
  "getenv('BDC_REMOVE_BG_API_KEY')",
  "'https://api.remove.bg/v1.0/removebg'",
  "'format' => 'png'",
  "str_starts_with($result, "\\x89PNG\\r\\n\\x1a\\n")",
  "Only locally stored portal photos can be processed.",
]) assert(service.includes(marker), 'missing service safeguard: ' + marker);

for (const marker of [
  "Auth::isSuperAdmin()",
  "value="remove_background"",
  "value="apply_background_removal"",
  "value="discard_background_preview"",
  "value="restore_original"",
  "competitor_background_preview_created",
  "competitor_background_removed",
  "competitor_photo_original_restored",
  "Background removed for preview.",
]) assert(page.includes(marker), 'missing protected workflow marker: ' + marker);

assert(page.includes("original_photo_url=COALESCE(original_photo_url,:source)"));
assert(page.includes("repeating-conic-gradient"));
assert(config.includes("'remove_bg_api_key_file'"));
assert(!service.includes('REMOVE_BG_API_KEY='));
assert(!config.match(/remove_bg_api_key['"]?\s*=>\s*['"][A-Za-z0-9_-]{16}/));

console.log('Protected background removal workflow regression checks passed.');
