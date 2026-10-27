# NUVIKOZ branding audit

Baseline e8bf419; feature branch feat/nuvikoz-branding. Existing uncommitted mobile work preserved in separate worktree. Actual customer portal: apps/nursery-web. Staff Flutter app also exists (apps/nursery_admin_mobile).

| File/component | Current | Proposed | Risk | Action |
|---|---|---|---|---|
| Customer Android label, Flutter title/header/auth/splash/support | GreenLeaf | NUVIKOZ | Low | Must change public strings; preserve applicationId and classes |
| Customer web layout/header/footer/page metadata/payment fallback | GreenLeaf Nursery | NUVIKOZ | Low | Central brand module, safe strings and SEO metadata |
| Admin login/sidebar/topbar/metadata | GreenLeaf Nursery Admin | NUVIKOZ Admin | Low | Central brand module and public strings |
| API root/email/payment defaults/config | GreenLeaf/Laravel | NUVIKOZ | Medium | Source configuration defaults/templates only; preserve response structure and records |
| Env example display names | GreenLeaf | NUVIKOZ | Low | Update templates; deployed environment overrides require separate application |
| Extra staff Flutter app (outside four requested targets) | GreenLeaf Admin/Operations | NUVIKOZ Admin | Medium | Discovered; separate follow-up, not modified in this patch |
| Launcher, native splash, favicon, leaf artwork | Old leaf artwork | Approved NUVIKOZ icon | Brand identity | Needs approved source: none identifiable in repo; preserve existing artwork pending user input |
| GreenLeaf class/function identifiers, storage keys, routes, package names, product brand records | Technical/operational identifiers | Unchanged | High if blindly replaced | Preserve |
| Historical reports, mock fixtures, product seed data | Historical/sample content | Unchanged | Avoid unrelated edits | Preserve except assertions for changed public copy |

## Design specification
Public name NUVIKOZ. Primary tagline “Grow Green. Live Better.”; secondary “Your Green Shopping Destination.” on authentication/intro only. Preserve existing botanical theme and artwork until approved icon is supplied. No new color theme needed for this copy/metadata patch; existing readable green/cream tokens remain. No layout/sizing change to assets.

No database migrations, seeded data updates, Android applicationId changes, DNS/payment/signing changes or secret values permitted in this patch. Production .env values can override repository defaults; source merge alone does not update Laravel deployment or cached config.

## Implementation and validation checkpoint
Implemented public text, customer Android display label, splash/auth copy, shared brand modules, root/social metadata, administrative identity, API root/public emails/payment display defaults. Preserved all icon artwork, class names (GreenLeafBackButton etc.), storage IDs, applicationId, routes, product records and database schema. Existing Next.js metadata guides read from installed matching Next.js package. Local customer web `npm ci --ignore-scripts` and `npm run typecheck` passed. Full CI pending.

## Blocked asset work / deployment
No approved NUVIKOZ source logo found. The legacy leaf and default favicon must be replaced only after approved source supplied; no new logo invented. Social preview image also awaits the approved asset. Thus this PR is a reviewable text/configuration phase, not a complete production brand transformation. Do not deploy partial brand identity automatically. Source env templates updated; actual Hostinger/Laravel APP_NAME/MAIL_FROM_NAME and cached config require deployment access. No .env secrets read or changed. No Hostinger deployment or mobile store publication performed.

## Approved asset integration (27 October 2026)
User supplied NUVIKOZ_Final_Logo_Asset_Pack(1).zip. Its README identifies the selected finalized artwork; raster PNG/WebP/JPG only. Core icon and colors copied unchanged. Integrated: website header icon/wordmark, footer horizontal artwork, admin login/sidebar, browser favicons and Apple touch icons, OpenGraph/Twitter images, Flutter header/login/brand mark and splash, Android legacy density icons and safely inset adaptive foreground, native splash, API favicon. No new logo invented or SVG conversion claimed.

Removed the old seedling Lottie from the customer startup widget. Default display delay is zero; bounded timers remain for explicitly configured durations and tests. Existing installation ID/signing configuration retained. Legacy leaf generator and historical files are internal references, not current runtime assets.

Asset blocker resolved by this upload; earlier blocked notes record the prior checkpoint. Full CI and post-deployment verification pending on this update. Staff Flutter app remains outside the four requested applications.
