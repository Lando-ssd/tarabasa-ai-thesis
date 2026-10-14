# Parent phone preview

A hosted page that shows the real Parent pages as they look on a phone, with a made-up family (Carla Domingo and her children
Maria, Miguel and Ana). Nothing in it is a real family. It is not part of the app and is not deployed with it.

How it is made (from the project folder):

1. Save the pages. This builds the sample family in a throwaway in-memory database, renders each Parent page from the real
   templates and writes it as HTML:

       PARENT_SHOTS_DIR=<an empty folder> php vendor/bin/phpunit tools/parent-phone-preview/CaptureParentPhoneTest.php

2. Make them self-contained (styles, icons and the logo inlined; links and buttons report to the viewer instead of leaving the page):

       python tools/parent-phone-preview/build_parent_preview.py <that folder> <an output folder>

3. Publish `<output folder>/TaraBasa-Parent-Phone-Preview.html` as an Artifact with every file in `<output folder>/screens/`
   listed as a supporting file (18 files, about 2 MB). The viewer draws each screen in a frame inside a phone with a browser bar.

When a Parent template or style changes, run steps 1 to 3 again and republish to the same address. Every page is checked at
360x640, 390x844, 430x932, 768x1024 and 1280x800 for sideways scroll, clipped text and tap targets under 44px.
