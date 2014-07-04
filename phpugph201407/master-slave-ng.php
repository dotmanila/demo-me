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

    query_write_mysql("INSERT INTO test(id) VALUES (NULL)", $mysqli);

    $sql = "SELECT MAX(id) AS id, @@server_id AS sid, connection_id() AS cid FROM test";
    $res = query_select_mysql($sql, $mysqli);
    if($res) {
        $row = $res->fetch_assoc();
        $res->close();
        printf("Last value %04d from server id %d thread id %d\n", 
            $row['id'], $row['sid'], $row['cid']);
    }

    sleep(1);
}

function query_write_mysql($sql, $mysqli)
{
    if ($mysqli->query($sql)) return true;
    /* We assign last errno to variable as somehow it is being cleared
    after first call */
    $errno = $mysqli->errno;
    printf(__LINE__ . ": [%d] %s\n", $errno, $mysqli->error);

    if(is_connect_error($errno)) {
        $mysqli = connect_mysql(true);
        return query_write_mysql($sql, $mysqli);
    }
    else return false;
}

function query_select_mysql($sql, $mysqli)
{
    if ($res = $mysqli->query($sql)) return $res;
    /* We assign last errno to variable as somehow it is being cleared
    after first call */
    $errno = $mysqli->errno;
    printf(__LINE__ . ": [%d] %s\n", $errno, $mysqli->error);

    if(is_connect_error($errno)) {
        $mysqli = connect_mysql(true);
        return query_select_mysql($sql, $mysqli);
    }
    else return false;
}

function is_connect_error($errno)
{
    $is_connect_error = false;
    /* http://dev.mysql.com/doc/refman/5.5/en/error-messages-client.html */
    switch($errno) {
        case 2002: /* Can't connect to local MySQL server through socket */
        case 2003: /* Can't connect to MySQL server on */
        $is_connect_error = true;
        break;
    }

    return $is_connect_error;
}

function connect_mysql($retry = false)
{
    $mysql_host = HOST_PRIMARY;
    $connected = true;

    if(is_file('/tmp/PRIMARY_HAS_FAILED')) {
        $mysql_host = HOST_STANDBY;
    }

    $mysqli = false;
    $mysqli = mysqli_init();
    $mysqli->real_connect($mysql_host, "msandbox", "msandbox", "test");

    if(!$retry) return $mysqli;

    /* https://bugs.php.net/bug.php?id=67564 */
    $mysqli->query('/*ms=master*/SELECT 1');
    if(!$mysqli->thread_id) $connected = false;
    $retries = 1;

    while(!$connected AND $retries <= 10) {
        printf("Connection to host '{$mysql_host}' failed: [%d] %s, " . 
            "retrying ($retries of 10) in 3 seconds\n",
            $mysqli->connect_errno, $mysqli->error
        );

        sleep(3);

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
