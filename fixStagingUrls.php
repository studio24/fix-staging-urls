<?php
/**
 * Fix staging paths in database content
 *
 * The script looks for instances of absolute links with the staging URL and replaces these with /
 * For example DB content with the following HTML: <img src="http://staging.domain.com/assets/img/person.jpg" alt="The team">
 * Will be translated to: <img src="/assets/img/person.jpg" alt="The team">
 *
 * Usage:
 * php fixStagingUrls.php [stagingURL] [tableName]
 *
 * stagingURL - the staging/test URL without http/s://, e.g. staging.domain.com
 * tableName - the table name to search for absolute paths in, you can add multiple tables separated by commas (no spaces please)
 *
 * Example usage:
 * Fixes content with absolute links to staging.studio24.net in the ExpressionEngine content table
 *
 * php fixStagingUrls.php staging.studio24.net exp_channel_data
 *
 * @author Simon R Jones <simon@studio24.net>
 * @copyright Studio 24 Ltd
 * @license MIT License (MIT)
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Defaults (add values to auto-set these properties)
define('DB_ENGINE', 'mysql');
define('DB_HOST', 'localhost');
//define('DB_NAME', '');
//define('DB_USERNAME', '');
//define('DB_PASSWORD', '');


/**
 * Do not edit below here
 */


// Help
if (isset($argv[1]) && in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
    echo <<<EOD
Fix staging paths in database content

The script looks for instances of absolute links with the staging URL and replaces these with /
For example DB content with the following HTML: <img src="http://staging.domain.com/assets/img/person.jpg" alt="The team">
Will be translated to: <img src="/assets/img/person.jpg" alt="The team">

Usage: php fixStagingUrls.php [stagingURL] [tableName]

stagingURL - the staging/test URL without http/s://, e.g. staging.domain.com
tableName - the table name to search for absolute paths in, you can add multiple tables separated by commas (no spaces please)
            If you don't set a table name the script will scan all tables in the database

Example usage:
Fixes content with absolute links to staging.studio24.net in the ExpressionEngine content table

php fixStagingUrls.php staging.studio24.net exp_channel_data

The script will ask for your database login details if this is not set in script constants.

MIT License (MIT)
Copyright (c) 2014 Studio 24 Ltd (www.studio24.net)

EOD;
    exit();
}

// Get arguments
if (defined('DB_ENGINE') && DB_ENGINE != '') {
    $dbEngine = DB_ENGINE;
} else {
    echo "Enter the database engine to connect to (e.g. mysql): ";
    $dbEngine = trim(fgets(STDIN));
}
if (defined('DB_HOST') && DB_HOST != '') {
    $host = DB_HOST;
} else {
    echo "Enter the database host to connect to (e.g. localhost): ";
    $host = trim(fgets(STDIN));
}
if (defined('DB_NAME') && DB_NAME != '') {
    $dbname = DB_NAME;
} else {
    echo "Enter the database name to connect to: ";
    $dbname = trim(fgets(STDIN));
}
if (defined('DB_USERNAME') && DB_USERNAME != '') {
    $username = DB_USERNAME;
} else {
    echo "Enter your database username: ";
    $username = trim(fgets(STDIN));
}
if (defined('DB_PASSWORD') && DB_PASSWORD != '') {
    $password = DB_PASSWORD;
} else {
    echo "Enter your database password: ";
    $password = trim(fgets(STDIN));
}

if ($argc < 2) {
    exit("You need to pass the staging URL and table names to search for content on!\n");
} else {
    $stagingUrl = $argv[1];
    if (isset($argv[2])) {
        $tables = explode(',', $argv[2]);
    } else {
        $tables = false;
    }
}

if (preg_match('/^http/', $stagingUrl)) {
    exit('Please do not add http/s at the start of the staging URL');
}
$stagingUrl = trim($stagingUrl);
$stagingUrl = trim($stagingUrl, '/');

echo PHP_EOL . 'Fix staging paths in database content' . PHP_EOL;
echo '-------------------------------------' . PHP_EOL . PHP_EOL;

// Connect to DB
try {
    $db = new PDO($dbEngine . ':dbname=' . $dbname . ';host=' . $host, $username, $password);
} catch (PDOException $e) {
    exit('Connection failed: ' . $e->getMessage());
}

// Fetch all table names from database
if (!$tables) {
    echo "Checking all tables in database $dbname" . PHP_EOL;
    $tables = array();
    foreach ($db->query('SHOW tables') as $row) {
        $tables[] = $row[0];
    }
}

// Process table by table
$replaced = array();
$skippedTables = array();
$noPrimaryKey = array();
foreach ($tables as $table) {
    $table = trim($table);
    $textFields = array();
    $primaryKeys = array();

    echo PHP_EOL . "Checking table $table" . PHP_EOL;

    // Build list of text fields
    foreach ($db->query('DESCRIBE `' . $table . '`') as $row) {
        if (in_array(strtolower($row['Type']), array('text', 'tinytext', 'mediumtext', 'longtext', 'char', 'varchar'))) {
            $textFields[] = $row['Field'];
        }
        if ($row['Key'] == 'PRI') {
            $primaryKeys[] = $row['Field'];
        }
    }

    if (empty($primaryKeys)) {
        echo "Cannot replace content in $table since there is no primary key set for this table" . PHP_EOL;
        $noPrimaryKey[] = $table;
    }

    // Load content to check what is incorrect
    $contentToFix = array();
    foreach ($textFields as $field) {
        $sql = 'SELECT ';
        foreach ($primaryKeys as $key) {
            $sql .= '`' . $key . '`, ';
        }
        $sql .= '`' . $field . '` as content FROM `' . $table . '` WHERE `' . $field . '` LIKE ?';
        $sth = $db->prepare($sql);
        $sth->execute(array('%' . $stagingUrl . '%'));
        foreach ($sth->fetchAll(PDO::FETCH_ASSOC) as $row) {

            // Build primary key refs
            $pks = array();
            foreach ($primaryKeys as $key) {
                $pks[$key] = $row[$key];
            }

            // Short text report on what we are replacing
            $summary = '';
            if (preg_match_all('!(.{0,45}' . preg_quote($stagingUrl, '!') . '.{0,45})!i', $row['content'], $m, PREG_PATTERN_ORDER)) {
                foreach ($m[1] as $match) {
                    $summary .= $match . PHP_EOL;
                }
            }

            // Replace content
            $regex = '!((src|href)=("|\'))?http(s)?://' . preg_quote($stagingUrl, '!') . '/!i';
            $replacement = '$1/';
            $replacedContent = preg_replace($regex, $replacement, $row["content"], -1, $count);
            $replacedSummary = preg_replace($regex, $replacement, $summary);

            // Build content to update
            $contentToFix[] = array(
                'tbl'   => $table,
                'fld'   => $field,
                'pk'    => $pks,
                'sum'   => $summary,
                'repsum' => $replacedSummary,
                'con'   => $replacedContent,
                'count' => $count
            );

            echo '+';
        }
        echo '.';
    }

    // Report on what we're about to do
    if (empty($contentToFix)) {
        echo "No records found that need fixing" . PHP_EOL;
        continue;
    }
    echo PHP_EOL . PHP_EOL;

    echo "Found " . count($contentToFix) . " records in the database which need fixing" . PHP_EOL . PHP_EOL;
    foreach ($contentToFix as $item) {
        $pks = '';
        foreach ($item['pk'] as $key => $val) {
            if (!empty($pks)) {
                $pks .= ',';
            }
            $pks .= $key . '=' . $val;
        }
        echo 'In ' . $item['tbl'] . '.' . $item['fld'] . ' (' . $pks . ') I found ' . $item['count'] . ' instance/s of the staging URL:' . PHP_EOL;
        echo 'Content: ' . $item['sum'];
        echo 'Replacement: ' . $item['repsum'] . PHP_EOL;
    }

    echo "Found " . count($contentToFix) . " records in the database which need fixing" . PHP_EOL;
    echo 'Do you want me to replace all content? (y/n) ';
    $response = '';
    while (!in_array(trim(strtolower($response)), array('y', 'n', 'yes', 'no'))) {
        $response = trim(fgets(STDIN));
    }
    if (in_array(trim(strtolower($response)), array('n','no'))) {
        echo "Skipping this table" . PHP_EOL;
        $skippedTables[] = $table;
        continue;
    }

    $affectedRows = 0;
    foreach ($contentToFix as $item) {
        $pksSql = '';
        $pksVals = array();
        $values = array($item['con']);
        foreach ($item['pk'] as $key => $val) {
            if (!empty($pksSql)) {
                $pksSql .= ' AND ';
            }
            $pksSql .= $key . '=?';
            $values[] = $val;
        }
        $sth = $db->prepare('UPDATE `' . $item['tbl'] . '` SET  `' . $item['fld'] . '` = ? WHERE ' . $pksSql);
        $sth->execute($values);
        $affectedRows += $sth->rowCount();
    }

    $replaced[$table] = $affectedRows;
    echo "Updated $affectedRows rows" . PHP_EOL;
}
echo PHP_EOL;

// Print summary
foreach ($replaced as $table => $count) {
    echo "Updated $count rows in $table" . PHP_EOL;
}
if (!empty($skippedTables)) {
    echo "You skipped updating the following tables: " . implode(', ', $skippedTables) . PHP_EOL;
}
foreach ($noPrimaryKey as $table) {
    echo "Cannot replace content in $table since there is no primary key set for this table" . PHP_EOL;
}

echo PHP_EOL;
echo "All done!" . PHP_EOL;
exit(0);
