# Diary 0.8.16

Events diary.

<p align="center"><img src="diary-screenshot.png?raw=true" alt="Screenshot"></p>

## How to install an extension

[Download ZIP file](https://github.com/GiovanniSalmeri/yellow-diary/archive/main.zip) and copy it into your `system/extensions` folder. [Learn more about extensions](https://github.com/annaesvensson/yellow-update).

## How to create an events list

Put one or more events files into `media/diary/`. You can use YAML, PSV, TSV and CSV format, choose whichever you like better.

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

## How to show an events list

Create a `[diary]` shortcut.

The following arguments are available, all but the first argument are optional:

`Name` = file name  
`TimeSpan` = show `future` or `past` events  
`Max` = number of events to show per shortcut, 0 for unlimited  
`Tags` = show events with specific tags, wrap multiple tags into quotes  

Note: Since the year of the events is not displayed, keep the events of each year in a different file and provide with an appropriate heading the page where you embed the diary. 

If you want to customise the events with CSS, write a `diary-custom.css` file, put it into your `system/extensions` folder, open file `system/extensions/yellow-system.ini` and change `DiaryStyle: custom`. Another option to customise the events with CSS is editing the files in your `system/themes` folder. It's recommended to use the latter option.

## Examples


Content file with events list:

    ---
    Title: Example page
    ---
    Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut 
    labore et dolore magna pizza. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris 
    nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit 
    esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt 
    in culpa qui officia deserunt mollit anim id est laborum.

    [diary events2019.psv]
    
Showing an events list, different files:

    [diary events2019.psv]
    [diary events2019.yaml]
    [diary events2019.csv]

Showing an events list, various options:

    [diary events2019.psv past]
    [diary events2030.yaml future 5]
    [diary events2030.yaml future 0 philosophy]

## Settings

The following settings can be configured in file `system/extensions/yellow-system.ini`:

`DiaryDir` = directory for diary files  
`DiaryPosterLocation` = location for posters  
`DiaryPosterDir` = directory for posters  
`DiaryThumbnail` = show thumbnails, 1 or 0  
`DiaryThumbnailLocation` = location for thumbnails  
`DiaryThumbnailDir` = directory for thumbnails  
`DiaryMaps` = which map service is used, `openstreetmap` or `googlemaps`  
`DiaryCalendar` = include link for iCalendar, 1 or 0  
`DiaryCalendarLocation` = location for iCalendar files  
`DiaryCalendarDir` = directory for iCalendar files  
`DiaryStyle` = diary style, e.g. `plain`, `squared`, `rounded`  

## Developer

Giovanni Salmeri. [Get help](https://datenstrom.se/yellow/help/).
