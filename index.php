<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Select Shop</title>
</head>
<body>
    <h1>Please select the shop</h1>
    <form action="select_supplier.php" method="post">
        <select name="shop">
            <?php
            // Load the JSON data from functions_config.json
            $config = json_decode(file_get_contents('functions_config.json'), true);

            // Display shop names from the JSON data
            if (isset($config['Shops'])) {
                foreach ($config['Shops'] as $shopData) {
                    $shopName = $shopData['ShopName'];
                    echo "<option value='$shopName'>$shopName</option>";
                }
            }
            ?>
        </select>
        <input type="submit" value="Submit">
    </form>
</body>
</html>