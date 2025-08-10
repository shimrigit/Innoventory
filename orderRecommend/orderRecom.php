<?php


function OrderRecommend_Files($shop, $supplier) {
    // Display the file upload form
    echo "<h1>Please upload the inventory files</h1>";
    echo "<form action='orderRecommend/process_OrderRecommend.php' method='post' enctype='multipart/form-data'>";
    // Input field for files
    echo "Purchase file <input type='file' name='purchase' required><br>";
    echo "Sales file <input type='file' name='sales' required><br>";
    echo "Inventory file <input type='file' name='inventory' required><br>";
    echo "Products file <input type='file' name='products' required><br>";
    echo "Suppliers file <input type='file' name='suppliers' required><br>";

     // Calculate default values for OrderDate
     $today = date('Y-m-d'); // Get today's date in the format 'YYYY-MM-DD'
     // Input field for OrderDate
    echo "Order Date: <input type='date' name='orderDate' value='$today' required><br>";
    // Calculate default values for StartDate and 
     $sixWeeksAgo = date('Y-m-d', strtotime('-6 weeks', strtotime($today))); // Calculate 6 weeks ago
    // Input field for StartDate
    echo "Start Date: <input type='date' name='startDate' value='$sixWeeksAgo' required><br>";
    
    
    // hidden fields for shop and supplier values of POST
    echo "<input type='hidden' name='shop' value='" . htmlspecialchars($shop, ENT_QUOTES, 'UTF-8') . "'>";
    echo "<input type='hidden' name='supplier' value='" . htmlspecialchars($supplier, ENT_QUOTES, 'UTF-8') . "'>";
    //hidden fields for upload method 
    echo "<input type='hidden' name='uploadMethod' value='files'>";

    echo "<input type='submit' value='Upload Files'>";
    echo "</form>";
} 

function OrderRecommend_Directory($shop, $supplier) {
    // Display the file upload form
    echo "<h1>Please upload the inventory files</h1>";
    echo "<form action='orderRecommend/process_OrderRecommend.php' method='post' enctype='multipart/form-data'>";

    echo "files will be loaded from JxSet directory <br>";

     // Calculate default values for OrderDate
     $today = date('Y-m-d'); // Get today's date in the format 'YYYY-MM-DD'
     // Input field for OrderDate
    echo "Order Date: <input type='date' name='orderDate' value='$today' required><br>";
    // Calculate default values for StartDate and 
     $sixWeeksAgo = date('Y-m-d', strtotime('-6 weeks', strtotime($today))); // Calculate 6 weeks ago
    // Input field for StartDate
    echo "Start Date: <input type='date' name='startDate' value='$sixWeeksAgo' required><br>";
    
    
    // hidden fields for shop and supplier values of POST
    echo "<input type='hidden' name='shop' value='" . htmlspecialchars($shop, ENT_QUOTES, 'UTF-8') . "'>";
    echo "<input type='hidden' name='supplier' value='" . htmlspecialchars($supplier, ENT_QUOTES, 'UTF-8') . "'>";
    //hidden fields for upload method 
    echo "<input type='hidden' name='uploadMethod' value='directory'>";

    echo "<input type='submit' value='Submit'>";
    echo "</form>";
} 

function StockProgess($shop, $supplier) {
    // Display the file upload form
    echo "<h1>Please upload the inventory files</h1>";
    echo "<form action='orderRecommend/process_OrderSalesRecomm.php' method='post' enctype='multipart/form-data'>";

    echo "files will be loaded from JxSet directory <br>";

     // Calculate default values for OrderDate
     $today = date('Y-m-d'); // Get today's date in the format 'YYYY-MM-DD'
     // Input field for OrderDate
    echo "Order Date: <input type='date' name='orderDate' value='2023-10-01' required><br>"; //value='$today'
    // Calculate default values for StartDate and 
     $sixWeeksAgo = date('Y-m-d', strtotime('-3 weeks', strtotime($today))); // Calculate 3 weeks ago
    // Input field for StartDate
    echo "Start Date: <input type='date' name='startDate' value='2023-08-06' required><br><br>"; //'$sixWeeksAgo'
    // Input field for next visit
    echo "Next visit Date: <input type='date' name='nextVisitDate' value='2023-10-04' required><br>"; //value=in X days based on supplier data
    // Input field for the visit after 
    echo "Visit after the next: <input type='date' name='visitAfterTheNextDate' value='2023-10-08' required><br>"; //value=in X days times twice based on supplier data
    
    
    // hidden fields for shop and supplier values of POST
    echo "<input type='hidden' name='shop' value='" . htmlspecialchars($shop, ENT_QUOTES, 'UTF-8') . "'>";
    echo "<input type='hidden' name='supplier' value='" . htmlspecialchars($supplier, ENT_QUOTES, 'UTF-8') . "'>";
    //hidden fields for upload method 
    echo "<input type='hidden' name='uploadMethod' value='directory'>";

    echo "<input type='submit' value='Submit'>";
    echo "</form>";
} 

?>