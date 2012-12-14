<?php

if (!isset($argv[1])) {
    echo "Usage: convert <data_folder>\n";
    exit;
}

$dataFolder = $argv[1];
if (!file_exists($dataFolder)) {
    echo "Error! Folder not found ($dataFolder)!";
    exit;
} else {
    if (!is_dir($dataFolder)) {
        echo "Error! $dataFolder is not a folder!";
        exit;
    }
}

$files = array();
$filenamePattern = '/^zt(\d+)_d00\.dat$/';
if ($dirHandle = opendir($dataFolder)) {
    while (false !== ($entry = readdir($dirHandle))) {
        if ($entry != "." && $entry != "..") {
            if (!is_dir($entry) === true) {
                if (preg_match($filenamePattern, $entry, $matches)) {
                    $shapeFilename = $matches[0];
                    $fileNumber = $matches[1];
                    $metadataFilename = 'zt' . $fileNumber . '_d00a.dat';
                    $metadataFilePath = $dataFolder . '/' . $metadataFilename;
                    if (file_exists($metadataFilePath)) {
                        $files[$fileNumber] = array(
                            'shape_filename' => $shapeFilename,
                            'metadata_filename' => $metadataFilename
                        );
                    } else {
                        echo "Warning: Missing metadata file ($metadataFilename) for shape file ($shapeFilename)!";
                    }
                }
            }
        }
    }
    closedir($dirHandle);
}

$outHandle = fopen('shapes.csv', 'w');
foreach ($files as $fileEntry) {
    echo 'File: ' . $fileEntry['shape_filename'] . "\n";
    $metadataHandle = fopen($dataFolder . '/' . $fileEntry['metadata_filename'], 'r');
    echo '    Reading Metadata';
    $metadata = readMetadata($metadataHandle);
    echo "\n";
    
    $shapeHandle = fopen($dataFolder . '/' . $fileEntry['shape_filename'], 'r');
    echo '    Reading Shapes';
    $shapes = readShapes($shapeHandle);
    echo "\n";
    
    $progress = 0;
    echo '    Writing Shapes';
    foreach ($shapes as $shapeId => $shapeData) {
        if (isset($metadata[$shapeId])) {
            fwrite($outHandle, $metadata[$shapeId] . ';POLYGON ((' . implode(', ', $shapeData) . '))' . "\n");
        }
        if ($progress % 100 == 0) {
            echo '.';
        }
        $progress++;
    }
    echo "\n\n";
    echo "Output: shapes.csv\n";
}

function readMetadata($fileHandle) {
    $metadata = array();
    $progress = 0;
    while (!feof($fileHandle)) {
        $line = fgets($fileHandle);
        $id = trim($line);
        $line = fgets($fileHandle);
        $zipCode = str_replace('"', '', trim($line));
        $line = fgets($fileHandle);
        $line = fgets($fileHandle);
        $line = fgets($fileHandle);
        $line = fgets($fileHandle);
        
        if ($id) {
            $metadata[$id] = $zipCode;
        }
        if ($progress % 100 == 0) {
            echo '.';
        }
        $progress++;
    }
    
    return $metadata;
}

function readShapes($fileHandle) {
    $shapes = array();
    $progress = 0;
    
    $firstLinePattern = '/^\s*(\d+)\s+([0-9\-\+\.E]+)\s+([0-9\-\+\.E]+)$/';
    $shapeLinePattern = '/^\s*([0-9\-\+\.E]+)\s+([0-9\-\+\.E]+)$/';
    while (!feof($fileHandle)) {
        $line = trim(fgets($fileHandle));
        if (preg_match($firstLinePattern, $line, $matches)) {
            $id = $matches[1];
            $points = array();
            $pointLng = $matches[2];
            $pointLat = $matches[3];
            $points[] = convertScientificNotation($pointLng) . ' ' . convertScientificNotation($pointLat);
        } elseif (preg_match($shapeLinePattern, $line, $matches)) {
            $pointLng = $matches[1];
            $pointLat = $matches[2];
            $points[] = convertScientificNotation($pointLng) . ' ' . convertScientificNotation($pointLat);
        } elseif ($line == 'END') {
            $shapes[$id] = $points;
        }
        if ($progress % 10000 == 0) {
            echo '.';
        }
        $progress++;
    }
    
    return $shapes;    
}

function convertScientificNotation($snNumber) {
    $result = $snNumber;
    $pattern = '/^([0-9\-\.]+)E\+(\d+)$/';
    if (preg_match($pattern, $snNumber, $matches)) {
        $number = (float) $matches[1];
        $exponent = (int) $matches[2];
        $result = $number * pow(10,$exponent);
    }
    
    return $result;
}