<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Select Supplier</title>
</head>
<body>
    <?php
    // Check if a shop was selected in the previous step
    if (isset($_POST['shop'])) {
        $selectedShop = $_POST['shop'];

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
            echo "<h1>Please select the supplier</h1>";
            echo "<form action='select_functionality.php' method='post'>";
            echo "<input type='hidden' name='shop' value='$selectedShop'>";
            
            // Display suppliers (SupplierName) for the selected shop
            echo "<select name='supplier'>";
            foreach ($selectedShopData['Suppliers'] as $supplierData) {
                $supplierName = $supplierData['SupplierName'];
                echo "<option value='$supplierName'>$supplierName</option>";
            }
            echo "</select>";
            
            echo "<input type='submit' value='Submit'>";
            echo "</form>";
        } else {
            echo "<p>Invalid shop selection.</p>";
        }
    } else {
        echo "<p>No shop selected.</p>";
    }
    ?>
</body>
</html>



