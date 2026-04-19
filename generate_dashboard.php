<?php
// ============================================================================
// 1. CONFIGURATION & SETUP
// ============================================================================
date_default_timezone_set('America/New_York');

// Image dimensions
define('IMG_WIDTH', 800);
define('IMG_HEIGHT', 480);

// Layout Boundaries
$dividerLeft = 240;
$dividerRight = 533;

// Font Path
$font = __DIR__ . '/Roboto-VariableFont.ttf';

// Initialize Canvas
$image = imagecreatetruecolor(IMG_WIDTH, IMG_HEIGHT);

// 4-Shade Greyscale Palette
$colors = [
    'white'      => imagecolorallocate($image, 255, 255, 255),
    'light_grey' => imagecolorallocate($image, 170, 170, 170),
    'dark_grey'  => imagecolorallocate($image, 85, 85, 85),
    'black'      => imagecolorallocate($image, 0, 0, 0)
];

// Fill background
imagefill($image, 0, 0, $colors['white']);

// ============================================================================
// 2. DATA FETCHING
// ============================================================================

// Fetch Weather Data (Open-Meteo)
$weatherUrl = "https://api.open-meteo.com/v1/forecast?latitude=33.990067&longitude=-84.343689&daily=sunrise,sunset,precipitation_probability_max,temperature_2m_max,temperature_2m_min,apparent_temperature_max&hourly=temperature_2m,relative_humidity_2m,precipitation_probability,cloud_cover,wind_speed_10m&current=temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,cloud_cover&timezone=America%2FNew_York&wind_speed_unit=mph&temperature_unit=fahrenheit&precipitation_unit=inch";
$weather_json = @file_get_contents($weatherUrl);
$weather = $weather_json ? json_decode($weather_json, true) : null;

// Fetch Moon Data (USNO)
$currentDate = date('Y-m-d');
$moonUrl = "https://aa.usno.navy.mil/api/rstt/oneday?date={$currentDate}&coords=33.99,-84.34&tz=-4";
$moon_json = @file_get_contents($moonUrl);
$moonData = $moon_json ? json_decode($moon_json, true) : null;

// Fetch Indoor AirGradient Data
$airUrl = "https://api.airgradient.com/public/api/v1/locations/63386/measures/current?token=8a8167d1-93e4-47a3-aff7-edfa387f2364";
$air_json = @file_get_contents($airUrl);
$airData = $air_json ? json_decode($air_json, true) : null;

// ============================================================================
// 3. DRAW DASHBOARD COMPONENTS
// ============================================================================

// Draw Left & Middle Weather Sections
if ($weather) {
    drawCurrentWeather($image, $weather, $colors, $font);
    drawDailySummary($image, $weather, $colors, $font);
    drawSunset($image, $weather, $colors, $font);
    drawHourlyChart($image, $weather, $colors, $font, $dividerLeft);
} else {
    // Fallback error message if API fails
    imagettftext($image, 14, 0, 20, 40, $colors['black'], $font, "Error loading weather data.");
}

if ($moonData) {
    drawMoonData($image, $moonData, $colors, $font);
}

if ($airData) {
    drawIndoorData($image, $airData, $colors, $font);
}

// Draw Right Calendar & Forecast Sections
drawCalendar($image, $colors, $font, $dividerRight);

if ($weather) {
    drawForecast($image, $weather, $colors, $font, $dividerRight);
}

// ============================================================================
// 4. DRAW LAYOUT DIVIDERS
// ============================================================================

// Vertical divider separating the main content from calendar/forecast
imageline($image, $dividerRight, 0, $dividerRight, IMG_HEIGHT, $colors['black']); 

// Horizontal divider below Current Weather
imageline($image, 0, 245, $dividerLeft - 20, 245, $colors['light_grey']); 

// Horizontal divider separating sunset and moon
imageline($image, 0, 342, $dividerLeft - 20, 342, $colors['light_grey']); 

// Horizontal divider separating moon and indoor temp text
imageline($image, 0, 430, $dividerLeft - 20, 430, $colors['light_grey']); 

// Horizontal divider separating calendar and forecast
imageline($image, $dividerRight, 285, IMG_WIDTH, 285, $colors['light_grey']); 

// ============================================================================
// 5. OUTPUT IMAGE
// ============================================================================

header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);

// ============================================================================
// COMPONENT FUNCTIONS
// ============================================================================

/**
 * Draws the current weather conditions, temperature, and humidity.
 */
function drawCurrentWeather($img, $data, $colors, $font) {
    $current = $data['current'];
    
    $state = "Clear";
    if ($current['precipitation'] > 0) {
        $state = "Rainy";
    } elseif (isset($current['cloud_cover']) && $current['cloud_cover'] > 50) {
        $state = "Cloudy";
    }

    $temp = round($current['temperature_2m']) . "°";
    $humidity = "Humidity: " . $current['relative_humidity_2m'] . "%";
    
    $x = 15;
    $currentTime = date('g:i a');
    
    // Calculate bounding box of the state text to dynamically position the cloud percentage
    $bbox = imagettfbbox(31, 0, $font, $state);
    $stateWidth = $bbox[2] - $bbox[0];
    
    imagettftext($img, 13, 0, $x, 35, $colors['dark_grey'], $font, "CURRENT WEATHER - " . $currentTime);
    imagettftext($img, 92, 0, $x - 5, 140, $colors['black'], $font, $temp);
    imagettftext($img, 31, 0, $x, 185, $colors['black'], $font, $state);
    
    // Draw the cloud cover percentage directly adjacent to the state word
    if (isset($current['cloud_cover'])) {
        $cloudStr = $current['cloud_cover'] . "% cover";
        imagettftext($img, 15, 0, $x + $stateWidth + 10, 185, $colors['dark_grey'], $font, $cloudStr);
    }

    imagettftext($img, 15, 0, $x + 5, 225, $colors['dark_grey'], $font, $humidity);
}

/**
 * Draws the upcoming 4-day forecasted temperatures and precipitation.
 */
function drawForecast($img, $data, $colors, $font, $dividerRight) {
    $daily = $data['daily'];
    
    $x = $dividerRight + 15;
    $y = 315;
    
    imagettftext($img, 12, 0, $x, $y, $colors['dark_grey'], $font, "FORECAST");
    $y += 35;
    
    for ($i = 1; $i <= 4; $i++) {
        $timestamp = strtotime($daily['time'][$i]);
        $dayName = date('D', $timestamp);
        $high = round($daily['temperature_2m_max'][$i]);
        $low = round($daily['temperature_2m_min'][$i]);
        $precip = $daily['precipitation_probability_max'][$i];
        
        $text = sprintf("%s: %d°/%d° Rain: %d%%", $dayName, $high, $low, $precip);
        imagettftext($img, 13, 0, $x, $y, $colors['black'], $font, $text);
        
        $y += 35; 
    }
}

/**
 * Draws the high/low temperature and average wind speed for the current day.
 */
function drawDailySummary($img, $data, $colors, $font) {
    $daily = $data['daily'];
    $hourly = $data['hourly'];

    // Retrieve today's values
    $maxTemp = round($daily['temperature_2m_max'][0]);
    $minTemp = round($daily['temperature_2m_min'][0]);
    $feelsLikeMax = round($daily['apparent_temperature_max'][0]);

    // Calculate average wind speed for today from the first 24 hours of hourly data
    $todayWindData = array_slice($hourly['wind_speed_10m'], 0, 24);
    $avgWind = round(array_sum($todayWindData) / count($todayWindData));

    $x = 20;
    $y = 275;

    imagettftext($img, 12, 0, $x, $y, $colors['black'], $font, "Today:  {$maxTemp}° / {$minTemp}°");
    
    $y += 19;
    imagettftext($img, 12, 0, $x, $y, $colors['dark_grey'], $font, "Max feels: {$feelsLikeMax}°  |  Wind: {$avgWind}mph");
}

/**
 * Draws today's sunset time.
 */
function drawSunset($img, $data, $colors, $font) {
    if (isset($data['daily']['sunset'][0])) {
        $sunsetTime = date('g:ia', strtotime($data['daily']['sunset'][0]));
        
        $x = 20;
        $y = 324;
        
        imagettftext($img, 12, 0, $x, $y, $colors['black'], $font, "Sunset: " . $sunsetTime);
    }
}

/**
 * Draws the 12-hour plotted temperature graph, including humidity and precipitation.
 */
function drawHourlyChart($img, $data, $colors, $font, $dividerLeft) {
    $hourly = $data['hourly'];
    $hoursToShow = 12;
    
    // Find the index for the current hour
    $currentHourTimestamp = strtotime(date('Y-m-d H:00:00'));
    $startIndex = 0;
    foreach ($hourly['time'] as $index => $timeStr) {
        if (strtotime($timeStr) >= $currentHourTimestamp) {
            $startIndex = $index;
            break;
        }
    }
    
    $temps = array_slice($hourly['temperature_2m'], $startIndex, $hoursToShow);
    $humidities = array_slice($hourly['relative_humidity_2m'], $startIndex, $hoursToShow);
    $precips = array_slice($hourly['precipitation_probability'], $startIndex, $hoursToShow);
    $times = array_slice($hourly['time'], $startIndex, $hoursToShow);
    
    $chartX_start = $dividerLeft + 75; 
    $chartX_end = 470; 
    $chartY_start = 80;
    $chartY_end = 455;
    
    // Draw Graph Column Headers
    $headerY = $chartY_start - 20;
    imagettftext($img, 10, 0, $dividerLeft + 50, $headerY, $colors['dark_grey'], $font, "prec");
    
    $tempHeaderX = $chartX_start + (($chartX_end - $chartX_start) / 2) - 15; 
    imagettftext($img, 10, 0, $tempHeaderX, $headerY, $colors['dark_grey'], $font, "temp");
    imagettftext($img, 10, 0, 495, $headerY, $colors['dark_grey'], $font, "hum");

    // Calculate Graph Scaling
    $minT = min($temps) - 2; 
    $maxT = max($temps) + 2; 
    $rangeT = $maxT - $minT;
    if ($rangeT == 0) $rangeT = 1;
    
    $yStep = ($chartY_end - $chartY_start) / ($hoursToShow - 1);
    
    $prevX = null;
    $prevY = null;
    
    for ($i = 0; $i < $hoursToShow; $i++) {
        $y = $chartY_start + ($i * $yStep);
        $x = $chartX_start + (($temps[$i] - $minT) / $rangeT) * ($chartX_end - $chartX_start);
        
        // Draw connecting line between points
        if ($prevX !== null) {
            imageline($img, $prevX, $prevY, $x, $y, $colors['black']);
            imageline($img, $prevX + 1, $prevY, $x + 1, $y, $colors['black']); // Line thickness
        }
        
        // Draw data node
        imagefilledellipse($img, $x, $y, 8, 8, $colors['dark_grey']);
        
        // Label Formatting
        $hourLabel = date('ga', strtotime($times[$i]));
        $precipLabel = $precips[$i] . "%";
        $tempLabel = round($temps[$i]) . "°";
        $humLabel  = $humidities[$i] . "%"; 
        
        // Draw Left Columns (Time and Precipitation)
        imagettftext($img, 11, 0, $dividerLeft + 5, $y + 5, $colors['black'], $font, $hourLabel);
        imagettftext($img, 10, 0, $dividerLeft + 50, $y + 5, $colors['dark_grey'], $font, $precipLabel);
        
        // Draw Plotted Temperature
        imagettftext($img, 11, 0, $x + 12, $y + 5, $colors['black'], $font, $tempLabel);
        
        // Draw Right Column (Humidity)
        imagettftext($img, 10, 0, 495, $y + 5, $colors['dark_grey'], $font, $humLabel);
        
        $prevX = $x;
        $prevY = $y;
    }
}

/**
 * Draws the current month's calendar, highlighting today's date.
 */
function drawCalendar($img, $colors, $font, $dividerRight) {
    $calX = $dividerRight + 20; 
    $calY = 40;
    $calWidth = 230;
    
    $month = date('n');
    $year = date('Y');
    $today = date('j');
    
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $firstDayOfWeek = date('w', mktime(0, 0, 0, $month, 1, $year));
    $monthName = date('F Y');
    
    imagettftext($img, 16, 0, $calX + 45, $calY, $colors['black'], $font, strtoupper($monthName));
    $calY += 45;
    
    $days = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
    $colWidth = $calWidth / 7;
    
    // Draw day of the week headers
    foreach ($days as $index => $day) {
        imagettftext($img, 12, 0, $calX + ($index * $colWidth) + 8, $calY, $colors['dark_grey'], $font, $day);
    }
    
    $calY += 30;
    
    $currentCol = $firstDayOfWeek;
    $currentRow = 0;
    $rowHeight = 35;
    
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $x = $calX + ($currentCol * $colWidth);
        $y = $calY + ($currentRow * $rowHeight);
        
        // Highlight today's date
        if ($day == $today) {
            imagefilledrectangle($img, $x + 2, $y - 20, $x + 28, $y + 6, $colors['dark_grey']);
            $textColor = $colors['white'];
        } else {
            $textColor = $colors['black'];
        }
        
        $offset = ($day < 10) ? 10 : 5; 
        imagettftext($img, 12, 0, $x + $offset, $y, $textColor, $font, $day);
        
        $currentCol++;
        if ($currentCol > 6) {
            $currentCol = 0;
            $currentRow++;
        }
    }
}

/**
 * Draws the current moon phase alongside moonrise and moonset times.
 */
function drawMoonData($img, $data, $colors, $font) {
    $moon = $data['properties']['data'] ?? null;
    if (!$moon) return;

    $phase = $moon['curphase'] ?? 'Unknown';
    $rise = '--:--';
    $set = '--:--';

    if (isset($moon['moondata'])) {
        foreach ($moon['moondata'] as $event) {
            if ($event['phen'] === 'Rise') {
                $rise = date('g:ia', strtotime($event['time']));
            } elseif ($event['phen'] === 'Set') {
                $set = date('g:ia', strtotime($event['time']));
            }
        }
    }

    $x = 20; 
    $y = 370;

    imagettftext($img, 12, 0, $x, $y, $colors['dark_grey'], $font, "MOON");
    
    $y += 22;
    imagettftext($img, 12, 0, $x, $y, $colors['black'], $font, $phase);
    
    $y += 22;
    imagettftext($img, 12, 0, $x, $y, $colors['black'], $font, "Rise: " . $rise . "   Set: " . $set);
}

/**
 * Draws the indoor temperature and humidity.
 */
function drawIndoorData($img, $data, $colors, $font) {
    if (isset($data['atmp']) && isset($data['rhum'])) {
        $indoorTempF = round(($data['atmp'] * 9/5) + 32);
        $indoorHum = $data['rhum'];
        $indoorText = "Indoor: " . $indoorTempF . "°   " . $indoorHum . "%";
        
        $x = 23;
        $y = 460;
        imagettftext($img, 13, 0, $x, $y, $colors['black'], $font, $indoorText);
    }
}
?>