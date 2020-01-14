# Diary 0.8.9

Events Diary.

<p align="center"><img src="diary-screenshot.png?raw=true" alt="Screenshot"></p>

## How to install extension

1. [Download and install Datenstrom Yellow](https://github.com/datenstrom/yellow/).
2. [Download extension](../../archive/master.zip). If you are using Safari, right click and select 'Download file as'.
3. Copy `diary.zip` into your `system/extensions` folder.

To uninstall delete the [extension files](extension.ini).

## How to create an events list

Put one or more events files into `media/diary/`. You can use different formats (choose whichever you like better).

Events in a `.yaml` file (each event begins with `---`; `label` is used to disambiguate between events occurring on the same date):

    ---
    date: YYYY-MM-DD
    start: HH:MM
    end: HH:MM
    label: text
    place: text
    description: text
    tags: tag tag...

Events in a `.psv` file (one event per line):

    YYYY-MM-DD | HH:MM | HH:MM | label | place  | description | tags

Events can be written also in a `.tsv` or a `.csv` format (in this latter place and description must be wrapped in quotes if they contain commas).

To add a poster to an event (e.g. with the complete programme), put it in `media/diary/posters/` with the name `YYYY-MM-DD-label.pdf`. To add an image different from the poster thumbnail, put it in `media/diary/thumbnail/` with the name `YYYY-MM-DD-label.jpg` and a timestamp newer than that of the poster.

In `place` and `description`, use `*` for italic, `**` for bold, `[text](URL)` for linking, `\n` for newline. Other URLs and email addresses are autolinked.  In `place`, enclose an address in square brackets (e.g. `Galleria degli Uffizi, [Piazzale degli Uffizi 6, Firenze]`), or write GPS coordinates in brackets (e.g. `Galleria degli Uffizi, Piazzale degli Uffizi 6, Firenze [43.7684,11.2556]`), in order to add a link to a pop-up map.

## How to embed a diary

Create a `[diary]` shortcut.

The following arguments are available, all but the first argument are optional:

`Location` = filename of events list to show  
`TimeSpan` (default: `future`) = show `future` or `past` events  
`Max` (default: `0`) = maximum number of events to show, 0 for unlimited  
`Tags` = show only events with any of the tags, wrap multiple tags into quotes  

Note: since the year of the events is not displayed, keep the events of each year in a different file and provide with an appropriate heading the page where you embed the diary.

## Settings

The following settings can be configured in file `system/settings/system.ini`.

`DiaryDir` (default: `media/diary/`) = directory for Diary files  
`DiaryPosterLocation` (default: `/media/diary/posters/`) = location for posters  
`DiaryPosterDir` (default: `media/diary/posters/`) = directory for posters  
`DiaryThumbnail` (default: `1`) = show thumbnails, 1 or 0  
`DiaryThumbnailLocation` (default: `/media/diary/thumbnails/`) = location for thumbnails  
`DiaryThumbnailDir` (default: `media/diary/thumbnails/`) = directory for thumbnails  
`DiaryMaps` (default: `openstreetmap`) = which map service is used, `openstreetmap` or `googlemaps`  
`DiaryCalendar` (default: `1`) = include link for iCalendar, 1 or 0  
`DiaryCalendarLocation` (default: `/media/diary/icalendar/`) = location for iCalendar files  
`DiaryCalendarDir` (default: `media/diary/icalendar/`) = directory for iCalendar files  
`DiaryStyle` (default: `plain`) = diary style (you can choose between `plain`, `squared`, `rounded`)  

If you want to add a new `fancy` style, write a `diary-fancy.css`  file and put into the `system/extensions` folder. Do not modify the standard styles, since they will be overwritten in case of update of the extension.

## Examples

Showing the diary of all future events:

    [diary events2019.psv]
    [diary events2019.yaml]

Showing the diary with various options:

    [diary events2019.psv past]
    [diary events2019.psv future 5]
    [diary events2019.yaml future 0 philosophy]

## Developer

Giovanni Salmeri.
