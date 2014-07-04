<?php
define('HOST_PRIMARY', 'primary');
$mysqli = connect_mysql();

$sql = "CREATE TABLE IF NOT EXISTS test(id INT NOT NULL AUTO_INCREMENT PRIMARY KEY)";
query_write_mysql($sql, $mysqli);

for($i = 0; $i <= 1; $i++) {
    $mysqli = connect_mysql();

    query_write_mysql("INSERT INTO test(id) VALUES (NULL)", $mysqli);

    $ms = MYSQLND_MS_LAST_USED_SWITCH;
    $sql = "/*ms=$ms*/SELECT MAX(id) AS id, @@server_id AS sid, connection_id() AS cid FROM test";
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

    $mysqli = false;
    $mysqli = mysqli_init();
    $mysqli->real_connect($mysql_host, "revin", "revin", "test");

    if(!$retry) return $mysqli;

    $ms = MYSQLND_MS_LAST_USED_SWITCH;
    /* https://bugs.php.net/bug.php?id=67564 */
    $mysqli->query("/*ms=$ms*/SELECT 1");
    if(!$mysqli->thread_id) $connected = false;
    $retries = 1;

    while(!$connected AND $retries <= 10) {
        printf("Connection to host '{$mysql_host}' failed: [%d] %s, " . 
            "retrying ($retries of 10) in 3 seconds\n",
            $mysqli->connect_errno, $mysqli->error
        );

        sleep(3);

        $mysqli->real_connect($mysql_host, "revin", "revin", "test");
        $mysqli->query("/*ms=$ms*/SELECT 1");

        if(!$mysqli->thread_id) $connected = false;
        else {
            $connected = true;
            break;
        }

        $retries++;
    }

    if($connected) return $mysqli;

    /* if we get at this point, neither the primary nor secondary has failed */
    die("Could not get MySQL connection!");
}
?>
