<?php
$conn = new mysqli('server', 'user', 'pass', 'db');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function getAccessToken($refreshToken) {
    $clientId = 'yourclientid';
    $clientSecret = 'yourclientsecret';
    $tokenUrl = 'https://oauth2.googleapis.com/token';

    $postData = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1); // Force HTTP/1.1

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error: ' . curl_error($ch);
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo 'Failed to get access token. HTTP Code: ' . $httpCode;
        return null;
    }

    $responseData = json_decode($response, true);
    return $responseData['access_token'] ?? null;
}

function getFileOwner($fileId, $accessToken) {
    $url = 'https://www.googleapis.com/drive/v3/files/' . $fileId . '?fields=owners';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1); // Force HTTP/1.1

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error: ' . curl_error($ch);
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo 'Failed to get file owner. HTTP Code: ' . $httpCode;
        return 'Unknown';
    }

    $fileData = json_decode($response, true);
    return $fileData['owners'][0]['emailAddress'] ?? null;
}

function getAuthorName($conn, $email) {
    $stmt = $conn->prepare("SELECT name FROM googleDocsAuthor WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($name);
    $stmt->fetch();
    $stmt->close();

    return $name ?: null; // Return null if no match
}

function getDocumentStatus($conn, $documentId) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM googleDocs WHERE document_id = ?");
    $stmt->bind_param("s", $documentId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return $count > 0 ? 'Published' : 'Draft';
}

// Fetch the refresh token
$refreshToken = trim(file_get_contents('docsToken.txt'));
if (!$refreshToken) {
    echo 'Refresh token is missing or invalid.';
    exit;
}

$accessToken = getAccessToken($refreshToken);

if ($accessToken) {
    $url = 'https://www.googleapis.com/drive/v3/files?q=mimeType="application/vnd.google-apps.document"&fields=files(id,name)';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error: ' . curl_error($ch);
        $files = null;
    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode === 200) {
            $files = json_decode($response, true);
        } else {
            echo 'Failed to fetch files. HTTP Code: ' . $httpCode;
            $files = null;
        }
    }
    curl_close($ch);
} else {
    echo 'Failed to get access token.';
    $files = null;
}

$titleDocument = 'Google Docs CMS';
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <title>Control Panel</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      margin: 0 auto;
      width: 100%;
      max-width: 1600px;
      height: 100%;
      font-family: Arial, sans-serif;
    }

    .content {
      width: 900px;
      margin: 20px auto;
      padding: 20px;
    }

    h1 {
      text-align: center;
      font-size: 24px;
      margin-bottom: 20px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin: 20px 0;
    }

    th, td {
      text-align: left;
      padding: 12px;
      border-bottom: 1px solid #eaeaea;
    }

    th {
      background-color: #f2f2f2;
      cursor: pointer;
    }

    th:hover {
      background-color: #ddd;
    }

    tr:nth-child(even) {
      background-color: #f9f9f9;
    }

    tr:hover {
      background-color: #f1f1f1;
    }

    a {
      text-decoration: none;
      color: black;
    }

    a:hover {
      text-decoration: underline;
    }
  </style>
  <script>
    function sortTable(columnIndex, isNumeric = false) {
      const table = document.querySelector('table tbody');
      const rows = Array.from(table.rows);
      const isAscending = table.getAttribute('data-sort-order') !== 'asc';

      rows.sort((rowA, rowB) => {
        const cellA = rowA.cells[columnIndex].textContent.toLowerCase();
        const cellB = rowB.cells[columnIndex].textContent.toLowerCase();

        if (isNumeric) {
          return isAscending
            ? Number(cellA) - Number(cellB)
            : Number(cellB) - Number(cellA);
        }
        return isAscending
          ? cellA.localeCompare(cellB)
          : cellB.localeCompare(cellA);
      });

      table.innerHTML = '';
      rows.forEach(row => table.appendChild(row));
      table.setAttribute('data-sort-order', isAscending ? 'asc' : 'desc');
    }
  </script>
</head>
<body>
    <div class="content">
      <h1><?php echo htmlspecialchars($titleDocument); ?></h1>
      <?php if ($files && isset($files['files'])): ?>
        <table>
          <thead>
            <tr>
              <th onclick="sortTable(0, true)">No</th>
              <th onclick="sortTable(1)">Title</th>
              <th onclick="sortTable(2)">Author</th>
              <th onclick="sortTable(3)">Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody data-sort-order="asc">
            <?php
            $no = 1;
            foreach ($files['files'] as $file):
                $ownerEmail = getFileOwner($file['id'], $accessToken);
                $authorName = getAuthorName($conn, $ownerEmail);
                if (!$authorName) {
                    continue;
                }
                $status = getDocumentStatus($conn, $file['id']);
                $googleDocsLink = "https://docs.google.com/document/d/{$file['id']}/edit";
            ?>
              <tr>
                <td><?php echo $no++; ?></td>
                <td><a href="<?php echo htmlspecialchars($googleDocsLink); ?>" target="_blank"><?php echo htmlspecialchars($file['name']); ?></a></td>
                <td><?php echo htmlspecialchars($authorName); ?></td>
                <td><?php echo htmlspecialchars($status); ?></td>
                <td>
                  <a href="viewDocs.php?id=<?php echo htmlspecialchars($file['id']); ?>&author=<?php echo urlencode($ownerEmail); ?>" target="_blank">View</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No documents found or an error occurred.</p>
      <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>
