# Legal pages preview

A hosted page that shows the new Privacy, Terms, Cookie and Refund pages, the Parent and Guardian Consent notice, and the screens that
now ask people to agree (parent and teacher sign up, the last step of Add a child with and without a photo, linking to a child), plus
the pages that changed (home page, log in, children, profiles, the Generate window). The people in it (Carla Domingo, her child Maria,
the teacher Elena Reyes) are made up. It is not part of the app and is not deployed with it.

How it is made (from the project folder):

1. Save the pages. This builds the sample people in a throwaway in-memory database, renders each page from the real templates and
   writes it as HTML:

       LEGAL_SHOTS_DIR=<an empty folder> php vendor/bin/phpunit tools/legal-preview/CaptureLegalPreviewTest.php

2. Make them self-contained (styles, fonts, icons and the logo inlined; links and buttons report to the viewer instead of leaving the
   page; the add a child page opens on its last step and the Generate window opens by itself):

       python tools/legal-preview/build_legal_preview.py <that folder> <an output folder>

3. Publish `<output folder>/TaraBasa-Legal-Preview.html` as an Artifact with every file in `<output folder>/screens/` listed as a
   supporting file (17 files, about 7 MB). The output folder must be inside the working folder or the session scratchpad for the
   Artifact tool to read it.

When a legal page, a consent box or a screen listed in the viewer changes, run steps 1 to 3 again and republish to the same address.
Not drawn: the phone app's own consent boxes and the home page's small animations (the preview does not load the animation library).
