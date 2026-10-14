# Admin phone preview

A hosted page that shows the real Admin pages as they look on a phone, with made-up accounts (Ada Admin, three teachers waiting for
approval, one rejected, two active, four parents, a short activity log and the services' last check). Nothing in it is a real
person. It is not part of the app and is not deployed with it.

How it is made (from the project folder):

1. Save the pages. This builds the sample accounts in a throwaway in-memory database, renders each Admin page from the real
   templates and writes it as HTML:

       ADMIN_SHOTS_DIR=<an empty folder> php vendor/bin/phpunit tools/admin-phone-preview/CaptureAdminPhoneTest.php

2. Make them self-contained (styles, script, icons and the logo inlined; links report to the viewer instead of leaving the page; a
   button that would change something shows "preview only"):

       python tools/admin-phone-preview/build_admin_preview.py <that folder> <an output folder>

3. Publish `<output folder>/TaraBasa-Admin-Phone-Preview.html` as an Artifact with every file in `<output folder>/screens/` listed as
   a supporting file (9 files). The viewer draws each screen in a frame inside a phone with a browser bar.

When an Admin template, style or script changes, run steps 1 to 3 again and republish to the same address. Every page, and every
window on it, is checked at 360x640, 390x844, 430x932 and 768x1024 (and 1280x800 to compare) for sideways scroll, clipped text and
tap targets under 44px (hidden screen-reader labels are not counted).
