/**
 * Install this script in each Google Form response spreadsheet.
 * Script Properties required:
 *   BDC_SYNC_URL    https://bachatadancecouncil.com/portal/api/form-sync/
 *   BDC_SYNC_SECRET same 32+ character secret as BDC_GOOGLE_FORM_SYNC_SECRET
 *   BDC_FORM_KIND   open or amateur
 */
function onFormSubmit(e) {
  if (!e || !e.range || !e.namedValues) throw new Error('Install this as a spreadsheet On form submit trigger.');
  const props = PropertiesService.getScriptProperties();
  const url = props.getProperty('BDC_SYNC_URL');
  const secret = props.getProperty('BDC_SYNC_SECRET');
  const formKind = (props.getProperty('BDC_FORM_KIND') || '').toLowerCase();
  if (!url || !secret || !['open', 'amateur'].includes(formKind)) throw new Error('BDC sync Script Properties are incomplete.');

  const value = names => {
    for (const name of names) {
      const found = e.namedValues[name];
      if (found && found.length) return String(found[0]).trim();
    }
    return '';
  };
  const category = value(['Please Select The Right Category', 'Lead or Follow', 'Lead or Follow ']);
  const photoUrl = value(['Upload Your HQ Photo for Art Work (No Cartoon, no Facemask Your Clear face Photo)', 'Profile Photo', 'Upload Photo']);
  const styles = [];
  if (/bachata/i.test(category)) styles.push('bachata');
  if (/salsa/i.test(category)) styles.push('salsa');
  const photo = loadDrivePhoto_(photoUrl);
  const sheet = e.range.getSheet();
  const spreadsheet = sheet.getParent();
  const payload = {
    source_system: 'google_forms:' + spreadsheet.getId(),
    source_key: spreadsheet.getId() + ':' + sheet.getSheetId() + ':' + e.range.getRow(),
    source_row: e.range.getRow(),
    form_kind: formKind,
    full_name: value(['FULL NAME', 'Full Name', 'Participant Name']),
    email: value(['Email', 'Email Address']),
    phone: value(['Contact Number', 'Phone', 'Phone Number']),
    instagram: value(['IG handle', 'Instagram', 'Instagram Handle']),
    country: value(['Country / City', 'Country/City', 'Country']),
    role: /follow/i.test(category) ? 'follower' : (/lead/i.test(category) ? 'leader' : ''),
    styles: styles,
    photo_base64: photo.base64,
    photo_mime: photo.mime
  };
  const body = JSON.stringify(payload);
  const signature = Utilities.computeHmacSha256Signature(body, secret)
    .map(byte => ('0' + (byte & 255).toString(16)).slice(-2)).join('');
  const response = UrlFetchApp.fetch(url, {
    method: 'post',
    contentType: 'application/json',
    payload: body,
    headers: {'X-BDC-Signature': signature},
    muteHttpExceptions: true
  });
  const code = response.getResponseCode();
  if (code < 200 || code >= 300) throw new Error('BDC sync failed (' + code + '): ' + response.getContentText());
}

function loadDrivePhoto_(url) {
  if (!url) return {base64: '', mime: ''};
  const match = String(url).match(/[-\w]{20,}/);
  if (!match) throw new Error('Photo link does not contain a Google Drive file ID.');
  const blob = DriveApp.getFileById(match[0]).getBlob();
  if (blob.getBytes().length > 15 * 1024 * 1024) throw new Error('Photo exceeds the 15 MB sync limit.');
  return {base64: Utilities.base64Encode(blob.getBytes()), mime: blob.getContentType()};
}

function installBdcTrigger() {
  const spreadsheet = SpreadsheetApp.getActive();
  ScriptApp.getProjectTriggers().filter(t => t.getHandlerFunction() === 'onFormSubmit').forEach(t => ScriptApp.deleteTrigger(t));
  ScriptApp.newTrigger('onFormSubmit').forSpreadsheet(spreadsheet).onFormSubmit().create();
}

/** Re-send existing response rows safely; BDC idempotency prevents duplicates. */
function syncRowsFrom(firstRow) {
  const sheet = SpreadsheetApp.getActiveSheet();
  const lastRow = sheet.getLastRow();
  const lastColumn = sheet.getLastColumn();
  const headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0];
  for (let row = Math.max(2, Number(firstRow) || 2); row <= lastRow; row++) {
    const values = sheet.getRange(row, 1, 1, lastColumn).getDisplayValues()[0];
    if (!values.some(Boolean)) continue;
    const namedValues = {};
    headers.forEach((header, index) => namedValues[header] = [values[index]]);
    onFormSubmit({range: sheet.getRange(row, 1, 1, lastColumn), namedValues: namedValues});
  }
}
