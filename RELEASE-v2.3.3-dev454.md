# 2.3.3-dev454

- Adds WhatsApp and Email actions to each Dance Cup Automatic judge row using the linked Judge Database contact details.
- Disables a contact action with a clear explanation when that contact detail is missing.
- Audits Email delivery attempts and WhatsApp opens without changing judge tokens or scoring data.
- Forces the Automatic matrix poll to bypass browser/proxy caches every two seconds and refreshes immediately when the tab becomes visible or focused.
- Aligns the five Dance Cup criteria into equal desktop columns, two tablet columns and one mobile column.
- Applies equally to isolated Test and Live Dance Cup workflows.
- Does not change Jack and Jill, scoring calculations or the database schema.
