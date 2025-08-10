<?php
$dir = "../preProcessDir";
$pdfFiles = glob($dir . "/*.pdf");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Multi pages PDF Viewer</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .pdf-block { border: 1px solid #ccc; margin-bottom: 30px; padding: 15px; border-radius: 8px; }
    embed { width: 50%; height: 500px; margin-bottom: 10px; }
    .input-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; align-items: center; }
    label { font-weight: bold; }

    select, input[type="date"], input[list] {
      padding: 5px;
    }

    .submit-btn {
      margin-top: 30px;
      padding: 10px 20px;
      font-size: 16px;
      cursor: pointer;
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
  </style>
</head>
<body>

  <h2>Multi pages PDF Viewer</h2>

  <form action="ConcatMultiPageInvoice.php" method="post">
    <datalist id="suppliers"></datalist>

    <?php
    $index = 1;
    foreach ($pdfFiles as $pdfPath):
      $pdfName = basename($pdfPath);
      $embedSrc = "../preProcessDir/" . htmlspecialchars($pdfName);
    ?>
    <div class="pdf-block">
      <embed src="<?= $embedSrc ?>" type="application/pdf">
      <input type="hidden" name="fileName_<?= $index ?>" value="<?= htmlspecialchars($pdfName) ?>">

      <div class="input-row">
        <div>
          <label>Supplier:</label><br>
          <input list="suppliers" name="hebrewName_<?= $index ?>">
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
    <button class="submit-btn" type="submit">Submit</button>
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
