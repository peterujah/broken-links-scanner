## PHP Broken Links Scanner

A PHP library for scanning websites to identify broken links and extract relevant information.
Ensure that the required PHP extensions are installed, particularly `cURL`, for the scanner to function properly.

Installation is super-easy via Composer:
```md
composer require peterujah/broken-links-scanner
```

---

## CLI Usage

![CLI Example](https://raw.githubusercontent.com/peterujah/broken-links-scanner/refs/heads/main/broken.png)
Use the CLI script to scan a website for broken links.

### Options:

- `--url` **(required)**: The starting URL for the scan (e.g., `http://luminova.ng/docs/` or `http://luminova.ng/`).
- `--host` **(required)**: The scan URL hostname (e.g., `luminova.ng`).
- `--path` **(optional)**: Path to save the scan results.
- `--output` **(optional)**: Flag to control output of broken links. Use `1` to print, or `0` to suppress output (default: `0`).
- `--timeout` **(optional)**: Maximum time in seconds to wait for the scan to complete (default: `0`).
- `--limit` **(optional)**: Maximum number of scans to perform. Use `0` to scan all URLs (default: `0`).

### Example Usage:

To start a scan, run the following command:

```bash
php broken --url="https://luminova.ng/" --host="luminova.ng" [--timeout=10] [--path="/scanner/logs"] [--output=0] [--limit=0]
```

---


## Example: Using Scanner to Scan a Website for Broken Links

Initialize `Scanner` with the necessary parameters and register your custom classes.


#### 1. Basic Usage

```php
require_once __DIR__ . '/vendor/autoload.php';

use \Peterujah\BrokenLinks\Scanner;

// Define the starting URL for the scan
$url = 'https://luminova.ng/';
$host = 'luminova.ng';
$maxScan = 10; // Set to 0 to scan all URLs.

// Initialize the BrokenLinks class
$scanner = new Scanner($url, $host, $maxScan);

// Optionally set the path to save scanned URLs
$scanner->setPath($path);
```

#### 2. Start the Scan and Retrieve Results

If the path is not set, you can get the output directly:

```php
if ($scanner->start() && $scanner->isCompleted()) {
    // Get results from the scan
    $brokenLinks = $scanner->getBrokenLinks();
    $visitedUrls = $scanner->getVisitedUrls();
    $errors = $scanner->getErrors();
    $allUrls = $scanner->getUrls();

    // Output the scanned data
    echo "Broken Links:\n";
    print_r($brokenLinks);

    echo "\nVisited URLs:\n";
    print_r($visitedUrls);

    echo "\nErrors Encountered:\n";
    print_r($errors);

    echo "\nAll Extracted URLs:\n";
    print_r($allUrls);
} else {
    echo "Failed to complete the scan.\n";
}
```

#### 3. Using the `wait` Method

To wait for the scan to complete, you can use the `wait` method with a specified timeout:

```php
$timeout = 30;

try {
    $scanner->wait($timeout, function (BrokenLinks $scanner) {
        $brokenLinks = $scanner->getBrokenLinks();
        echo "Broken Links:\n";
        print_r($brokenLinks);
    });
} catch (RuntimeException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

> **Note:** When using the `wait` method no need to call `start` method again.

***

### Class Methods Documentation

#### __construct

- **Description:** Initializes a new instance of the scanner with the specified URL and hostname.
- **Parameters:**
  - `string $url`: The starting URL for the scan (e.g., `https://luminova.ng/docs/`).
  - `string $host`: The hostname for the URL to scan (e.g., `luminova.ng`).
  - `int $maxScan`: The maximum number of scans to perform (default is `0`, which means no limit).
  
---

#### isCompleted(): bool

- **Description:** Checks whether the scanning process has been completed.
- **Returns:**
  - `bool`: Returns `true` if the scan is completed; otherwise, returns `false`.
  
---

#### getBrokenLinks(): array

- **Description:** Retrieves the list of broken URLs identified during the scan.
- **Returns:**
  - `array`: An array containing the broken URLs.
  
---

#### getVisitedUrls(): array

- **Description:** Retrieves the list of URLs that have been visited during the scan.
- **Returns:**
  - `array`: An array containing the visited URLs.
  
---

#### getErrors(): array

- **Description:** Retrieves the error messages encountered during the scan process.
- **Returns:**
  - `array`: An array containing the error messages.
  
---

#### getUrls(): array

- **Description:** Retrieves the list of extracted URLs during the scan.
- **Returns:**
  - `array`: An array containing the extracted URLs.
  
---

#### setPath(string $path): self

- **Description:** Sets the file path where scanned URLs will be saved.
- **Parameters:**
  - `string $path`: The file path to save scanned URLs.
- **Returns:**
  - `self`: Returns the current instance of the class for method chaining.
  
---

#### cli(bool $cli): self

- **Description:** Sets whether the scanning results should be shown in the command line interface (CLI).
- **Parameters:**
  - `bool $cli`: `true` if running in CLI mode; otherwise, `false`.
- **Returns:**
  - `self`: Returns the current instance of the class for method chaining.
  
---

#### start(): bool

- **Description:** Initiates the link scanning process.
- **Returns:**
  - `bool`: Returns `true` if the scan completes successfully; returns `false` otherwise.
- **Throws:**
  - `RuntimeException`: Throws an exception if the provided URL is invalid.

---

#### wait(int $timeout, ?callable $onComplete = null): void

- **Description:** Waits for the scanning process to complete or until a specified timeout is reached. If a callback function is provided, it will be executed upon completion.
- **Parameters:**
  - `int $timeout`: The maximum number of seconds to wait. If `0`, it waits indefinitely until the scan is completed.
  - `callable|null $onComplete`: An optional callback function to be executed when the scan completes.
- **Throws:**
  - `RuntimeException`: Throws an exception if the timeout is exceeded before completion.
