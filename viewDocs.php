<?php

function getAccessToken($refreshToken) {
    $clientId = 'yourclientid';
    $clientSecret = 'yoursecretid';
    $tokenUrl = 'https://oauth2.googleapis.com/token';

    $postData = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
        curl_close($ch);
        return null;
    }

    curl_close($ch);
    $responseData = json_decode($response, true);
    return $responseData['access_token'] ?? null;
}

function fetchFormattedAddress($lat, $lon) {
    $apiKey = 'api'; // Replace with your actual API key
    $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=$lat,$lon&location_type=ROOFTOP&result_type=street_address&key=$apiKey";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($data['status'] == 'OK') {
        return $data['results'][0]['formatted_address'];
    } else {
        return null;
    }
}

function parseBBCode($text) {
    // Replace [LINK]...[/LINK] with a div containing the link
    $text = preg_replace_callback('/\[LINK\](.*?)\[\/LINK\]/', function ($matches) {
        $url = htmlspecialchars($matches[1]);
        return '<div class="link">This is link: <a href="' . $url . '" target="_blank">' . $url . '</a></div>';
    }, $text);

    // Replace [BUKU]...[/BUKU] with a div for book links or similar future BBCode tags
    $text = preg_replace_callback('/\[BUKU\](.*?)\[\/BUKU\]/', function ($matches) {
        $url = htmlspecialchars($matches[1]);
        return '<div class="book-link">Link to the book: <a href="' . $url . '" target="_blank">' . $url . '</a></div>';
    }, $text);

    // Replace [YOUTUBE]...[/YOUTUBE] with an iframe for embedding YouTube videos
    $text = preg_replace_callback('/\[YOUTUBE\](.*?)\[\/YOUTUBE\]/', function ($matches) {
        $url = htmlspecialchars($matches[1]);

        // Extract the YouTube video ID from the URL
        parse_str(parse_url($url, PHP_URL_QUERY), $queryParams);
        $videoId = $queryParams['v'] ?? '';

        if ($videoId) {
            return '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $videoId . '?si=tACf01UF3Scbl6qV" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>';
        } else {
            return '<div class="error">Invalid YouTube link provided.</div>';
        }
    }, $text);

    // Replace [MAP]...[/MAP] with an iframe for embedding Google Maps and show formatted address below
    $text = preg_replace_callback('/\[MAP\](.*?)\[\/MAP\]/', function ($matches) {
        $url = htmlspecialchars($matches[1]);

        // Extract the latitude and longitude from the URL
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $coords)) {
            $lat = $coords[1];
            $lon = $coords[2];

            // Fetch the formatted address from the Geocoding API
            $formattedAddress = fetchFormattedAddress($lat, $lon);

            // Construct the Google Maps embed URL using the extracted coordinates
            $embedUrl = 'https://www.google.com/maps/embed/v1/view?key=apikey&center=' . $lat . ',' . $lon . '&zoom=19';

            // Embed the map and add the formatted address below it
            $output = '<iframe width="600" height="450" style="border:0" loading="lazy" allowfullscreen src="' . $embedUrl . '"></iframe>';
            if ($formattedAddress) {
                $output .= '<p><em>' . htmlspecialchars($formattedAddress) . '</em></p>';
            } else {
                $output .= '<p><em>Address not found</em></p>';
            }

            return $output;
        } else {
            return '<div class="error">Invalid Google Maps link provided.</div>';
        }
    }, $text);

    // Add more BBCode conversions here as needed

    return $text;
}

function renderGoogleDoc($document, &$firstImage, &$titleDocument, &$subtitle) {
    $content = '';
    $inlineObjects = $document['inlineObjects'] ?? [];
    $firstImage = null;
    $titleDocument = null;
    $subtitle = null;

    foreach ($document['body']['content'] as $element) {
        if (isset($element['paragraph'])) {
            $paragraph = $element['paragraph'];
            $paragraphContent = '';

            foreach ($paragraph['elements'] as $elem) {
                if (isset($elem['textRun'])) {
                    $textRun = $elem['textRun'];
                    $text = htmlspecialchars($textRun['content']);
                    $styles = $textRun['textStyle'] ?? [];

                    if (isset($styles['bold'])) {
                        $text = "<b>$text</b>";
                    }
                    if (isset($styles['italic'])) {
                        $text = "<i>$text</i>";
                    }
                    if (isset($styles['underline'])) {
                        $text = "<u>$text</u>";
                    }
                    if (isset($styles['link'])) {
                        $url = htmlspecialchars($styles['link']['url']);
                        $text = "<a href=\"$url\" target=\"_blank\">$text</a>";
                    }

                    $paragraphContent .= $text;
                } elseif (isset($elem['inlineObjectElement'])) {
                    $inlineObjectId = $elem['inlineObjectElement']['inlineObjectId'];
                    if (isset($inlineObjects[$inlineObjectId])) {
                        $inlineObject = $inlineObjects[$inlineObjectId];
                        $embeddedObject = $inlineObject['inlineObjectProperties']['embeddedObject'];
                        $imageUri = $embeddedObject['imageProperties']['contentUri'];
                        $description = isset($embeddedObject['description']) ? htmlspecialchars($embeddedObject['description']) : '';

                        if (!$firstImage) {
                            // Capture the first image URI but do not add it to the content
                            $firstImage = $imageUri;
                        } else {
                            // Add subsequent images to the content
                            $paragraphContent .= "<img src=\"$imageUri\" alt=\"Embedded Image\" style=\"width: 600px; height: auto;\" />";
                            if ($description) {
                                $paragraphContent .= "<p><em>$description</em></p>";
                            }
                        }
                    }
                }
            }

            $paragraphStyle = $paragraph['paragraphStyle'] ?? [];
            if (isset($paragraphStyle['namedStyleType'])) {
                $styleType = $paragraphStyle['namedStyleType'];
                if ($styleType === 'TITLE' && !$titleDocument) {
                    // Capture the title and skip adding it to content
                    $titleDocument = $paragraphContent;
                    continue;
                } elseif ($styleType === 'SUBTITLE' && !$subtitle) {
                    // Capture the subtitle and skip adding it to content
                    $subtitle = $paragraphContent;
                    continue;
                } elseif ($styleType === 'HEADING_1') {
                    $content .= "<h2>$paragraphContent</h2>";
                } elseif ($styleType === 'HEADING_2') {
                    $content .= "<h3>$paragraphContent</h3>";
                } else {
                    if (isset($paragraph['bullet'])) {
                        $content .= "<li>$paragraphContent</li>";
                    } else {
                        $content .= "<p>$paragraphContent</p>";
                    }
                }
            }
        }
    }

    // Remove empty <p></p> tags
    $content = preg_replace('/<p>\s*<\/p>/', '', $content);

    // Parse BBCode in the content
    $content = parseBBCode($content);

    return $content;
}

$refreshToken = trim(file_get_contents('docsToken.txt'));
$accessToken = getAccessToken($refreshToken);

if ($accessToken && isset($_GET['id'])) {
    $documentId = $_GET['id'];

    $url = 'https://docs.googleapis.com/v1/documents/' . $documentId;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    } else {
        $document = json_decode($response, true);

        $firstImage = null;
        $titleDocument = null;
        $subtitle = null;

        $writing = renderGoogleDoc($document, $firstImage, $titleDocument, $subtitle);

        //echo $writing;
    }

    curl_close($ch);
} else {
    echo 'Failed to get access token or document ID.';
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <title><?php echo $titleDocument; ?></title>
  <meta content="text/html; charset=utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=0">
  <style>

    body {
      margin: 0 auto;
      width: 100%;
      max-width: 1600px;
      height: 100%;
    }

    .container {
      width: 80%;
      margin: 0 auto;
    }

    .content {
      width: 800px;
      padding: 20px;
      margin: 0 auto;
    }

    .textTitle {
      text-align: center;
      font-size: 38px;
    }

    .textSubtitle {
      text-align: center;
      font-size: 18px;
      font-weight: lighter;
      margin-top: -14px;
    }

    .textIllustration {
      width: 100%;
      height: auto;
      margin: 0 auto;
      align-content: center;
    }

    .textMain {
      font-size: 22px;
    }

    .textMain li {
      margin-left: 50px;
    }

    .textMain h1 {
      font-size: 30px !important;
    }

    .textMain h2 {
      font-size: 30px !important;
    }

    .textMain iframe {
      width: 80% !important;
      height: 400px !important;
      display: block;
      margin: 0 auto;
    }

    .textMain img {
      width: 80% !important;
      height: auto !important;
      display: block; /* Ensures the image is treated as a block-level element */
      margin: 0 auto; /* This centers the image horizontally */
    }

    .textMain em {
      font-size: 16px;
      display: block; /* Makes the <em> element a block-level element */
      text-align: center !important; /* Centers the text inside the <em> element */
      margin: 0 auto; /* Centers the <em> element itself */
      margin-top: -6px;
    }

    .postDocs {
      width: 200px;
      margin-top: 20px;
      margin-right: 20px;
      position: fixed;
      right: 0;
    }

    .button-31 {
      background-color: #222;
      border-radius: 20px;
      border-style: none;
      box-sizing: border-box;
      color: #fff;
      cursor: pointer;
      display: inline-block;
      font-family: "Farfetch Basis","Helvetica Neue",Arial,sans-serif;
      font-size: 16px;
      font-weight: 700;
      line-height: 1.5;
      margin: 0;
      max-width: none;
      min-height: 44px;
      min-width: 10px;
      outline: none;
      overflow: hidden;
      padding: 9px 20px 8px;
      position: relative;
      text-align: center;
      text-transform: none;
      user-select: none;
      -webkit-user-select: none;
      touch-action: manipulation;
      width: 100%;
    }

    .button-31:hover,
    .button-31:focus {
      opacity: .75;
    }

    .button-32 {
      background-color: #fff;
      border-radius: 20px;
      border-style: none;
      box-sizing: border-box;
      color: #222;
      cursor: pointer;
      display: inline-block;
      font-family: "Farfetch Basis","Helvetica Neue",Arial,sans-serif;
      font-size: 16px;
      font-weight: 700;
      line-height: 1.5;
      margin: 0;
      max-width: none;
      min-height: 44px;
      min-width: 10px;
      outline: none;
      overflow: hidden;
      padding: 9px 20px 8px;
      position: relative;
      text-align: center;
      text-transform: none;
      user-select: none;
      -webkit-user-select: none;
      touch-action: manipulation;
      width: 100%;
      border: 1px solid #222;
    }

    .button-32:hover,
    .button-32:focus {
      opacity: .75;
    }

  </style>
</head>
<body>
  <div class="postDocs">
    <form action="postDocs.php" method="post">
        <input type="hidden" name="titleDocument" value="<?php echo htmlspecialchars($titleDocument); ?>" />
        <input type="hidden" name="firstImage" value="<?php echo htmlspecialchars($firstImage); ?>" />
        <input type="hidden" name="subtitle" value="<?php echo htmlspecialchars($subtitle); ?>" />
        <input type="hidden" name="writing" value="<?php echo htmlspecialchars($writing); ?>" />
        <input type="hidden" name="documentId" value="<?php echo htmlspecialchars($documentId); ?>" />
        <input type="hidden" name="author" value="<?php echo $_GET['author']; ?>" />
        <button type="submit" class="button-31">Publish</button>
    </form>
    <a href="https://docs.google.com/document/d/<?php echo $documentId; ?>" target="_blank">
        <button class="button-32" style="margin-top: 10px;">Edit</button>
    </a>
  </div>
  <div class="container">
    <div class="content">
      <h1 class="textTitle"><?php echo $titleDocument; ?></h1>
      <h2 class="textSubtitle"><?php echo $subtitle; ?></h2>
      <br />
      <img src="<?php echo $firstImage; ?>" class="textIllustration">
        <div class="textMain">
          <?php echo $writing; ?>
        </div>
    </div>
  </div>
  <br /><br /><br />
</body>
</html>
