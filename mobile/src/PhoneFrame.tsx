import { useEffect, useState } from 'react';

// Developer tool, browser only. On a wide browser window the app is shown inside a phone drawn on the page, so it is
// obvious how big the real screen is. The app itself runs in an iframe of exactly the phone's size, which makes it behave
// as it would on that phone (layout, scrolling, text wrapping). Phones and the phone builds never see this file:
// App.tsx only uses it on the web, on a window wider than 600px, outside an iframe. Add ?frame=off to the address to
// switch it off.

type Preset = { key: string; label: string; w: number; h: number; tablet?: boolean };

// w and h are the whole screen in dp, including the status bar strip.
const PRESETS: Preset[] = [
  { key: 'small', label: 'Small phone', w: 360, h: 640 },
  { key: 'phone', label: 'Common phone', w: 390, h: 844 },
  { key: 'large', label: 'Large phone', w: 412, h: 915 },
  { key: 'tablet', label: 'Tablet', w: 768, h: 1024, tablet: true },
];

const STATUS_BAR = 24; // height of the status bar strip at the top of the screen
const BEZEL = 12; // the dark edge around the screen

function saved(): string {
  try {
    return localStorage.getItem('tarabasa_frame') ?? 'phone';
  } catch {
    return 'phone';
  }
}

export function PhoneFrame() {
  const [key, setKey] = useState(saved);
  const [view, setView] = useState({ w: window.innerWidth, h: window.innerHeight });

  useEffect(() => {
    const onResize = () => setView({ w: window.innerWidth, h: window.innerHeight });
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);

  const preset = PRESETS.find((p) => p.key === key) ?? PRESETS[1];
  const outerW = preset.w + BEZEL * 2;
  const outerH = preset.h + BEZEL * 2;
  // Shrink the drawing (not the app's real size) when the browser window is smaller than the phone.
  const scale = Math.min(1, (view.h - 150) / outerH, (view.w - 32) / outerW);
  const src = `${window.location.pathname}?embed=1${window.location.hash}`;

  function choose(next: string) {
    setKey(next);
    try {
      localStorage.setItem('tarabasa_frame', next);
    } catch {
      /* the choice is just not remembered */
    }
  }

  return (
    <div
      style={{
        minHeight: '100vh',
        background: '#e8eef5',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        padding: '14px 12px 18px',
        boxSizing: 'border-box',
        fontFamily: 'system-ui, -apple-system, Segoe UI, sans-serif',
        color: '#17324d',
      }}
    >
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', justifyContent: 'center' }}>
        {PRESETS.map((p) => (
          <button
            key={p.key}
            onClick={() => choose(p.key)}
            style={{
              cursor: 'pointer',
              border: '2px solid ' + (p.key === preset.key ? '#dd7014' : '#c5d6e8'),
              background: p.key === preset.key ? '#dd7014' : '#ffffff',
              color: p.key === preset.key ? '#ffffff' : '#17324d',
              borderRadius: 999,
              padding: '7px 14px',
              fontSize: 14,
              fontWeight: 700,
            }}
          >
            {p.label} {p.w} x {p.h}
          </button>
        ))}
      </div>

      <div style={{ width: outerW * scale, height: outerH * scale, marginTop: 14 }}>
        <div
          style={{
            width: outerW,
            height: outerH,
            transform: `scale(${scale})`,
            transformOrigin: 'top left',
            background: '#1f2d3b',
            borderRadius: preset.tablet ? 30 : 50,
            padding: BEZEL,
            boxSizing: 'border-box',
            boxShadow: '0 26px 50px -20px rgba(23,50,77,0.6)',
          }}
        >
          <div
            style={{
              width: '100%',
              height: '100%',
              borderRadius: preset.tablet ? 20 : 38,
              overflow: 'hidden',
              background: '#ffffff',
              display: 'flex',
              flexDirection: 'column',
            }}
          >
            <div
              style={{
                height: STATUS_BAR,
                flex: 'none',
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                padding: '0 18px',
                fontSize: 11,
                fontWeight: 700,
                color: '#1f2d3b',
                background: '#ffffff',
              }}
            >
              <span>9:41</span>
              <span>LTE 87%</span>
            </div>
            <iframe
              key={preset.key}
              src={src}
              title="TaraBasa AI"
              allow="microphone; camera; autoplay"
              style={{ flex: 1, border: 0, width: '100%', background: '#ffffff' }}
            />
          </div>
        </div>
      </div>

      <p style={{ margin: '12px 0 0', fontSize: 13, color: '#56697a', textAlign: 'center', maxWidth: 520 }}>
        This screen is {preset.w} x {preset.h} dp. The app has {preset.w} x {preset.h - STATUS_BAR} below the status bar.
        {scale < 1 ? ` Drawn at ${Math.round(scale * 100)}% so it fits your window; the app still sees the full size.` : ''}
      </p>
    </div>
  );
}
