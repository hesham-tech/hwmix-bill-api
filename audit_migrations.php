<?php
$dir = 'database/migrations';
$files = scandir($dir);
$report = "Destructive operations in UP() method (Dangerous for Production):\n\n";

foreach ($files as $file) {
    if ($file === '.' || $file === '..')
        continue;
    $path = $dir . '/' . $file;
    if (!is_file($path))
        continue;

    $lines = file($path);
    $inUp = false;
    $upLines = "";
    $braceCount = 0;

    foreach ($lines as $line) {
        if (preg_match('/public\s+function\s+up\s*\(/', $line)) {
            $inUp = true;
        }

        if ($inUp) {
            $upLines .= $line;
            $braceCount += substr_count($line, '{');
            $braceCount -= substr_count($line, '}');

            if ($braceCount === 0 && strpos($line, '}') !== false && strlen(trim($upLines)) > 30) {
                $inUp = false;

                // Now check for danger in this collected up() content
                if (preg_match_all('/(dropColumn|dropTable|renameColumn|dropIfExists|drop)\s*\(\s*(.*?)\)/s', $upLines, $upMatches)) {
                    $report .= "File: $file\n";
                    foreach ($upMatches[0] as $match) {
                        $report .= "  - DANGER: " . trim($match) . "\n";
                    }
                    $report .= "\n";
                }
                $upLines = "";
            }
        }
    }
}

if ($report === "Destructive operations in UP() method (Dangerous for Production):\n\n") {
    $report .= "No dangerous operations found in UP() methods.";
}

file_put_contents('migration_audit_report.txt', $report);
echo "Report generated in migration_audit_report.txt\n";
