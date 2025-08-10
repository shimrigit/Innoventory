<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['jsonFile'])) {
    $fileTmpPath = $_FILES['jsonFile']['tmp_name'];
    $originalName = pathinfo($_FILES['jsonFile']['name'], PATHINFO_FILENAME);
    $simplifiedName = $originalName . '_smpl.json';

    $jsonContent = file_get_contents($fileTmpPath);
    $data = json_decode($jsonContent, true);

    // Check if GDocument->fields exists
    if (isset($data['GDocument']['fields']) && is_array($data['GDocument']['fields'])) {
        $simplifiedFields = [];

        foreach ($data['GDocument']['fields'] as $field) {
            $simplifiedFields[] = [
                'id' => $field['id'] ?? null,
                'name' => $field['name'] ?? '',
                'value' => $field['value'] ?? ''
            ];
        }

        $output = ['fields' => $simplifiedFields];
        $jsonOutput = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Send headers for download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $simplifiedName . '"');
        echo $jsonOutput;
        exit;
    } else {
        echo "Invalid JSON format: 'GDocument->fields' not found.";
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>JSON Field Simplifier</title>
</head>
<body>
    <h2>Upload a JSON file to extract top-level GDocument->fields</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="jsonFile" accept=".json" required>
        <button type="submit">Upload and Simplify</button>
    </form>
</body>
</html>
