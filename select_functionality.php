<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Select Functionality</title>
</head>
<body>
    <?php
    // Check if both shop and supplier were selected in the previous step
    if (isset($_POST['shop']) && isset($_POST['supplier'])) {
        $selectedShop = htmlspecialchars($_POST['shop']);
        $selectedSupplier = htmlspecialchars($_POST['supplier']);

        // Load the JSON data from functions_config.json
        $config = json_decode(file_get_contents('functions_config.json'), true);

        // Find the selected shop's data
        $selectedShopData = null;
        foreach ($config['Shops'] as $shopData) {
            if ($shopData['ShopName'] === $selectedShop) {
                $selectedShopData = $shopData;
                break;
            }
        }

        if ($selectedShopData) {
            echo "<h1>Please select the functionality</h1>";
            echo "<form action='dispatch.php' method='post'>";
            echo "<input type='hidden' name='shop' value='$selectedShop'>";
            echo "<input type='hidden' name='supplier' value='$selectedSupplier'>";
            
            // Display functionalities for the selected supplier
            echo "<ul>";
            foreach ($selectedShopData['Suppliers'] as $supplierData) {
                if ($supplierData['SupplierName'] === $selectedSupplier) {
                    $functionalities = $supplierData['Functionalities'];
                    foreach ($functionalities as $functionality) {
                        echo "<li><label><input type='radio' name='functionality' value='$functionality'>$functionality</label></li>";
                    }
                    break;
                }
            }
            echo "</ul>";
            
            echo "<input type='submit' value='Submit'>";
            echo "</form>";
        } else {
            echo "<p>Invalid selection.</p>";
        }
    } else {
        echo "<p>Invalid selection.</p>";
    }
    ?>
</body>
</html>
