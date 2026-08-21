# BDC 2.3.3-dev310 · Build 3016

## Remove iframe navigation overlay

- Prevents the universal **Back / Dashboard** navigation from being injected into iframe documents.
- Removes the floating buttons from embedded **Judge Live Links** and embedded Automatic Final judge controls.
- Keeps normal navigation when **Open Full Judge Control** opens the same control as a standalone page.
- Applies centrally to both Live and Test embedded scoring surfaces.

## Parity Gate

- **Testing:** shared iframe guard applies to isolated Test scoring and nested Test judge controls.
- **Live:** embedded Judge Live Links no longer receive the floating universal navigation.
- **Projector:** embedded projector/control surfaces receive the same iframe-safe behavior; standalone projector controls remain unchanged.
- Candidate static validation completed; Staging runtime confirmation remains pending.

## Migration and deployment

- Database migration: none.
- Data impact: none.
- Deployment: source candidate only; no Staging or Production deployment performed.
