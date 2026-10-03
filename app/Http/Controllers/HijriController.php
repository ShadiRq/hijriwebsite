<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RizqShadi\HijriDate\CalendarType;
use RizqShadi\HijriDate\Exception\HijriDateException;
use RizqShadi\HijriDate\Hijri;
use RizqShadi\HijriDate\HijriDate;
use RizqShadi\HijriDate\Location;
use RizqShadi\HijriDate\RamadanCountdown;

class HijriController extends Controller
{
    private const DEFAULT_TIMEZONE = 'Asia/Riyadh';

    /**
     * Today's Hijri date, the Ramadan countdown, and an optional ?date= conversion.
     * Invalid input is reported above the page instead of replacing it.
     */
    public function show(Request $request): Response
    {
        $input = $this->input($request);
        $errorMessages = [];

        try {
            $location = Location::fromTimezone($input['tz']);
        } catch (HijriDateException $e) {
            $errorMessages[] = $e->getMessage().' Showing '.self::DEFAULT_TIMEZONE.' instead.';
            $location = Location::fromTimezone(self::DEFAULT_TIMEZONE);
        }

        $hijri = new Hijri($location, $input['calendar']);
        $converted = null;

        if ($input['date'] !== null) {
            try {
                $converted = $hijri->fromGregorian($input['date']);
            } catch (HijriDateException $e) {
                $errorMessages[] = $e->getMessage();
            }
        }

        return response()->view('hijri', $input + $this->todayAndCountdown($hijri) + [
            'location' => $location->timezoneName(),
            'converted' => $converted,
            'errorMessages' => $errorMessages,
        ], $errorMessages === [] ? 200 : 422);
    }

    public function json(Request $request): JsonResponse
    {
        $input = $this->input($request);

        try {
            $hijri = new Hijri(Location::fromTimezone($input['tz']), $input['calendar']);
            $converted = $input['date'] !== null ? $hijri->fromGregorian($input['date']) : null;
        } catch (HijriDateException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        ['today' => $today, 'countdown' => $countdown] = $this->todayAndCountdown($hijri);

        return response()->json([
            'location' => $hijri->location->timezoneName(),
            'calendar' => $hijri->calendar,
            'today' => $today,
            'countdown' => $countdown,
            'countdown_text' => [
                'en' => $countdown->toString('en'),
                'ar' => $countdown->toString('ar'),
            ],
            'converted' => $converted,
        ], options: JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{tz: string, calendar: CalendarType, date: ?string}
     */
    private function input(Request $request): array
    {
        return [
            'tz' => $request->string('tz')->toString() ?: self::DEFAULT_TIMEZONE,
            'calendar' => CalendarType::tryFrom($request->string('calendar')->toString()) ?? CalendarType::UmmAlQura,
            'date' => $request->string('date')->toString() ?: null,
        ];
    }

    /**
     * @return array{today: HijriDate, countdown: RamadanCountdown}
     */
    private function todayAndCountdown(Hijri $hijri): array
    {
        // at(now()) rather than today(), so Laravel's time travel (travelTo / Carbon::setTestNow) applies.
        $today = $hijri->at(now());

        return ['today' => $today, 'countdown' => $hijri->untilRamadan($today->gregorian)];
    }
}
