<?php
$dir = "../preProcessDir";
$pdfFiles = glob($dir . "/*.pdf");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice Pre-Processing</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
    h2 { color: #333; }
    .pdf-block {
      background-color: white;
      border: 1px solid #ddd;
      margin-bottom: 30px;
      padding: 15px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    embed { width: 50%; height: 500px; margin-bottom: 10px; border: 1px solid #ccc; }
    .input-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 10px;
      align-items: center;
    }
    label { font-weight: bold; color: #555; }

    select, input[type="date"], input[list] {
      padding: 6px 8px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 14px;
    }

    .submit-btn {
      margin-top: 30px;
      padding: 12px 30px;
      font-size: 16px;
      cursor: pointer;
      background-color: #4CAF50;
      color: white;
      border: none;
      border-radius: 5px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .submit-btn:hover {
      background-color: #45a049;
    }

    /* Toggle Switch Styling */
    .switch {
      position: relative;
      display: inline-block;
      width: 40px;
      height: 22px;
    }

    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .slider {
      position: absolute;
      cursor: pointer;
      top: 0; left: 0; right: 0; bottom: 0;
      background-color: #ccc;
      transition: 0.4s;
      border-radius: 34px;
    }

    .slider:before {
      position: absolute;
      content: "";
      height: 16px;
      width: 16px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: 0.4s;
      border-radius: 50%;
    }

    input:checked + .slider {
      background-color: #f44336;
    }

    input:checked + .slider:before {
      transform: translateX(18px);
    }

    .pdf-filename {
      font-size: 12px;
      color: #666;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>

  <h2>Invoice Pre-Processing - Append and Rename PDFs</h2>
  <p style="color: #666; margin-bottom: 20px;">
    Combine multiple PDF pages into single invoices and rename them with proper naming convention.
  </p>

  <form action="AppendAndRename.php" method="post">
    <datalist id="suppliers"></datalist>

    <?php
    $index = 1;
    foreach ($pdfFiles as $pdfPath):
      $pdfName = basename($pdfPath);
      $embedSrc = "../preProcessDir/" . htmlspecialchars($pdfName);
    ?>
    <div class="pdf-block">
      <div class="pdf-filename">
        <strong>File:</strong> <?= htmlspecialchars($pdfName) ?>
      </div>
      <embed src="<?= $embedSrc ?>" type="application/pdf">
      <input type="hidden" name="fileName_<?= $index ?>" value="<?= htmlspecialchars($pdfName) ?>">

      <div class="input-row">
        <div>
          <label>Supplier:</label><br>
          <input list="suppliers" name="hebrewName_<?= $index ?>" placeholder="Select supplier...">
        </div>

        <div>
          <label>Letter (A–J):</label><br>
          <select name="englishLetter_<?= $index ?>">
            <?php foreach (range('A', 'J') as $char): ?>
              <option><?= $char ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Page Number:</label><br>
          <select name="pageNumber_<?= $index ?>">
            <?php for ($i = 1; $i <= 20; $i++): ?>
              <option<?= $i === 1 ? ' selected' : '' ?>><?= $i ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <div>
          <label>Date:</label><br>
          <input type="date" name="date_<?= $index ?>" id="date_<?= $index ?>">
        </div>

        <div>
          <label>Discard:</label><br>
          <label class="switch">
            <input type="checkbox" name="discard_<?= $index ?>" value="yes">
            <span class="slider round"></span>
          </label>
        </div>
      </div>
    </div>
    <?php $index++; endforeach; ?>

    <input type="hidden" name="totalFiles" value="<?= $index - 1 ?>">
    <button class="submit-btn" type="submit">Process Invoices</button>
  </form>

  <script>
    const today = new Date().toISOString().split('T')[0];
    <?php for ($i = 1; $i < $index; $i++): ?>
      document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('date_<?= $i ?>').value = today;
      });
    <?php endfor; ?>

    fetch('../suppliers.json')
      .then(response => response.json())
      .then(suppliers => {
        const datalist = document.getElementById('suppliers');
        suppliers.forEach(supplier => {
          const option = document.createElement('option');
          option.value = supplier.hebrewName;
          datalist.appendChild(option);
        });
      })
      .catch(error => {
        console.error('Failed to load suppliers.json:', error);
      });
  </script>

</body>
</html>
