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

foreach ($files as $fileEntry) {
    $metadataHandle = fopen($dataFolder . '/' . $fileEntry['metadata_filename'], 'r');
    $metadata = readMetadata($metadataHandle);
    
    $shapeHandle = fopen($dataFolder . '/' . $fileEntry['shape_filename'], 'r');
    $shapes = readShapes($shapeHandle);
    var_dump($shapes);
}

function readMetadata($fileHandle) {
    $metadata = array();
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
    }
    
    return $metadata;
}

function readShapes($fileHandle) {
    $shapes = array();
    
    $firstLinePattern = '/^\s*(\d+)\s+([0-9\-\+\.E]+)\s+([0-9\-\+\.E]+)$/';
    $shapeLinePattern = '/^\s*([0-9\-\+\.E]+)\s+([0-9\-\+\.E]+)$/';
    while (!feof($fileHandle)) {
        $line = trim(fgets($fileHandle));
        if (preg_match($firstLinePattern, $line, $matches)) {
            $id = $matches[1];
            $points = array();
            $pointLng = $matches[2];
            $pointLat = $matches[3];
            $points[] = array(convertScientificNotation($pointLng), convertScientificNotation($pointLat));
        } elseif (preg_match($shapeLinePattern, $line, $matches)) {
            $pointLng = $matches[1];
            $pointLat = $matches[2];
            $points[] = array(convertScientificNotation($pointLng), convertScientificNotation($pointLat));
        } elseif ($line == 'END') {
            $shapes[$id] = $points;
        }
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