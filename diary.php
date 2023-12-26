<?php
// Diary extension, https://github.com/GiovanniSalmeri/yellow-diary

class YellowDiary {
    const VERSION = "0.8.16";
    public $yellow;         //access to API
    public $siteId;         //site root (string)
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("diaryDirectory", "media/diary/");
        $this->yellow->system->setDefault("diaryPosterLocation", "/media/diary/posters/");
        $this->yellow->system->setDefault("diaryPosterDirectory", "media/diary/posters/");
        $this->yellow->system->setDefault("diaryThumbnail", "1");
        $this->yellow->system->setDefault("diaryThumbnailLocation", "/media/diary/thumbnails/");
        $this->yellow->system->setDefault("diaryThumbnailDirectory", "media/diary/thumbnails/");
        $this->yellow->system->setDefault("diaryMaps", "openstreetmap");
        $this->yellow->system->setDefault("diaryCalendar", "1");
        $this->yellow->system->setDefault("diaryCalendarLocation", "/media/diary/icalendar/");
        $this->yellow->system->setDefault("diaryCalendarDirectory", "media/diary/icalendar/");
        $this->yellow->system->setDefault("diaryStyle", "plain");
        $this->siteId = $this->getSiteId();
        foreach (["diaryDirectory", "diaryPosterDirectory", "diaryThumbnailDirectory", "diaryCalendarDirectory"] as $dir) {
            $path = $this->yellow->system->get($dir);
            if (!is_string_empty($path) && !is_dir($path)) @mkdir($path, 0777, true);
        }
        $this->yellow->language->setDefaults([
            "Language: en",
            "DiaryDay: Day",
            "DiaryHour: Hour",
            "DiaryPlace: Location",
            "DiaryNoEvent: No events at the moment",
            "DiaryAdd: Add to Your Calendar",
            "DiaryPoster: Poster",
            "Language: de",
            "DiaryDay: Tag",
            "DiaryHour: Uhr",
            "DiaryPlace: Ort",
            "DiaryNoEvent: Zur Zeit keine Veranstaltungen",
            "DiaryAdd: Zu deinem Kalender hinzufügen",
            "DiaryPoster: Plakat",
            "Language: fr",
            "DiaryDay: Jour",
            "DiaryHour: Heure",
            "DiaryPlace: Place",
            "DiaryNoEvent: Aucun évènement pour le moment",
            "DiaryAdd: Ajouter à votre calendrier",
            "DiaryPoster: Affiche",
            "Language: it",
            "DiaryDay: Giorno",
            "DiaryHour: Ora",
            "DiaryPlace: Luogo",
            "DiaryNoEvent: Nessun evento per il momento",
            "DiaryAdd: Aggiungi al tuo calendario",
            "DiaryPoster: Locandina",
            "Language: es",
            "DiaryDay: Día",
            "DiaryHour: Hora",
            "DiaryPlace: Lugar",
            "DiaryNoEvent: No hay eventos por el momento",
            "DiaryAdd: Añadir a tu calendario",
            "DiaryPoster: Cartel",
            "Language: nl",
            "DiaryDay: Dag",
            "DiaryHour: Uur",
            "DiaryPlace: Plaats",
            "DiaryNoEvent: Op dit moment geen evenement",
            "DiaryAdd: Toevoegen aan je kalender",
            "DiaryPoster: Aanplakbiljet",
            "Language: pt",
            "DiaryDay: Dia",
            "DiaryHour: Hora",
            "DiaryPlace: Lugar",
            "DiaryNoEvent: Nenhum evento no momento",
            "DiaryAdd: Adicionar ao seu calendário",
            "DiaryPoster: Cartaz",
        ]);
    }

    // Handle page content of shortcut
    public function onParseContentShortcut($page, $name, $text, $type) {
        define("THUMBWIDTH", 150);
        define("GOOGLEMAPS", "https://www.google.com/maps/place/");
        define("OSM", "https://www.openstreetmap.org/#map=17/");
        $output = null;
        if ($name=="diary" && ($type=="block" || $type=="inline")) {
            list($eventList, $timeSpan, $max, $tags) = $this->yellow->toolbox->getTextArguments($text);
            if ($timeSpan != "past") $timeSpan = "future";
            $tags = preg_split("/[\s,]+/", $tags, 0, PREG_SPLIT_NO_EMPTY);
            $eventListName = $this->yellow->system->get("diaryDirectory").$eventList;

            $dateMonths = preg_split("/\s*,\s*/", $this->yellow->language->getText("coreDateMonthsGenitive"));
            $dateWeekdays = preg_split("/\s*,\s*/", $this->yellow->language->getText("coreDateWeekdays"));

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
                    $getDate = getdate($eventTime);
                    $eventDate = "<b>".$this->yellow->language->getTextHtml("diaryDay").":</b> <span class=\"wday\">".$dateWeekdays[($getDate["wday"]+6) % 7]."</span> <span class=\"mday\">".$getDate["mday"]."</span> <span class=\"month\">".$dateMonths[$getDate["mon"]-1]."</span>";

                    // Poster thumbnail and link
                    $posterLink = null;
                    $pdfName = $this->yellow->system->get("diaryPosterDirectory").$eventId.".pdf";
                    $thumbName = $this->yellow->system->get("diaryThumbnailDirectory").$eventId.".jpg";
                    $pdfLoc = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("diaryPosterLocation").$eventId.".pdf";
                    $thumbLoc = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("diaryThumbnailLocation").$eventId.".jpg";
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
                            if ($this->yellow->extension->isExisting("image")) {
                                list($thumbLoc, $THUMBWIDTH, $newHeight) = $this->yellow->extension->get("image")->getImageInformation($this->yellow->system->get("diaryThumbnailDirectory").$eventId.".jpg", 150, $newHeight);
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
                        if (substr($matches[2], 0, 4) == "geo:") $matches[2] = (substr($address, 4));
                        list($lat, $lon) = explode(",", explode(";", $matches[2])[0]);
                        $lat = trim($lat); $lon = trim($lon);
                        if (is_numeric($lat) && is_numeric($lon)) {
                            $event[4] = trim($matches[1].$matches[3]);
                            $eventPlaceGeo = $lat.";".$lon;
                            if ($this->yellow->system->get("diaryMaps") == "googlemaps") {
                                $eventPlaceMap = GOOGLEMAPS.$lat.",".$lon;
                            } else {
                                $eventPlaceMap = OSM.$lat."/".$lon;
                            }
                        } else {
                            $event[4] = $matches[1].$matches[2].$matches[3];
                            if ($this->yellow->system->get("diaryMaps") == "googlemaps") {
                                $eventPlaceMap = GOOGLEMAPS.rawurlencode($matches[2]);
                            } else {
                                list($lat, $lon) = $this->geolocation($matches[2]);
                                $eventPlaceGeo = $lat.";".$lon;
                                $eventPlaceMap = OSM.$lat."/".$lon;
                            }
                        }
                    }

                    // Generate iCalendar file
                    $calLink = null;
                    if ($timeSpan == "future" && $this->yellow->system->get("diaryCalendar")) {
                        $calName = $this->yellow->system->get("diaryCalendarDirectory").$eventId.".ics";
                        if (!@filemtime($calName) || filemtime($calName) < filemtime($eventListName)) {
                            $fileHandle = @fopen($calName, "w");
                            fwrite($fileHandle, $this->getCalendar($event, $eventId, $eventPlaceGeo, $eventTags));
                            fclose($fileHandle);
                        }
                        $calLoc = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("diaryCalendarLocation").$eventId.".ics";
                        $calLink = "<a class=\"calendar\" href=\"".htmlspecialchars($calLoc)."\">".$this->yellow->language->getTextHtml("diaryAdd")."</a>";
                    }

                    // Generate HTML
                    $output .= "<li>\n";
                    if (@filemtime($pdfName) || @filemtime($thumbName) && $this->yellow->system->get("diaryThumbnail")) $output .= "<div class=\"poster\">$posterLink</div>\n";
                    $output .= "<div class=\"date\">$eventDate</div>\n";
                    $output .= "<div class=\"time\"><b>".$this->yellow->language->getTextHtml("diaryHour").":</b> ".
($event[1][0] == "0" ? substr($event[1], 1) : $event[1])."-".($event[2][0] == "0" ? substr($event[2], 1) : $event[2])."</div>\n";
                    $output .= "<div class=\"place\"><b>".$this->yellow->language->getTextHtml("diaryPlace").":</b> ".($eventPlaceMap ? "<a class=\"popup\" href=\"".htmlspecialchars($eventPlaceMap)."\">".$this->toHTML($event[4])."</a>" : $this->toHTML($event[4]))."</div>\n";
                    $output .= "<div class=\"desc\">".$this->toHTML($event[5]). (@filemtime($pdfName) && (!@filemtime($thumbName) || !$this->yellow->system->get("diaryThumbnail")) ? " [<a href=\"".htmlspecialchars($pdfLoc)."\">".$this->yellow->language->getTextHtml("diaryPoster")."</a>]" : ""). "</div>\n";
                    if ($timeSpan == "future" && $this->yellow->system->get("diaryCalendar")) $output .= "<div class=\"add\">$calLink</div>\n";
                    $output .= "</li>\n";
                    $eventsShown += 1;
                }
                if ($max && $eventsShown >= $max) break; 
            }
            if ($eventsShown == 0) {
                $output .= "<li>".$this->yellow->language->getTextHtml("diaryNoEvent")."</li>";
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
                    if ($line == "" || $line[0] == "#") {
                        continue;
                    } elseif ($line == "---") { 
                        $currRec += 1;
                    } elseif ($currRec >= 0) {
                        if (preg_match("/^(.*?):\s+(.*?)\s*$/", $line, $matches) && isset($FIELD[$matches[1]])) {
                            $events[$currRec][$FIELD[$matches[1]]] = $matches[2];
                        }
                    }
                }
            } elseif ($type == "txt") { // legacy
                $pattern = "/^(\d+-\d+-\d+), ore ([\d:]+)-([\d:]+)(.?), ([^:]+): (.*)/";
                while (($data = fgets($fileHandle)) !== false) {
                    if (preg_match($pattern, $data, $matches)) {
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
        $nominatim = simplexml_load_file("https://nominatim.openstreetmap.org/search?format=xml&q=".rawurlencode($address));
        ini_set("user_agent", $ua);
        if ($nominatim) {
            $lat = (float)$nominatim->place["lat"];
            $lon = (float)$nominatim->place["lon"];
            return array($lat, $lon);
        }
    }
    function geolocation($address) {
        $cacheFile = $this->yellow->system->get("coreExtensionDirectory")."openstreetmap.csv";
        $fileHandle = @fopen($cacheFile, "r");
        $cache = [];
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

    // Minimal markdown
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

    // Build iCalendar object RFC 5545
    function getCalendar($event, $eventId, $eventPlaceGeo, $eventTags) {
        $quote = function($string) { return '"'. str_replace(['^', '"'], ["^^", "^'"], $string) . '"'; }; // RFC 6868
        $escape = function($string) { return addcslashes($string, '\,;'); };
        $timeFormat = "Ymd\THis\Z";
        $start = gmdate($timeFormat, strtotime($event[0]." ".$event[1]));
        $end = gmdate($timeFormat, strtotime($event[0]." ".$event[2]));
        $lines = [];
        $lines[] ="BEGIN:VCALENDAR";
        $lines[] ="PRODID:-//github.com/GiovanniSalmeri//NONSGML YellowMailer ".$this::VERSION."//EN";
        $lines[] ="VERSION:2.0";
        $lines[] ="METHOD:REQUEST";
        $lines[] ="BEGIN:VEVENT";
        $lines[] ="UID:".md5($eventId."@".$this->siteId); // more paranoiac than RFC 5545, less than RFC 7986
        $lines[] ="DTSTAMP:".gmdate($timeFormat);
        $lines[] ="DTSTART:".$start;
        $lines[] ="DTEND:".$end;
        $lines[] = "LOCATION:".$escape($event[4]);
        if ($eventPlaceGeo) $lines[] ="GEO:".str_replace([",", " "], [";", ""], $ical['geo']);
        if (preg_match("/\*(.*?)\*/", $event[5], $matches)) $lines[] = "SUMMARY:".$escape($matches[1]);
        $lines[] = "DESCRIPTION:".$escape($event[5]);
        if (@filemtime($pdfName) && $this->yellow->system->get("diaryThumbnailDirectory")) $lines[] = "ATTACH:".$this->yellow->system->get("coreServerBase").$pdfLoc;
        if ($eventTags) $lines[] = "CATEGORIES:".implode(",",$eventTags);
        $lines[] ="END:VEVENT";
        $lines[] ="END:VCALENDAR";
        $output = null;
        foreach ($lines as $line) {
            while (strlen($line) > 1) {
                $fragment = mb_strcut($line, 0, 73);
                $line = " ".substr($line, strlen($fragment));
                $output .= $fragment . "\r\n";
            }
        }
        return $output;
    }

    // Get a site identifier
    function getSiteId() {
        if (preg_match("/^(.*)\/.*\.php$/", $this->yellow->toolbox->getServer("SCRIPT_NAME"), $matches)) {
	    return "@".$this->yellow->toolbox->getServer("SERVER_NAME").$matches[1];
	} else {
	    return "@".$this->yellow->toolbox->getServer("SERVER_NAME");
	}
    }

    // Handle page extra data
    public function onParsePageExtra($page, $name) {
        $output = null;
        if ($name=="header") {
            $extensionLocation = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("coreExtensionLocation");
            $style = $this->yellow->system->get("diaryStyle");
            $output .= "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"{$extensionLocation}diary-{$style}.css\" />\n";
            $output .= "<script type=\"text/javascript\" defer=\"defer\" src=\"{$extensionLocation}diary.js\"></script>\n";
        }
        return $output;
    }
}
