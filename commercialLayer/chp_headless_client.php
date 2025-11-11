<?php
/**
 * CHP.co.il Headless Browser Client
 * Uses Puppeteer (Node.js) to scrape price data from CHP website
 */

class CHPHeadlessClient {
    private $scriptPath;

    public function __construct() {
        $this->scriptPath = __DIR__ . '/chp_scraper.js';
    }

    /**
     * Search for product prices by barcode and city
     *
     * @param string $barcode Product barcode
     * @param string $city City name in Hebrew
     * @return array|false Results array or false on failure
     */
    public function searchPrices($barcode, $city) {
        if (!file_exists($this->scriptPath)) {
            echo "Error: chp_scraper.js not found at: {$this->scriptPath}\n";
            return false;
        }

        // Check if node is available
        $nodeCheck = shell_exec('node --version 2>&1');
        if (empty($nodeCheck)) {
            echo "Error: Node.js is not installed or not in PATH\n";
            return false;
        }

        echo "Using Node.js version: " . trim($nodeCheck) . "\n";
        echo "Launching headless browser...\n";
        echo "Searching for barcode: $barcode in city: $city\n\n";

        // Escape arguments for shell
        $escapedBarcode = escapeshellarg($barcode);
        $escapedCity = escapeshellarg($city);

        // Build command
        $command = "node " . escapeshellarg($this->scriptPath) . " $escapedBarcode $escapedCity 2>&1";

        // Execute
        $output = shell_exec($command);

        if ($output === null) {
            echo "Error: Failed to execute Node.js script\n";
            return false;
        }

        // Split output into stderr (debug) and stdout (JSON)
        $lines = explode("\n", $output);
        $jsonOutput = '';
        $debugOutput = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Lines starting with specific prefixes are debug messages
            if (preg_match('/^(Navigating|Page loaded|Entering|Clicking|Waiting|Extracting|Screenshot|Starting|Search|Error:)/i', $line)) {
                $debugOutput[] = $line;
            } else {
                $jsonOutput .= $line;
            }
        }

        // Show debug output
        if (!empty($debugOutput)) {
            echo "=== Browser Debug Output ===\n";
            echo implode("\n", $debugOutput) . "\n";
            echo "============================\n\n";
        }

        // Parse JSON
        $data = json_decode($jsonOutput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "Error parsing JSON: " . json_last_error_msg() . "\n";
            echo "Raw output:\n";
            echo $output . "\n";
            return false;
        }

        return $data;
    }

    /**
     * Display results in a formatted way
     */
    public function displayResults($results) {
        if (!$results) {
            echo "No results to display\n";
            return;
        }

        echo "\n" . str_repeat('=', 80) . "\n";
        echo "CHP PRICE COMPARISON RESULTS (via Headless Browser)\n";
        echo str_repeat('=', 80) . "\n\n";

        if (isset($results['productName'])) {
            echo "Product: {$results['productName']}\n\n";
        }

        if (!empty($results['prices'])) {
            echo str_pad("STORE", 40) . str_pad("PRICE", 20) . "\n";
            echo str_repeat('-', 60) . "\n";

            foreach ($results['prices'] as $price) {
                $store = substr($price['store'] ?? 'Unknown', 0, 38);
                $priceStr = $price['price'] ?? 'N/A';
                echo str_pad($store, 40) . str_pad($priceStr, 20) . "\n";
            }

            echo "\nFound " . count($results['prices']) . " price(s)\n";
        } else {
            echo "No prices found\n";

            if (isset($results['html'])) {
                echo "\nHTML Preview (first 500 chars):\n";
                echo substr($results['html'], 0, 500) . "\n";
            }
        }

        echo "\n" . str_repeat('=', 80) . "\n";
    }
}

// ============================================================================
// USAGE EXAMPLE
// ============================================================================

if (php_sapi_name() === 'cli') {
    echo "=== CHP Headless Browser Test ===\n\n";

    $client = new CHPHeadlessClient();

    $barcode = $argv[1] ?? '7290000554532';
    $city = $argv[2] ?? 'קרית אונו';

    $results = $client->searchPrices($barcode, $city);

    if ($results) {
        $client->displayResults($results);

        // Save to file
        $jsonFile = __DIR__ . '/chp_results.json';
        file_put_contents($jsonFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "\nResults saved to: $jsonFile\n";
    } else {
        echo "\nFailed to retrieve results\n";
    }
}
?>
