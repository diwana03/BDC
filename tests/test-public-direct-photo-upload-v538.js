const fs = require('fs');
const assert = require('assert');

const page = fs.readFileSync('register/index.php', 'utf8');
const adminAdjust = fs.readFileSync('admin/competitors/photo-adjust.php', 'utf8');

for (const removedMarker of [
  'photo_cropped',
  'photoCropped',
  'cropStage',
  'photoZoom',
  'canvas.toDataURL',
  'FileReader'
]) assert(!page.includes(removedMarker), `public crop pipeline remains: ${removedMarker}`);

for (const marker of [
  'enctype="multipart/form-data"',
  'name="photo"',
  'accept="image/jpeg,image/png,image/webp"',
  'Upload the exact original photo. No crop or adjustment is applied.',
  'maximum 15 MB',
  'is_uploaded_file($tmp)',
  'new finfo(FILEINFO_MIME_TYPE)',
  'getimagesize($tmp)',
  '$size>15*1024*1024',
  "mkdir($dir,0755,true)",
  'move_uploaded_file($tmp',
  "'photo_processing'=>'original_unchanged'",
  "'photo'=>$photo"
]) assert(page.includes(marker), `direct original upload marker missing: ${marker}`);

for (const marker of ['cropped_photo_data', 'competitor_photo_adjusted']) {
  assert(adminAdjust.includes(marker), `admin photo adjustment must remain available: ${marker}`);
}

console.log('dev538 public direct original photo upload checks passed');
