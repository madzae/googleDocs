<?php

$conn = new mysqli('server', 'user', 'pass', 'db');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Escape the content to prevent SQL injection
$documentId = mysqli_real_escape_string($conn, $_POST['documentId']);
$titleDocument = mysqli_real_escape_string($conn, $_POST['titleDocument']);
$firstImage = mysqli_real_escape_string($conn, $_POST['firstImage']);
$subtitle = mysqli_real_escape_string($conn, $_POST['subtitle']);
$writing = mysqli_real_escape_string($conn, $_POST['writing']);

$author = $_POST['author'];
$preSlug = $_POST['titleDocument'];

// Remove any character that is not alphanumeric, space, or hyphen
$cleanTitleDocument = preg_replace('/[^a-zA-Z0-9\s-]/', '', $preSlug);

// Replace multiple spaces or hyphens with a single hyphen, and convert to lowercase
$slug = strtolower(trim(preg_replace('/[\s-]+/', '-', $cleanTitleDocument)));

// Continue with the rest of your code

// Download the image and save it locally
$imageUrl = $firstImage;
$imageContent = file_get_contents($imageUrl);

if ($imageContent !== false) {
    // Define the path to save the original image
    $uniqueId = uniqid();
    $localImagePath = 'illustration/' . $uniqueId;

    // Check image type and add appropriate file extension
    $imageInfo = getimagesizefromstring($imageContent);
    $imageType = $imageInfo[2]; // Get the image type (IMAGETYPE_JPEG, IMAGETYPE_PNG, etc.)

    if ($imageType === IMAGETYPE_JPEG) {
        $localImagePath .= '.jpg';
    } elseif ($imageType === IMAGETYPE_PNG) {
        $localImagePath .= '.png';
    } else {
        die("Unsupported image type.");
    }

    // Save the original image file
    file_put_contents($localImagePath, $imageContent);

    // Create 30% and 50% width thumbnails with updated filenames
    list($originalWidth, $originalHeight) = getimagesize($localImagePath);
    $thumbnail30Path = 'illustration/30_' . $uniqueId . '.' . ($imageType === IMAGETYPE_JPEG ? 'jpg' : 'png');
    $thumbnail50Path = 'illustration/50_' . $uniqueId . '.' . ($imageType === IMAGETYPE_JPEG ? 'jpg' : 'png');

    // Generate 30% and 50% thumbnails
    $width30 = $originalWidth * 0.3;
    $height30 = $originalHeight * 0.3;
    createThumbnail($localImagePath, $thumbnail30Path, $width30, $height30, $imageType);

    $width50 = $originalWidth * 0.5;
    $height50 = $originalHeight * 0.5;
    createThumbnail($localImagePath, $thumbnail50Path, $width50, $height50, $imageType);

    // Update $firstImage to just the filename of the original image
    $firstImage = basename($localImagePath);

} else {
    die("Failed to download the image.");
}

// Function to create a thumbnail
function createThumbnail($sourcePath, $destinationPath, $newWidth, $newHeight, $imageType) {
    // Check image type and use the appropriate function
    if ($imageType === IMAGETYPE_JPEG) {
        $sourceImage = imagecreatefromjpeg($sourcePath);
    } elseif ($imageType === IMAGETYPE_PNG) {
        $sourceImage = imagecreatefrompng($sourcePath);
    } else {
        die("Unsupported image type for thumbnail creation.");
    }

    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($sourceImage), imagesy($sourceImage));

    // Save thumbnail in the appropriate format
    if ($imageType === IMAGETYPE_JPEG) {
        imagejpeg($thumbnail, $destinationPath);
    } elseif ($imageType === IMAGETYPE_PNG) {
        imagepng($thumbnail, $destinationPath);
    }

    imagedestroy($sourceImage);
    imagedestroy($thumbnail);
}

// Insert the data into the database
$sql = "INSERT INTO googleDocs (document_id, slug, author, title, first_image, subtitle, content)
        VALUES ('$documentId', '$slug', '$author', '$titleDocument', '$firstImage', '$subtitle', '$writing')";

if (mysqli_query($conn, $sql)) {
    echo "Document successfully inserted!";
} else {
    echo "Error: " . mysqli_error($conn);
}

// Close the connection
$conn->close();
?>
