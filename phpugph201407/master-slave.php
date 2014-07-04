<?php
define('HOST_PRIMARY', 'primary');
define('HOST_STANDBY', 'standby');
$mysqli = connect_mysql();

/* Statements will be run on the master */
if (!$mysqli->query("DROP TABLE IF EXISTS test")) {
    printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
}
if (!$mysqli->query("CREATE TABLE test(id INT NOT NULL AUTO_INCREMENT PRIMARY KEY)")) {
    printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
}

for($i = 0; $i <= 1000; $i++) {
    $mysqli = connect_mysql();

    if (!$mysqli->query("INSERT INTO test(id) VALUES (NULL)")) {
        printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
    }

    $select  = "SELECT MAX(id) AS id, @@server_id AS sid, connection_id() AS cid FROM test";
    if (!$res = $mysqli->query($select)) {
        printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
    } else {
        $row = $res->fetch_assoc();
        $res->close();
        printf("Last value %04d from server id %d thread id %d\n", 
            $row['id'], $row['sid'], $row['cid']);
    }

    sleep(1);
}

function connect_mysql()
{
    $mysql_host = HOST_PRIMARY;
    $connected = true;

    if(is_file('/tmp/PRIMARY_HAS_FAILED')) {
    $mysql_host = HOST_STANDBY;
    }

    $mysqli = false;
    $mysqli = mysqli_init();
    $mysqli->real_connect($mysql_host, "msandbox", "msandbox", "test");

    /* https://bugs.php.net/bug.php?id=67564 */
    $mysqli->query('/*ms=master*/SELECT 1');
    if(!$mysqli->thread_id) $connected = false;
    $retries = 1;

    while(!$connected AND $retries <= 10) {
        printf(
            "Connection to host '{$mysql_host}' failed: [%d] %s, " . 
            "retrying ($retries of 10) in 3 seconds\n",
            $mysqli->connect_errno, $mysqli->error
        );

        sleep(3);

        $mysqli = false;
        $mysqli = mysqli_init();
        $mysqli->real_connect($mysql_host, "msandbox", "msandbox", "test");
        $mysqli->query('/*ms=master*/SELECT 1');

        if(!$mysqli->thread_id) $connected = false;
        else {
            $connected = true;
            break;
        }

        $retries++;
    }

    if(!$connected AND $retries >= 10 AND $mysql_host == HOST_PRIMARY) {
        printf("The primary host '{$mysql_host}' has failed after 30 seconds, " . 
            "failing over to standby!\n");
        touch('/tmp/PRIMARY_HAS_FAILED');
        return connect_mysql();
    }

    if($connected) return $mysqli;

    /* if we get at this point, neither the primary nor secondary has failed */
    die("Could not get MySQL connection!");
}
?>
