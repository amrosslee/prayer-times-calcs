# Reference fixtures

`PrayerTimesCalculatorTest::test_calculates_doha_reference_fixture_with_custom_interval_settings` uses the
2016-01-01 Doha row from the [Adhan shared test data](https://github.com/batoulapps/adhan-testdata/blob/d418fb37b3d011af5594e344c06c0e5616db2a5c/Times/Doha-Qatar.json).
That fixture records coordinates `25.283897, 51.528770`, timezone `Asia/Riyadh`, Qatar parameters
(Fajr 18 degrees, Isha 90 minutes after sunset), and the prayer times `04:58, 06:19, 11:37,
14:35, 16:55, 18:25`. The fixture identifies the original reference as the Qatar Ministry of
Endowments and Islamic Affairs website and states that its tabulated values are adjusted by up to
two minutes. The test allows a two-minute tolerance matching that source. This single-row reference
check is not presented as a general accuracy guarantee.

`PrayerTimesCalculatorTest::test_calculates_umm_al_qura_makkah_reference_fixture` uses the
2016-01-05 Makkah row from the [Adhan shared test data](https://github.com/batoulapps/adhan-testdata/blob/d418fb37b3d011af5594e344c06c0e5616db2a5c/Times/Makkah-UmmAlQura.json).
Coordinates are `21.427009, 39.828685`, timezone `Asia/Riyadh`, and the method is Umm al-Qura:
Fajr 18.5 degrees and Isha 90 minutes after sunset. The original reference is
[Umm al-Qura University](http://www.ummulqura.org.sa/Index.aspx), with the fixture documenting a
one-minute adjustment. The regression tolerance is one minute.

`PrayerTimesCalculatorTest::test_calculates_tehran_reference_fixture` uses the 2018-01-01 Tehran
row from the [Adhan shared test data](https://github.com/batoulapps/adhan-testdata/blob/d418fb37b3d011af5594e344c06c0e5616db2a5c/Times/Tehran-Tehran.json).
Coordinates are `35.715298, 51.404343`, timezone `Asia/Tehran`, and the Tehran parameters are Fajr
17.7 degrees, Maghrib 4.5 degrees, and Isha 14 degrees. The fixture cites
[PrayTimes.org](http://praytimes.org/) and a one-minute variance; the regression tolerance is one
minute.

`PrayerTimesCalculatorTest::test_nearest_valid_date_matches_upstream_midnight_sun_regression`
reproduces the pinned Adhan JS test `test/adhan.test.ts`, commit
[`46ed34d`](https://github.com/batoulapps/adhan-js/blob/46ed34d64bb396391e9cfef768cb8c6c43032716/test/adhan.test.ts),
for Arjeplog, Sweden (`66.7222444, 17.7189`), 2020-06-21, `Europe/Stockholm`, MWL, Seventh of
the Night, and nearest-valid-date resolution. Its expected six formatted times are copied from
that upstream regression, and the comparison is exact to the minute.

`PrayerTimesCalculatorTest::test_matches_upstream_isna_hanafi_reference_case` reproduces the
same pinned upstream test for `35.775, -78.6336` on 2015-07-12, using the ISNA/North America
parameters, Hanafi Asr, and `America/New_York`. Its six expected local times come from that
upstream test and are compared exactly to the minute.

`PrayerTimesCalculatorTest::test_matches_upstream_mwl_reference_case` uses the expected times
from the pinned Adhan JS [`test/adhan.test.ts`](https://github.com/batoulapps/adhan-js/blob/46ed34d64bb396391e9cfef768cb8c6c43032716/test/adhan.test.ts)
"using offsets to manually adjust prayer times" case at coordinates `35.775, -78.6336`, date
2015-12-01, timezone `America/New_York`, and MWL. The upstream case's first set of times is before
its subsequent manual offsets are applied; this test uses that unadjusted set and compares exactly
to the minute.

`PrayerTimesCalculatorTest::test_matches_upstream_egyptian_reference_case` reproduces the pinned
Adhan JS [`test/adhan.test.ts`](https://github.com/batoulapps/adhan-js/blob/46ed34d64bb396391e9cfef768cb8c6c43032716/test/adhan.test.ts)
Egyptian-method case for Cairo (`30.028703, 31.249528`), date 2020-01-01, timezone `Africa/Cairo`.
The upstream test supplies all six expected times; this test compares exactly to the minute.

These reference checks do not imply universal religious or astronomical accuracy. Preserve each
fixture's source and stated adjustment variance when adding cases. The reference data are not copied
into runtime code.
