<?php
define('HOST_PRIMARY', 'primary');
$mysqli = connect_mysql();
$host_info = false;

$sql = "CREATE TABLE IF NOT EXISTS test(id INT NOT NULL AUTO_INCREMENT PRIMARY KEY)";
query_write_mysql($sql, $mysqli);

for($i = 0; $i <= 3; $i++) {
    $mysqli = connect_mysql();

    query_write_mysql("INSERT INTO test(id) VALUES (NULL)", $mysqli);
    printf("Last value %d from host %s and thread id %s\n", 
        $mysqli->insert_id, $mysqli->host_info, $mysqli->thread_id);
    sleep(1);

    $mysqli->close();
    unset($mysqli);
}

function query_write_mysql($sql, $mysqli)
{
    global $host_info;
    
    if ($mysqli->query($sql)) return true;
    /* We assign last errno to variable as somehow it is being cleared
    after first call */
    $errno = $mysqli->errno;
    printf("ERRROR: %s [%d] %s on line %d\n", 
        $host_info, $errno, $mysqli->error, __LINE__);

    if(is_connect_error($errno)) {
        $mysqli = connect_mysql(true, $mysqli);
        return query_write_mysql($sql, $mysqli);
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

function connect_mysql($retry = false, $mysqli = false)
{
    global $host_info;

    $mysql_host = HOST_PRIMARY;
    $connected = true;

    if(!$retry) {
        $mysqli = false;
        $mysqli = new mysqli($mysql_host, "revin", "revin", "test");
        $host_info = $mysqli->host_info;
        return $mysqli;
    }

    /* https://bugs.php.net/bug.php?id=67564 */
    $mysqli->query("SELECT 1");
    $host_info = $mysqli->host_info;
    if(!$mysqli->thread_id) $connected = false;
    $retries = 1;

    while(!$connected AND $retries <= 10) {
        printf("Connection to host '{$host_info}' failed: [%d] %s, " . 
            "retrying ($retries of 10) in 3 seconds\n",
            $mysqli->connect_errno, $mysqli->error
        );

        sleep(3);

        $host_info = $mysqli->host_info;
        $mysqli->query("SELECT 1");

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
