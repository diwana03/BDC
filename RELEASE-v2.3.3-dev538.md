# v2.3.3-dev538

- Uploads the exact original JPG, PNG or WebP from the public Competitor Portal without browser cropping, canvas conversion, compression or re-encoding.
- Accepts original photos up to 15 MB and reports PHP upload, invalid-image, storage-directory and file-save failures clearly.
- Keeps the existing admin photo-adjustment workflow available so the uploaded original can be positioned later.
- Adds a static regression check covering the direct-upload path and the absence of public crop code.
- No database migration is required.
