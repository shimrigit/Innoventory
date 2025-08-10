<?php
if (isset($_POST['shop']) && isset($_POST['supplier']) && isset($_POST['functionality'])) {
    $selectedShop = htmlspecialchars($_POST['shop']);
    $selectedSupplier = htmlspecialchars($_POST['supplier']);
    $selectedFunctionality = htmlspecialchars($_POST['functionality']);

    //for debug purposes 
    echo $selectedShop."<br>";
    echo $selectedSupplier."<br>";
    echo $selectedFunctionality."<br>";

    // Include the functions list file
    include 'functions_list.php';
    //include 'ConvertToComax.php';
    //include 'PurchaseConvert.php';
    include 'functions/convertJsonToExcel.php';
    include 'orderRecommend/orderRecom.php';

    // Call the function based on the selected functionality
    $functionName = $selectedFunctionality;

    if (function_exists($functionName)) {
        $result = call_user_func($functionName, $selectedShop, $selectedSupplier);

    } else {
        echo "<p>Functionality not found.</p>";
    }
} else {
    echo "<p>Invalid selection.</p>";
}
?>

