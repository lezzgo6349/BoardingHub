# BoardingHub — Boarding house rent and reservation system

PHP + MySQL (XAMPP) + vanilla JavaScript + Bootstrap 5. No build step, no Composer.

## Install (XAMPP)
1. Copy this folder to `C:\xampp\htdocs\boardinghub` (keep the folder name, or change `BASE_URL` in `config.php`).
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open phpMyAdmin → **Import** → choose `boardinghub.sql` → Go. (It creates the `boardinghub` database.)
4. Open `http://localhost/boardinghub/`.
5. Click the small **shield icon** (top right) → **Register admin**. The first admin needs no code. Later admins need `ADMIN_INVITE_CODE` from `config.php` (change it).

## Account flow
- **Admin**: registers first via the shield icon, then signs in there.
- **Tenant**: registers on the home page, signs in, browses and reserves.
- **Owner**: a tenant opens *Become an owner* (profile menu or dashboard), uploads a National ID and proof of ownership. After an admin approves it, the **Owner dashboard** unlocks. The ID images are stored in `uploads/private` and only the admin (and the applicant) can view them.

## Reservation and rent flow
1. Tenant browses → picks a room with a free bed → chooses a move-in date (max 14 days ahead) → bed becomes **Reserved**.
2. Owner accepts (bed becomes **Occupied**) or declines (bed becomes **Available**). Tenant is notified. Tenant can cancel while unpaid.
3. After acceptance the tenant pays online (GCash, PayMaya or card) for the price and days the owner set on the room. Receipt + rules/contract + next payment date are shown, can be printed / downloaded as PDF, and are emailed.
4. Refunds: before the move-in date the tenant can request a refund; the owner approves or declines.
5. **Rent a tenant** (owner): enter tenant details, email, amount paid, days of stay → the receipt, rules/contract and next payment date are emailed automatically. **Tenants** page lets the owner record later payments, resend receipts and end stays.

## Integrations (all optional to start)
| Feature | What is used | How to enable |
|---|---|---|
| Database | XAMPP MySQL | import `boardinghub.sql` |
| Map / location | Leaflet + OpenStreetMap + Nominatim search (alternative to Google Maps, no API key) | works out of the box |
| Payments | Online checkout: GCash, PayMaya, card (powered by PayMongo behind the scenes) | put your secret key in `PAYMONGO_SECRET_KEY` (config.php). While empty, the checkout screen completes the payment in **demo mode** (no real money) so you can test the full flow without real money. |
| Receipt / contract PDF | Print-ready page + html2pdf.js (browser-side PDF generator) | works out of the box |
| Email | Built-in SMTP client | fill `SMTP_*` and `MAIL_FROM` in config.php. Until then every email is saved in **Admin → Email log**. |

Important: when you use a real PayMongo key, do not leave `PAYMONGO_SECRET_KEY` empty on a live site, because the demo checkout only exists while it is empty.

## Folders
- `index.php` tenant sign in / register · `admin/login.php` admin
- `tenant/` browse, house details + reserve, reservations, payments, messages, become owner
- `owner/` overview, boarding houses + rooms, reservations, rent, tenants, finance, messages, profile
- `admin/` overview, owner requests, accounts, reports/inquiries, email log
- `includes/` shared code · `assets/` CSS, JS, logo · `uploads/` images
