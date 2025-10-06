<?php
echo "✅ PHP está funcionando<br>";
echo "Directorio actual: " . __DIR__ . "<br>";
echo "Archivos en /var/www/html:<br>";
$files = scandir('/var/www/html');
foreach ($files as $file) {
    echo "- $file<br>";
}
?>
