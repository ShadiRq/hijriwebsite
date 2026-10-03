<?php

namespace Tests\Feature;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;
use RizqShadi\HijriDate\CalendarType;
use RizqShadi\HijriDate\Exception\HijriDateException;
use RizqShadi\HijriDate\Exception\InvalidDateException;
use RizqShadi\HijriDate\Exception\LocationException;
use RizqShadi\HijriDate\Hijri;
use RizqShadi\HijriDate\Location;
use Tests\TestCase;

class HijriDateTest extends TestCase
{
    public function test_it_converts_gregorian_to_hijri(): void
    {
        $date = $this->riyadh()->fromGregorian('2026-02-18');

        $this->assertSame('1447-09-01', (string) $date);
        $this->assertTrue($date->isRamadan());
        $this->assertSame('Wednesday, 1 Ramadan 1447', $date->format('l, j F Y'));
        $this->assertSame('الأربعاء ١ رمضان ١٤٤٧ هـ', $date->format('l j F Y هـ', 'ar', true));
    }

    public function test_it_accepts_strings_datetimes_and_carbon(): void
    {
        $hijri = $this->riyadh();
        $inputs = ['2026-02-18', '18/02/2026', '18-02-2026', 'February 18, 2026', new DateTimeImmutable('2026-02-18'), Carbon::create(2026, 2, 18)];

        foreach ($inputs as $input) {
            $this->assertSame('1447-09-01', (string) $hijri->fromGregorian($input));
        }
    }

    public function test_it_converts_hijri_to_gregorian(): void
    {
        $hijri = $this->riyadh();
        $gregorian = $hijri->toGregorian(1446, 1, 1);

        $this->assertSame('2024-07-07', $gregorian->format('Y-m-d'));
        $this->assertSame('Asia/Riyadh', $gregorian->getTimezone()->getName());
        $this->assertSame(29, $hijri->daysInMonth(1445, 8));
    }

    public function test_invalid_input_throws_the_documented_exceptions(): void
    {
        $hijri = $this->riyadh();

        $this->assertThrows(fn () => $hijri->fromGregorian('2026-02-30'), InvalidDateException::class);
        $this->assertThrows(fn () => $hijri->fromGregorian('0500-01-01'), InvalidDateException::class);
        $this->assertThrows(fn () => $hijri->toGregorian(1445, 8, 30), InvalidDateException::class);
        $this->assertThrows(fn () => $hijri->create(1447, 13, 1), InvalidDateException::class);
        $this->assertThrows(fn () => Location::fromTimezone('Mars/Olympus_Mons'), LocationException::class);
        $this->assertThrows(fn () => $hijri->withAdjustment(3), HijriDateException::class);
    }

    public function test_ramadan_countdown(): void
    {
        $countdown = $this->riyadh()->untilRamadan('2026-09-26');

        $this->assertSame([4, 16, 135], [$countdown->months, $countdown->days, $countdown->totalDays]);
        $this->assertSame('1448-09-01', (string) $countdown->ramadanStart);
        $this->assertSame('2027-02-08', $countdown->ramadanStart->gregorian->format('Y-m-d'));
        $this->assertFalse($countdown->isRamadanNow);
        $this->assertSame('4 months and 16 days until Ramadan 1448', $countdown->toString('en'));
        $this->assertSame('باقي 4 أشهر و16 يومًا على رمضان 1448', $countdown->toString('ar'));
    }

    public function test_ramadan_countdown_during_ramadan(): void
    {
        $first = $this->riyadh()->untilRamadan('2026-02-18');
        $this->assertTrue($first->isRamadanNow);
        $this->assertSame(1, $first->dayOfRamadan);
        $this->assertSame('Ramadan 1447 starts today', $first->toString('en'));

        $tenth = $this->riyadh()->untilRamadan('2026-02-27');
        $this->assertTrue($tenth->isRamadanNow);
        $this->assertSame(10, $tenth->dayOfRamadan);
        $this->assertSame(1448, $tenth->ramadanStart->year);
    }

    public function test_moon_sighting_adjustment_and_civil_calendar(): void
    {
        $adjusted = $this->riyadh()->withAdjustment(1);
        $this->assertSame('1447-09-02', (string) $adjusted->fromGregorian('2026-02-18'));
        $this->assertSame('2026-02-17', $adjusted->toGregorian(1447, 9, 1)->format('Y-m-d'));

        $civil = $this->riyadh()->withCalendar(CalendarType::Civil)->fromGregorian('2026-02-18');
        $this->assertSame('1447-09-01', (string) $civil);
        $this->assertSame(CalendarType::Civil, $civil->calendar);
    }

    public function test_the_date_depends_on_location_and_sunset(): void
    {
        $instant = new DateTimeImmutable('2026-09-26 22:30', new DateTimeZone('UTC'));

        $this->assertSame('1448-04-16', (string) $this->riyadh()->at($instant)); // already 01:30 on the 27th in Riyadh
        $this->assertSame('1448-04-15', (string) (new Hijri(Location::fromTimezone('America/New_York')))->at($instant));
        $this->assertSame('Asia/Riyadh', Location::fromCountry('SA')->timezoneName());

        $mecca = new Hijri(Location::fromCoordinates(21.4225, 39.8262), dayStartsAtSunset: true);
        $this->assertSame('Asia/Riyadh', $mecca->location->timezoneName());
        $this->assertSame('1448-04-15', (string) $mecca->at(new DateTimeImmutable('2026-09-26 12:00', new DateTimeZone('Asia/Riyadh'))));
        $this->assertSame('1448-04-16', (string) $mecca->at(new DateTimeImmutable('2026-09-26 20:00', new DateTimeZone('Asia/Riyadh'))));
    }

    public function test_it_can_be_autowired_by_the_container(): void
    {
        $hijri = $this->app->make(Hijri::class);

        $this->assertSame(config('app.timezone'), $hijri->location->timezoneName());
        $this->assertSame(CalendarType::UmmAlQura, $hijri->calendar);
    }

    public function test_the_page_shows_today_at_the_location_and_the_countdown(): void
    {
        // Still 29 Sha'ban in UTC (the app timezone), but already 00:30 on 1 Ramadan in Riyadh.
        $this->travelTo(Carbon::parse('2026-02-17 21:30', 'UTC'));

        $this->get('/hijri')
            ->assertOk()
            ->assertSee('Wednesday, 1 Ramadan 1447 AH')
            ->assertSee('الأربعاء، ١ رمضان ١٤٤٧ هـ')
            ->assertSee('Ramadan 1447 starts today')
            ->assertSee('اليوم أول أيام رمضان ١٤٤٧');
    }

    public function test_the_page_converts_a_date(): void
    {
        $this->get('/hijri?date=18/02/2026')
            ->assertOk()
            ->assertSee('Wednesday, 1 Ramadan 1447 AH')
            ->assertSee('الأربعاء ١ رمضان ١٤٤٧ هـ');
    }

    public function test_invalid_input_is_reported_above_today_and_the_countdown(): void
    {
        $this->travelTo(Carbon::parse('2026-02-27 09:00', 'UTC')); // 10 Ramadan 1447 in Riyadh

        $this->get('/hijri?date=2026-02-30')
            ->assertStatus(422)
            ->assertSeeInOrder([
                'Invalid Gregorian date "2026-02-30".',
                'Friday, 10 Ramadan 1447 AH',
                '11 months and 21 days until Ramadan 1448',
            ]);

        $this->get('/hijri?tz=Mars/Olympus_Mons&date=2026-02-30')
            ->assertStatus(422)
            ->assertSeeInOrder([
                'Location: Asia/Riyadh',
                'Unknown timezone "Mars/Olympus_Mons". Showing Asia/Riyadh instead.',
                'Invalid Gregorian date "2026-02-30".',
                'Friday, 10 Ramadan 1447 AH',
            ]);
    }

    public function test_the_json_endpoint(): void
    {
        $this->travelTo(Carbon::parse('2026-02-27 09:00', 'UTC')); // 10 Ramadan 1447 in Riyadh

        $this->getJson('/hijri.json?date=2026-09-26')
            ->assertOk()
            ->assertJsonPath('location', 'Asia/Riyadh')
            ->assertJsonPath('calendar', 'islamic-umalqura')
            ->assertJsonPath('today.date', '1447-09-10')
            ->assertJsonPath('today.month_name_ar', 'رمضان')
            ->assertJsonPath('countdown.is_ramadan_now', true)
            ->assertJsonPath('countdown.day_of_ramadan', 10)
            ->assertJsonPath('countdown.ramadan_start.gregorian', '2027-02-08')
            ->assertJsonPath('countdown_text.en', '11 months and 21 days until Ramadan 1448')
            ->assertJsonPath('countdown_text.ar', 'باقي 11 شهرًا و21 يومًا على رمضان 1448')
            ->assertJsonPath('converted.date', '1448-04-15');

        $this->getJson('/hijri.json?calendar=islamic-civil')->assertOk()->assertJsonPath('today.calendar', 'islamic-civil');
        $this->getJson('/hijri.json?tz=Mars/Olympus_Mons')->assertStatus(422)->assertJsonPath('error', 'Unknown timezone "Mars/Olympus_Mons".');
    }

    private function riyadh(): Hijri
    {
        return new Hijri(Location::fromTimezone('Asia/Riyadh'));
    }
}
