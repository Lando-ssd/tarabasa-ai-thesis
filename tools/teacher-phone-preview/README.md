# Teacher phone preview

A hosted page that shows the real Teacher pages as they look on a phone, with a made-up class (Ms Elena Reyes, who teaches
Sampaguita in Grade 1 and Dahlia in Grade 2, plus a second teacher who is still waiting for approval). Nothing in it is a real
class, learner or reading. It is not part of the app and is not deployed with it.

How it is made (from the project folder):

1. Save the pages. This builds the sample class in a throwaway in-memory database, renders each Teacher page from the real
   templates and writes it as HTML (it also saves the window of every activity, which the page fetches when a card opens):

       TEACHER_SHOTS_DIR=<an empty folder> php vendor/bin/phpunit tools/teacher-phone-preview/CaptureTeacherPhoneTest.php

2. Make them self-contained (styles, script, icons and the logo inlined; links report to the viewer instead of leaving the page;
   a button that would save something shows "preview only"; opening an activity's window works):

       python tools/teacher-phone-preview/build_teacher_preview.py <that folder> <an output folder>

3. Publish `<output folder>/TaraBasa-Teacher-Phone-Preview.html` as an Artifact with every file in `<output folder>/screens/`
   listed as a supporting file (34 files, about 4 MB). The viewer draws each screen in a frame inside a phone with a browser bar.

When a Teacher template, style or script changes, run steps 1 to 3 again and republish to the same address. Every page, and every
view of every window, is checked at 360x640, 390x844, 430x932, 768x1024 and 1280x800 for sideways scroll, clipped text and tap
targets under 44px (hidden screen-reader labels are not counted).

Drag and drop between levels needs a mouse, so on a phone the "Move to" menu on each card is the way; the preview says so.
