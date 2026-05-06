# Rielcode Testimonials Subdomain

Submit-only form for client testimonials. Targeted-link entry (sent via WhatsApp post-delivery). Manual approval in main site admin panel.

## Files

- `index.php` — form page (11 fields)
- `submit.php` — POST handler: validation, DB insert (status=pending), admin email, redirect
- `thank-you.php` — confirmation page
- `connection.php` — DB (reuses main site config)
- `CSS/testimonial-form.css` — dark gradient theme matching rielcode.com brand
- `IMG/logo.png`, `IMG/favicon.png` — copied from main site
- `sql/testimonials.sql` — schema migration (run once)

## Local Setup

1. Start XAMPP (Apache + MySQL).
2. Run migration:
   ```
   "C:/xampp/mysql/bin/mysql.exe" -u root rielcode < sql/testimonials.sql
   ```
3. Visit: `http://localhost/Rielcode-testimonials/`
4. Admin review: `http://localhost/Rielcode/admin.php?table=testimonials`

## Production Deploy (cPanel)

1. cPanel → Subdomains → create `testimonials.rielcode.com` pointing to `/public_html/testimonials/`
2. Upload all files except `sql/` and `README.md` to that folder
3. phpMyAdmin → run `sql/testimonials.sql` against `rier5192_rielcode`
4. Test full flow with one fake submission, then verify it lands in admin panel pending list

## Form Fields (Final)

Required:
1. Client name (max 80)
2. Role / title (max 80)
3. Business / company name (max 100)
4. Star rating (1–5)
5. Project URL (validated)
6. Problem before (50–300 chars)
7. Solution / what was built (100–500 chars)
8. Recommendation (50–300 chars)
9. Consent checkbox

Optional:
10. Headline (max 120)
11. Email (never displayed, follow-up only)

## Spam Prevention

- CSRF token (per-session)
- Honeypot field (`name="website"`)
- Time-based check (form must be open >3 sec before submit)
- No reCAPTCHA — entry is targeted-link only

## Admin Workflow

1. New submission → email notification to `afw1407@gmail.com`
2. Admin → `Testimonials` tab → pending row at top
3. Click `Full` to expand quote
4. Approve / Reject / Delete

## Out of Scope (Next Phase)

- Public display surfaces (homepage section, dedicated wall page)
- Photo / logo upload
- Video testimonials
- Public open submissions
