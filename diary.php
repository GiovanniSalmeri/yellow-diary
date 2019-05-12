<?php
// Events extension
// Copyright (c) 2019 Giovanni Salmeri
// This file may be used and distributed under the terms of the public license.

class YellowDiary {
    const VERSION = "0.8.3";
    const TYPE = "feature";
    public $yellow;         //access to API
    public $siteId;         //site root (string)
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("diaryDir", "media/diary/");
        $this->yellow->system->setDefault("diaryPosterLocation", "/media/diary/posters/");
        $this->yellow->system->setDefault("diaryPosterDir", "media/diary/posters/");
        $this->yellow->system->setDefault("diaryThumbnail", "1");
        $this->yellow->system->setDefault("diaryThumbnailLocation", "/media/diary/thumbnails/");
        $this->yellow->system->setDefault("diaryThumbnailDir", "media/diary/thumbnails/");
        $this->yellow->system->setDefault("diaryMaps", "openstreetmap");
        $this->yellow->system->setDefault("diaryCalendar", "1");
        $this->yellow->system->setDefault("diaryCalendarLocation", "/media/diary/icalendar/");
        $this->yellow->system->setDefault("diaryCalendarDir", "media/diary/icalendar/");
        $this->yellow->system->setDefault("diaryStyle", "plain");
        $this->siteId = $this->getSiteId();
        foreach (["diaryDir", "diaryPosterDir", "diaryThumbnailDir", "diaryCalendarDir"] as $dir) {
            $path = $this->yellow->system->get($dir);
            if (!empty($path) && !is_dir($path)) @mkdir($path, 0777, true);
        }
    }

    // Handle page content of shortcut
    public function onParseContentShortcut($page, $name, $text, $type) {
        $output = null;
        if ($name=="diary" && ($type=="block" || $type=="inline")) {
            list($eventList, $timeSpan, $max, $tags) = $this->yellow->toolbox->getTextArgs($text);
            if ($timeSpan != "past") $timeSpan = "future";
            $tags = preg_split("/[\s,]+/", $tags, 0, PREG_SPLIT_NO_EMPTY);
            $eventListName = $this->yellow->system->get("diaryDir").$eventList;

            $dateMonths = preg_split("/\s*,\s*/", $this->yellow->text->get("dateMonths"));
            $dateWeekdays = preg_split("/\s*,\s*/", $this->yellow->text->get("dateWeekdays"));

            // Read and sort events
            $events = $this->parseEvents($eventListName);
            $sortType = ($timeSpan == "past" ? SORT_DESC : SORT_ASC);
            array_multisort(array_column($events, 0), $sortType, array_column($events, 1), $sortType, $events);

            $output .= "<ul class=\"diary $timeSpan\">\n";
            $eventsShown = 0;
            foreach ($events as $event) {
                $eventId = $event[0].($event[3] ? "-".$event[3] : "");

                // Syntax check
                if (!preg_match("/^\d\d\d\d-\d\d-\d\d$/", $event[0]) || !preg_match("/^\d\d:\d\d$/", $event[1]) || !preg_match("/^\d\d:\d\d$/", $event[2]) || !$event[5]) {
                    $output .= "<li>Error in event $eventId</li>\n";
                    continue;
                }

                $eventTime = strtotime($event[0]." ".$event[2]);
                $eventTags = preg_split("/[\s,]+/", $event[6], 0, PREG_SPLIT_NO_EMPTY);
                if ((($timeSpan == "future" && $eventTime > time()) || ($timeSpan == "past" && $eventTime <= time())) && (!$tags || array_intersect($eventTags, $tags))) {

                    // Human readable event date
                    $locMonth = $dateMonths[getdate($eventTime)["mon"]-1];
                    $locWday = $dateWeekdays[(getdate($eventTime)["wday"]+6) % 7];
                    $eventDate = strftime("<b>".$this->yellow->text->getHtml("diaryDay").":</b> <span class=\"wday\">$locWday</span> <span class=\"mday\">%-d</span> <span class=\"month\">$locMonth</span>", $eventTime);

                    // Poster thumbnail and link
                    define (THUMBWIDTH, 150);
                    $posterLink = null;
                    $pdfName = $this->yellow->system->get("diaryPosterDir").$eventId.".pdf";
                    $thumbName = $this->yellow->system->get("diaryThumbnailDir").$eventId.".jpg";
                    $pdfLoc = $this->yellow->system->get("serverBase").$this->yellow->system->get("diaryPosterLocation").$eventId.".pdf";
                    $thumbLoc = $this->yellow->system->get("serverBase").$this->yellow->system->get("diaryThumbnailLocation").$eventId.".jpg";
                    if (@filemtime($pdfName)) {
                        if ($this->yellow->system->get("diaryThumbnail") && !@filemtime($thumbName) || filemtime($thumbName) < filemtime($pdfName)) {
                            if(extension_loaded('Imagick') && false) {
                                $im = new Imagick($pdfName."[0]");
                                $im->setimageformat("jpeg");
                                $im->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                                $im->thumbnailImage(THUMBWIDTH, 0);
                                $im->writeImage($thumbName);
                                $im->clear();
                            } else {
				//exec(escapeshellcmd("gm convert -thumbnail ".THUMBWIDTH. "{$pdfName}[0] $thumbName"));
                                exec(escapeshellcmd("convert -alpha remove -thumbnail ".THUMBWIDTH. "x {$pdfName}[0] $thumbName"));
                            }
                        }
                        $thumbSize = getimagesize($thumbName);
                        if ($thumbSize[0] == THUMBWIDTH) {
                            $thumbAttr = $thumbSize[3];
                        } elseif ($thumbSize[0] > 0) { // resize thumbnail
                            $newHeight = floor($thumbSize[1]*THUMBWIDTH/$thumbSize[0]);
                            $thumbAttr = "width=\"".THUMBWIDTH."\" height=\"".$newHeight."\"";
                            if ($this->yellow->extensions->isExisting("image")) {
                                list($thumbLoc, $THUMBWIDTH, $newHeight) =                             $this->yellow->extensions->get("image")->getImageInformation($this->yellow->system->get("diaryThumbnailDir").$eventId.".jpg", 150, $newHeight);
                            }
                        }
                    }
                    if (@filemtime($thumbName)) {
                        $posterLink = "<img src=\"".htmlspecialchars($thumbLoc)."\" $thumbAttr alt=\"Poster\" />";
                        if (@filemtime($pdfName)) $posterLink = "<a class=\"thumb\" href=\"".htmlspecialchars($pdfLoc)."\">".$posterLink."</a>";
                    }

                    // Geolocation and map link
                    $eventPlaceGeo = $eventPlaceMap = null;
                    if (preg_match("/^(.*?)\[(.+?)\](.*)/", $event[4], $matches)) {
                        define(GOOGLEMAPS, "https://www.google.com/maps/place/");
                        define(OSM, "https://www.openstreetmap.org/#map=17/");
                        if (substr($matches[2], 0, 4) == "geo:") $matches[2] = (substr($address, 4));
                        list($lat, $lon) = explode(",", explode(";", $matches[2])[0]);
                        $lat = trim($lat); $lon = trim($lon);
                        if (is_numeric($lat) && is_numeric($lon)) {
                            $event[4] = trim($matches[1].$matches[3]);
                            $eventPlaceGeo = $lat.";".$lon;
                            if ($this->yellow->system->get("diaryMaps") == "google") {
                                $eventPlaceMap = GOOGLEMAPS.$lat.",".$lon;
                            } else {
                                $eventPlaceMap = OSM.$lat."/".$lon;
                            }
                        } else {
                            $event[4] = $matches[1].$matches[2].$matches[3];
                            if ($this->yellow->system->get("diaryMaps") == "google") {
                                $eventPlaceMap = GOOGLEMAPS.$matches[2];
                            } else {
                                list($lat, $lon) = $this->geolocation($matches[2]);
                                $eventPlaceGeo = $lat.";".$lon;
                                $eventPlaceMap = OSM.$lat."/".$lon;
                            }
                        }
                    }

                    // Generate iCalendar file
                    $calLink = null;
                    if ($this->yellow->system->get("diaryCalendar")) {
                        $calName = $this->yellow->system->get("diaryCalendarDir").$eventId.".ics";
                        if (!@filemtime($calName) || filemtime($calName) < filemtime($eventListName)) {
                            $fileHandle = @fopen($calName, "w");
                            fwrite($fileHandle, $this->getCalendar($event, $eventId, $eventPlaceGeo, $eventTags));
                            fclose($fileHandle);
                        }
                        $calLoc = $this->yellow->system->get("serverBase").$this->yellow->system->get("diaryCalendarLocation").$eventId.".ics";
                        $calLink = "<a class=\"calendar\" href=\"".htmlspecialchars($calLoc)."\">".$this->yellow->text->getHtml("diaryAdd")."</a>";
                    }

                    // Generate HTML
                    $output .= "<li>\n";
                    if (@filemtime($pdfName) || @filemtime($thumbName) && $this->yellow->system->get("diaryThumbnail")) $output .= "<div class=\"poster\">$posterLink</div>\n";
                    $output .= "<div class=\"date\">$eventDate</div>\n";
                    $output .= "<div class=\"time\"><b>".$this->yellow->text->getHtml("diaryHour").":</b> ".
($event[1][0] == "0" ? substr($event[1], 1) : $event[1])."-".($event[2][0] == "0" ? substr($event[2], 1) : $event[2])."</div>\n";
                    $output .= "<div class=\"place\"><b>".$this->yellow->text->getHtml("diaryPlace").":</b> ".($eventPlaceMap ? "<a class=\"popup\" href=\"".htmlspecialchars($eventPlaceMap)."\">".$this->toHTML($event[4])."</a>" : $this->toHTML($event[4]))."</div>\n";
                    $output .= "<div class=\"desc\">".$this->toHTML($event[5]). (@filemtime($pdfName) && (!@filemtime($thumbName) || !$this->yellow->system->get("diaryThumbnail")) ? " [<a href=\"".htmlspecialchars($pdfLoc)."\">".$this->yellow->text->getHtml("diaryPoster")."</a>]" : ""). "</div>\n";
                    if ($this->yellow->system->get("diaryCalendar")) $output .= "<div class=\"add\">$calLink</div>\n";
                    $output .= "</li>\n";
                    $eventsShown += 1;
                }
                if ($max && $eventsShown >= $max) break; 
            }
            if ($eventsShown == 0) {
                $output .= "<li>".$this->yellow->text->getHtml("diaryNoEvent")."</li>";
            }
            $output .= "</ul>\n";
        }
        return $output;
    }

    function parseEvents($fileName) {
        $events = [];
        if ($fileHandle = @fopen($fileName, "r")) {
            $type = $this->yellow->toolbox->getFileType($fileName);
            if ($type == "psv") {
                while (($data = fgetcsv($fileHandle, 0, "|", chr(0))) !== false) {
                    if ($data) $events[] = array_map("trim", $data);
                }
            } elseif ($type == "csv") {
                while (($data = fgetcsv($fileHandle)) !== false) {
                    if ($data) $events[] = array_map("trim", $data);
                }
            } elseif ($type == "tsv") {
                while (($data = fgetcsv($fileHandle, 0, "\t", chr(0))) !== false) {
                    if ($data) $events[] = array_map("trim", $data);
                }
            } elseif ($type == "yaml") {
                $FIELD = [
                    "date" => 0,
                    "start" => 1,
                    "end" => 2, 
                    "label" => 3,
                    "place" => 4,
                    "description" => 5,
                    "tags" => 6,
                ];
                $currRec = -1;
                while (($line = fgets($fileHandle)) !== false) {
                    $line = rtrim($line);
                    if ($line == "---") { 
                        $currRec += 1;
                    } elseif ($line[0] == "#") {
                        continue;
                    } elseif ($currRec >= 0) {
                        preg_match("/^(.*?):\s+(.*?)\s*$/", $line, $matches);
                        if ($matches && isset($FIELD[$matches[1]])) {
                            $events[$currRec][$FIELD[$matches[1]]] = $matches[2];
                        }
                    }
                }
            } elseif ($type == "txt") { // legacy
                $pattern = "/^(\d+-\d+-\d+), ore ([\d:]+)-([\d:]+)(.?), ([^:]+): (.*)/";
                while (($data = fgets($fileHandle)) !== false) {
                    preg_match($pattern, $data, $matches);
                    if ($matches) {
                        array_shift($matches);
                        $matches[4] = str_replace("((", "[", $matches[4]);
                        $matches[4] = str_replace("))", "]", $matches[4]);
                        $events[] = $matches;
                    }
                }
            }
            fclose($fileHandle);
        }
        return $events;
    }

    // The following code is from class YellowOpenStreetMap
    function nominatim($address) {
        $ua = ini_set("user_agent", "Yellow Diary extension ". $this::VERSION);
        $nominatim = simplexml_load_file("https://nominatim.openstreetmap.org/search?format=xml&q=$address");
        ini_set("user_agent", $ua);
        if ($nominatim) {
            $lat = (float)$nominatim->place["lat"];
            $lon = (float)$nominatim->place["lon"];
            return array($lat, $lon);
        }
    }
    function geolocation($address) {
        $cacheFile = $this->yellow->system->get("extensionDir")."openstreetmap.csv";
        $fileHandle = @fopen($cacheFile, "r");
        if ($fileHandle) {
            while ($data = fgetcsv($fileHandle)) {
                $cache[$data[0]] = array($data[1], $data[2]);
            }
            fclose($fileHandle);
        }
        if (!isset($cache[$address])) {
            $cache[$address] = $this->nominatim($address);
            if (isset($cache[$address][0]) && isset($cache[$address][1])) {
                $fileHandle = @fopen($cacheFile, "w");
                foreach ($cache as $addr => $coord) {
                    fputcsv($fileHandle, array($addr, $coord[0], $coord[1]));
                }
                fclose($fileHandle);
            }
        }
        return $cache[$address];
    }

    function toHTML($text) {
        $text = htmlspecialchars($text);
        $text = preg_replace_callback('/\\\[\\\n]/', function($m) { return $m[0] == "\\\\" ? "\\" : "<br />\n"; }, $text);
        $text = preg_replace("/\*\*(.+?)\*\*/", "<b>$1</b>", $text);
        $text = preg_replace("/\*(.+?)\*/", "<i>$1</i>", $text);
        $text = preg_replace("/(?<!\()(https?:\/\/[^ )]+)(?!\))/", "<a href=\"$1\">$1</a>", $text);
        $text = preg_replace("/\[(.*?)\]\((https?:\/\/[^ )]+)\)/", "<a href=\"$2\">$1</a>", $text);
        $text = preg_replace("/(\S+@\S+\.[a-z]+)/", "<a href=\"mailto:$1\">$1</a>", $text);
        return $text;
    }

    function getCalendar($event, $eventId, $eventPlaceGeo, $eventTags) {
        $output = null;
        $output .= "BEGIN:VCALENDAR\r\n";
        $output .= "PRODID:-//github.com/GiovanniSalmeri//NONSGML Yellow Diary".$this::VERSION."//EN\r\n";
        $output .= "VERSION:2.0\r\n";
        $output .= "BEGIN:VEVENT\r\n";
        $output .= "UID:".sha1($eventId.$this->siteId)."\r\n"; // more paranoiac than RFC 5545, less than RFC 7986
        $output .= "DTSTAMP:".gmstrftime("%Y%m%dT%H%M%SZ")."\r\n";
        $output .= "DTSTART:".gmstrftime("%Y%m%dT%H%M%SZ", strtotime($event[0]." ".$event[1]))."\r\n";
        $output .= "DTEND:".gmstrftime("%Y%m%dT%H%M%SZ", strtotime($event[0]." ".$event[2]))."\r\n";
        $output .= $this->fold("LOCATION:".$event[4])."\r\n";
        if ($eventPlaceGeo) $output .= "GEO:".$eventPlaceGeo."\r\n";
        $output .= $this->fold("DESCRIPTION:".$event[5])."\r\n";
        if (@filemtime($pdfName) && $this->yellow->system->get("diaryThumbnailDir")) $output .= "ATTACH:".$this->yellow->system->get("serverBase").$pdfLoc."\r\n";
        if ($eventTags) $output .= $this->fold("CATEGORIES:".implode(",",$eventTags))."\r\n";
        $output .= "END:VEVENT\r\n";
        $output .= "END:VCALENDAR\r\n";
        return $output;
    }

    function fold($line) {
        $foldedLine = null;
        while (strlen($line) > 75) {
            $start = 75;
            while ($line[$start] > chr(127) && $line[$start] < chr(192)) $start -= 1; // do not break UTF-8
            $foldedLine .= substr($line, 0, $start)."\r\n";
            $line = " ".substr($line, $start);
        }
        $foldedLine .= $line;
        return $foldedLine;
    }

    function getSiteId() {
        preg_match("/^(.*)\/.*\.php$/", $_SERVER["SCRIPT_NAME"], $matches);
        return "@".$_SERVER["SERVER_NAME"].$matches[1];
    }

    // Handle page extra data
    public function onParsePageExtra($page, $name) {
        $output = null;
        if ($name=="header") {
            $extensionLocation = $this->yellow->system->get("serverBase").$this->yellow->system->get("extensionLocation");
            $style = $this->yellow->system->get("diaryStyle");
            $output .= "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"{$extensionLocation}diary-{$style}.css\" />\n";
            $output .= "<script type=\"text/javascript\" defer=\"defer\" src=\"{$extensionLocation}diary.js\"></script>\n";
        }
        return $output;
    }
}
