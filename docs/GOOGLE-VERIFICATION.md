# Google OAuth setup and verification

Because customers from any Google account will connect their calendars, the OAuth app must be
**External** and **In production**, and because `calendar.events` is a *sensitive* scope, Google
requires **app verification** before more than 100 users can connect and before the
"Google hasn't verified this app" warning disappears. Verification is free; expect 3–10 business
days after submission, sometimes longer with follow-up questions.

## 1. Cloud project (signed in as bill@backofficedesigns.com)
- Use the existing `backofficedesigns` project (Calendar API is already enabled).
- **OAuth consent screen → Audience:** External. **Publishing status:** In production
  (do not leave it in Testing — Testing tokens expire every 7 days).
- **Branding:** App name "BOD Bookings" (must match the site), support email, logo (120×120 PNG),
  Application home page `https://book.backofficedesigns.com`,
  Privacy policy `https://book.backofficedesigns.com/privacy`,
  Terms `https://book.backofficedesigns.com/terms`,
  Authorized domain `backofficedesigns.com`.
- **Data access → scopes:** add exactly these, nothing more:
  `openid`, `.../auth/userinfo.email`, `.../auth/userinfo.profile`,
  `https://www.googleapis.com/auth/calendar.events`, `https://www.googleapis.com/auth/calendar.readonly`.
  For each sensitive scope, the justification field: e.g.
  *calendar.readonly — "Read free/busy times from the calendars the user selects so the booking page only offers open slots. We do not display or store other event details."*
  *calendar.events — "Create, update and delete the calendar events that correspond to bookings guests make on the user's public booking page, including attendee and Google Meet details."*
- **Credentials → OAuth client (Web):** Authorized redirect URIs
  `https://book.backofficedesigns.com/auth/callback` (and `http://book.local/auth/callback` for dev).

## 2. Domain verification
Verify `backofficedesigns.com` in Google Search Console (DNS TXT record) with the same account.

## 3. What the reviewer checks
- Home page on the verified domain explains what the app does and links to the privacy policy.
- Privacy policy mentions Google user data and the **Limited Use** disclosure (already in `/privacy`).
- A short **YouTube video (unlisted is fine)** showing: the sign-in button → Google consent screen with the
  app name and each requested scope visible → the app using calendar data (e.g. booking page showing slots,
  a booking appearing on the calendar). Narration/captions in English.
- The consent screen's app name matches the site.

## 4. Submit
Consent screen → "Prepare for verification" → fill the form, paste the video link, submit.
Reply promptly to reviewer emails; they often ask for a clarified scope justification or a re-recorded video.

## Until verified
- Up to 100 users can connect; each sees an "unverified app" warning with an *Advanced → continue* link.
- That's fine for beta customers you've talked to; not fine for public sign-ups.

## Not needed
- Security assessment (CASA) — only for *restricted* scopes (Gmail, Drive full access). Calendar scopes are sensitive, not restricted.
