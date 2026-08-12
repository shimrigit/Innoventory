/**
 * CHP.co.il Web Scraper using Puppeteer
 * Automates the search process to extract product and price data
 */

// Use Puppeteer from centralized vendor directory
const path = require('path');
const vendorPath = path.resolve(__dirname, '../vendor/node_modules');
const puppeteer = require(path.join(vendorPath, 'puppeteer'));

async function searchCHP(barcode, city) {
    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    try {
        const page = await browser.newPage();

        // Set viewport
        await page.setViewport({ width: 1280, height: 800 });

        console.error('Navigating to CHP.co.il...');
        await page.goto('https://chp.co.il', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        console.error('Page loaded. Looking for search inputs...');

        // Wait for both input fields to appear
        await page.waitForSelector('input[type="text"]', { timeout: 10000 });

        // Get all text input fields
        const inputs = await page.$$('input[type="text"]');

        if (inputs.length >= 2) {
            // First input is for city/location (upper box)
            console.error(`Entering city in first input: ${city}`);
            await inputs[0].click();
            // Settle delay before typing — without this, the very first
            // keystroke can be dropped by the page's focus/autocomplete init
            // (race condition), silently truncating the typed value by one
            // character. 300ms verified with a large safety margin (the
            // drop stopped occurring at delays as low as 50ms in testing).
            await new Promise(resolve => setTimeout(resolve, 300));
            await inputs[0].type(city, { delay: 100 });
            await new Promise(resolve => setTimeout(resolve, 1500));

            // Second input is for barcode/product (lower box)
            console.error(`Entering barcode in second input: ${barcode}`);
            await inputs[1].click();
            await new Promise(resolve => setTimeout(resolve, 300));
            await inputs[1].type(barcode, { delay: 100 });
            await new Promise(resolve => setTimeout(resolve, 1000));
        } else {
            console.error('Warning: Expected 2 input fields, found:', inputs.length);
        }

        // Click the "בדוק" button
        console.error('Clicking בדוק button...');
        const buttonClicked = await page.evaluate(() => {
            const buttons = Array.from(document.querySelectorAll('button, input[type="button"], input[type="submit"]'));
            const bdokButton = buttons.find(btn =>
                btn.textContent.includes('בדוק') ||
                btn.value === 'בדוק' ||
                btn.innerText === 'בדוק'
            );
            if (bdokButton) {
                bdokButton.click();
                return true;
            }
            return false;
        });

        if (!buttonClicked) {
            console.error('Warning: Could not find בדוק button, trying Enter key');
            await page.keyboard.press('Enter');
        }

        // Wait for results to load
        console.error('Waiting for results...');
        await new Promise(resolve => setTimeout(resolve, 5000));

        // Extract product data
        console.error('Extracting product data...');
        const results = await page.evaluate(() => {
            const data = {
                products: [],
                prices: [],
                html: document.body.innerHTML.substring(0, 5000),
                productInfo: ''
            };

            // Extract product info string (name + manufacturer + barcode)
            const displayedEl = document.querySelector('#displayed_product_name_and_contents');
            if (displayedEl && displayedEl.parentElement) {
                const parent = displayedEl.parentElement;
                const fullText = parent.textContent.trim();

                // Extract just the product info line (everything before "הוסף לרשימה")
                const match = fullText.match(/^([^]+?)\s*(?:הוסף לרשימה|מחירים ב)/);
                if (match) {
                    data.productInfo = match[1].trim();
                }
            }

            // Also keep productName for backward compatibility
            data.productName = data.productInfo;

            // Try to find price table rows
            // Expected columns: רשת (chain), שם החנות (store name), כתובת (address), מבצע (promotion), מחיר (price)
            const priceRows = document.querySelectorAll('.results-table tr, table tr, .price-row');
            priceRows.forEach((row, rowIndex) => {
                const cells = row.querySelectorAll('td');

                // Skip header rows
                if (cells.length === 0 || row.querySelector('th')) {
                    return;
                }

                if (cells.length >= 5) {
                    // Standard 5-column format
                    const chain = cells[0]?.textContent.trim();
                    const storeName = cells[1]?.textContent.trim();
                    const address = cells[2]?.textContent.trim();
                    const promotion = cells[3]?.textContent.trim();
                    const price = cells[4]?.textContent.trim();

                    if (chain || storeName) {
                        data.prices.push({
                            chain: chain || '',
                            store: storeName || chain,
                            address: address || '',
                            promotion: promotion || '',
                            price: price || ''
                        });
                    }
                } else if (cells.length >= 3) {
                    // Fallback for different format: try to find price column
                    const store = cells[0]?.textContent.trim();
                    let priceCell = '';

                    // Find the cell with price (contains ₪ or numbers)
                    for (let i = cells.length - 1; i >= 1; i--) {
                        const text = cells[i]?.textContent.trim();
                        if (text && (text.includes('₪') || /^\d+[\d.,]*/.test(text))) {
                            priceCell = text;
                            break;
                        }
                    }

                    if (store) {
                        data.prices.push({
                            store,
                            address: cells[1]?.textContent.trim() || '',
                            price: priceCell
                        });
                    }
                }
            });

            // Try alternative selectors
            if (data.prices.length === 0) {
                const storeEls = document.querySelectorAll('[class*="store"], [class*="chain"]');
                const priceEls = document.querySelectorAll('[class*="price"]');

                storeEls.forEach((storeEl, i) => {
                    if (priceEls[i]) {
                        data.prices.push({
                            store: storeEl.textContent.trim(),
                            price: priceEls[i].textContent.trim()
                        });
                    }
                });
            }

            return data;
        });

        // Output results as JSON
        console.log(JSON.stringify(results, null, 2));

        return results;

    } catch (error) {
        console.error('Error:', error.message);
        throw error;
    } finally {
        await browser.close();
    }
}

// Get command line arguments
const barcode = process.argv[2] || '7290000554532';
const city = process.argv[3] || 'קרית אונו';

console.error(`Starting search for barcode: ${barcode}, city: ${city}`);

searchCHP(barcode, city)
    .then(() => {
        console.error('Search completed successfully');
        process.exit(0);
    })
    .catch((error) => {
        console.error('Search failed:', error);
        process.exit(1);
    });
