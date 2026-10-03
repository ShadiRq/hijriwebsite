<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hijri Date Test</title>
    <style>
        :root { --bg: #f7f6f2; --card: #fff; --text: #1c1b18; --muted: #6b6a64; --line: #e4e2da; --accent: #0f6b4f; --error-bg: #fdecea; --error: #9b1c1c; --conv: #1f5fae; --conv-bg: #eaf2fc; --conv-line: #bfd4f2; --ram: #8a5a00; --ram-bg: #fcf1d8; --ram-line: #eed9a6; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #151513; --card: #1f1f1c; --text: #ecebe6; --muted: #a09f98; --line: #34332e; --accent: #4cc39a; --error-bg: #3b1714; --error: #f4a19a; --conv: #7fb0f0; --conv-bg: #16243a; --conv-line: #2b4467; --ram: #e6b450; --ram-bg: #2a2310; --ram-line: #4a3d1c; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif; }
        main { max-width: 720px; margin: 0 auto; padding: 32px 16px 48px; }
        h1 { font-size: 1.4rem; margin: 0 0 4px; }
        h2 { font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin: 0 0 8px; }
        section, form { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 16px 20px; margin-top: 16px; }
        p { margin: 4px 0; }
        .muted { color: var(--muted); font-size: .9rem; }
        .big { font-size: 1.35rem; font-weight: 600; }
        [dir="rtl"] { font-family: "Segoe UI", Tahoma, "Noto Naskh Arabic", sans-serif; }
        .error { background: var(--error-bg); color: var(--error); border-radius: 12px; padding: 12px 16px; margin-top: 16px; }
        .ramadan { background: var(--ram-bg); border-color: var(--ram-line); border-inline-start: 4px solid var(--ram); }
        .ramadan h2 { color: var(--ram); }
        .emoji { font-size: 1.25em; line-height: 1; }
        .converted { background: var(--conv-bg); border-color: var(--conv-line); border-inline-start: 4px solid var(--conv); }
        .converted h2 { color: var(--conv); }
        form { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); align-items: end; }
        label { display: grid; gap: 4px; font-size: .85rem; color: var(--muted); }
        input, select, button { font: inherit; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--bg); color: var(--text); min-width: 0; }
        button { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; cursor: pointer; }
        a { color: var(--accent); }
    </style>
</head>
<body>
<main>
    <h1>rizqshadi/hijri-date in Laravel</h1>
    <p class="muted">Location: {{ $location }} · Calendar: {{ $calendar->value }}</p>

    @foreach ($errorMessages as $message)
        <div class="error" role="alert">{{ $message }}</div>
    @endforeach

    <section>
        <h2>Today</h2>
        <p class="big">{{ $today->format('l, j F Y') }} AH</p>
        <p class="big" dir="rtl" lang="ar">{{ $today->format('l، j F Y هـ', 'ar', arabicDigits: true) }}</p>
        <p class="muted">Gregorian: {{ $today->gregorian->format('l, j F Y') }}</p>
    </section>

    <section class="ramadan">
        <h2><span class="emoji" aria-hidden="true">🌙</span> Ramadan countdown</h2>
        <p>{{ $countdown->toString('en') }}</p>
        <p dir="rtl" lang="ar">{{ $countdown->toString('ar', arabicDigits: true) }}</p>
        <p class="muted">{{ $countdown->totalDays }} days · 1 Ramadan {{ $countdown->ramadanStart->year }} = {{ $countdown->ramadanStart->gregorian->format('l, j F Y') }}</p>
    </section>

    @isset($converted)
        <section class="converted">
            <h2>Converted</h2>
            <p class="big">{{ $date }} → {{ $converted->format('l, j F Y') }} AH</p>
            <p dir="rtl" lang="ar">{{ $converted->format('l j F Y هـ', 'ar', arabicDigits: true) }}</p>
        </section>
    @endisset

    <form method="get" action="{{ url('/hijri') }}">
        <label>Gregorian date
            <input name="date" value="{{ $date }}" placeholder="2026-02-18, 18/02/2026, tomorrow">
        </label>
        <label>Timezone
            <input name="tz" value="{{ $tz }}" list="zones">
            <datalist id="zones">
                @foreach (['Asia/Riyadh', 'Asia/Dubai', 'Africa/Cairo', 'Europe/Istanbul', 'Asia/Karachi', 'Asia/Jakarta', 'Europe/London', 'America/New_York'] as $zone)
                    <option value="{{ $zone }}">
                @endforeach
            </datalist>
        </label>
        <label>Calendar
            <select name="calendar">
                @foreach (\RizqShadi\HijriDate\CalendarType::cases() as $case)
                    <option value="{{ $case->value }}" @selected($case === $calendar)>{{ $case->name }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit">Convert</button>
    </form>

    <p class="muted" style="margin-top: 16px"><a href="{{ url('/hijri.json') }}?{{ http_build_query(request()->query()) }}">View as JSON</a></p>
</main>
</body>
</html>
