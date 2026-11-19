"""
Makes Tara's voice clips for Sound Hunt and for Word Train's letters, once, with a text to speech voice, and saves them in public/audio/sound-hunt so
the games sound the same on every phone and computer (they play these clips when they exist and fall back to the device's own voice when they do not).

The 26 letter clips (l-a ... l-z) are the real fix for "A sounds like I": a device voice is given a spelling of the letter's name and can read it
wrongly, but a clip made here says the letter itself. They are made with explicit instructions, not a spelling:
  --letters spell     (default) the voice is told to spell the capital letter out:  <say-as interpret-as="characters">A</say-as>
  --letters phoneme   the voice is told the sounds (International Phonetic Alphabet, from config/voice.php):  <phoneme alphabet="ipa" ph="eɪ">A</phoneme>
  --letters-voice     say the letters with another voice, for example en-US-JennyNeural, if the main voice says a letter oddly
Whichever is used, LISTEN to the sample (A and I first) before making the rest. Which of these a given voice accepts has not been tested here.

What it needs, and where the key lives
  An Azure Speech key and its region, from the owner's own Azure account. They are read from two environment variables and are never written to a
  file, printed, or sent anywhere except to Microsoft's speech address:
      PowerShell:   $env:AZURE_SPEECH_KEY = "paste the key here"
                    $env:AZURE_SPEECH_REGION = "southeastasia"          (the region the key was made in)
  Close the window afterwards (or run  Remove-Item Env:AZURE_SPEECH_KEY ) so the key is gone. Never paste the key into a chat or a commit.

What it does
  1. Asks the app which clips are wanted and which are missing:  php artisan sound-hunt:clips --missing --json
  2. For each missing clip, asks the voice (default en-PH-RosaNeural: a Philippine English woman) to say its words, slowly and clearly,
     and saves the MP3 as public/audio/sound-hunt/<key>.mp3.
  3. Skips clips that already exist, so it can be run again after a failure. --force makes them all again.

Try it first without a key:   python tools/make-voice-clips.py --dry-run
Try 11 clips to listen to:    python tools/make-voice-clips.py --sample          (8 words and phrases, and the letters A, I and E)
Make everything:              python tools/make-voice-clips.py

BEFORE shipping the clips, read Microsoft's terms for the output audio of the Speech service and confirm that audio made with a neural voice may be
included in an app that is given to children. This script does not decide that for you. Listen to every clip too: a voice can say a word oddly.

Written with the standard library only (no packages to install). It has not been run against Azure yet: the first run is the test.
"""
import argparse
import json
import os
import subprocess
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path
from xml.sax.saxutils import escape

ROOT = Path(__file__).resolve().parent.parent
SAMPLE_KEYS = ['w-cat', 'w-hat', 'w-pig', 'w-big', 'w-sock', 'w-pet', 'ask-rhyme', 'again', 'l-a', 'l-i', 'l-e']


def wanted(missing_only):
    """Ask the app for the list of clips (key and the words to say)."""
    args = ['php', 'artisan', 'sound-hunt:clips', '--json'] + (['--missing'] if missing_only else [])
    out = subprocess.run(args, cwd=ROOT, capture_output=True, text=True, encoding='utf-8')
    if out.returncode != 0:
        sys.exit('Could not ask the app for the clip list:\n' + (out.stderr or out.stdout))
    return json.loads(out.stdout)


def ssml(text, voice, rate, letter_ipa=None, letters_mode='spell'):
    """
    One short line for the voice, a little slower than normal so a child can hear every sound.
    `letter_ipa` is given only for a letter clip: the letter is then said as a letter (spelled out, or from its sounds), never read as a word.
    """
    lang = '-'.join(voice.split('-')[:2])
    if letter_ipa is None:
        inner = escape(text)
    elif letters_mode == 'phoneme':
        inner = f"<phoneme alphabet='ipa' ph='{escape(letter_ipa)}'>{escape(text)}</phoneme>"
    else:
        inner = f"<say-as interpret-as='characters'>{escape(text)}</say-as>"
    return (
        f"<speak version='1.0' xml:lang='{lang}'><voice name='{voice}'>"
        f"<prosody rate='{rate}'>{inner}</prosody></voice></speak>"
    )


def synthesize(body, key, region, tries=4):
    url = f'https://{region}.tts.speech.microsoft.com/cognitiveservices/v1'
    headers = {
        'Ocp-Apim-Subscription-Key': key,
        'Content-Type': 'application/ssml+xml',
        'X-Microsoft-OutputFormat': 'audio-24khz-48kbitrate-mono-mp3',
        'User-Agent': 'tarabasa-voice-clips',
    }
    for attempt in range(1, tries + 1):
        req = urllib.request.Request(url, data=body.encode('utf-8'), headers=headers, method='POST')
        try:
            with urllib.request.urlopen(req, timeout=40) as res:
                return res.read()
        except urllib.error.HTTPError as e:
            if e.code in (401, 403):
                sys.exit(f'The speech service refused the key (HTTP {e.code}). Check AZURE_SPEECH_KEY and that AZURE_SPEECH_REGION is the region the key was made in.')
            if e.code == 429 and attempt < tries:
                time.sleep(5 * attempt)
                continue
            if attempt == tries:
                sys.exit(f'The speech service answered HTTP {e.code} and would not make the clip.')
            time.sleep(2 * attempt)
        except (urllib.error.URLError, TimeoutError):
            if attempt == tries:
                sys.exit('Could not reach the speech service. Check the internet connection and AZURE_SPEECH_REGION.')
            time.sleep(2 * attempt)
    return b''


def main():
    ap = argparse.ArgumentParser(description="Make Sound Hunt's voice clips with a text to speech voice.")
    ap.add_argument('--dry-run', action='store_true', help='only list what would be made; needs no key')
    ap.add_argument('--sample', action='store_true', help='make only 11 clips to listen to first (including the letters A, I and E)')
    ap.add_argument('--force', action='store_true', help='make clips that already exist again')
    ap.add_argument('--voice', help='the voice name (default: the one in config/sound_hunt.php)')
    ap.add_argument('--letters', choices=['spell', 'phoneme'], default='spell', help='how a letter clip is made: spelled out (default) or from its sounds')
    ap.add_argument('--letters-voice', help='a different voice for the 26 letter clips only (default: the same voice)')
    ap.add_argument('--rate', default='-12%', help="speaking speed, for example -12%% (default) or 0%%")
    args = ap.parse_args()

    info = wanted(missing_only=not args.force)
    voice = args.voice or info['voice']
    letters_voice = args.letters_voice or voice
    letter_ipa = info.get('letters', {})
    folder = ROOT / 'public' / info['directory']
    ext = info['extension']
    clips = info['clips']
    if args.sample:
        clips = {k: v for k, v in clips.items() if k in SAMPLE_KEYS}

    print(f"{len(clips)} clip(s) to make with {voice}, into {folder}")
    if args.dry_run:
        for k, v in clips.items():
            print(f'  {k:14s} {v}')
        return
    if not clips:
        print('Nothing to make: every clip already exists.')
        return

    key = os.environ.get('AZURE_SPEECH_KEY', '').strip()
    region = os.environ.get('AZURE_SPEECH_REGION', '').strip()
    if not key or not region:
        sys.exit('Set AZURE_SPEECH_KEY and AZURE_SPEECH_REGION first (see the top of this file). Nothing was made.')

    folder.mkdir(parents=True, exist_ok=True)
    made = 0
    for k, text in clips.items():
        if k in letter_ipa:
            body = ssml(text, letters_voice, args.rate, letter_ipa[k], args.letters)
        else:
            body = ssml(text, voice, args.rate)
        audio = synthesize(body, key, region)
        if len(audio) < 800:
            sys.exit(f'The clip for "{k}" came back empty. Stopping so a bad file is not saved.')
        (folder / f'{k}.{ext}').write_bytes(audio)
        made += 1
        print(f'  made {k}.{ext}  ({len(audio) // 1024} KB)  {text}')
        time.sleep(0.15)
    print(f'Done: {made} clip(s). Listen to them, then check them in with the game.')


if __name__ == '__main__':
    main()
