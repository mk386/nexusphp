<?php
class Attendance
{
    protected $userid;
    protected $curdate;
    public function __construct($userid){
        $this->userid = $userid;
        $this->curdate = date('Y-m-d');
        $this->cachename = sprintf('attendance_%u_%s', $this->userid, $this->curdate);
    }

    public function check($flush = false)
    {
        global $Cache;
        if($flush || ($row = $Cache->get_value($this->cachename)) === false){
            $res = sql_query(sprintf('SELECT * FROM `attendance` WHERE `uid` = %u AND DATE(`added`) = %s', $this->userid, sqlesc($this->curdate.' 00:00:00'))) or sqlerr(__FILE__,__LINE__);
            $row = mysql_num_rows($res) ? mysql_fetch_assoc($res) : array();
            $Cache->cache_value($this->cachename, $row, 86400);
        }
        return empty($row) ? false : $row;
    }

    public function attend($initial = 10, $step = 5, $maximum = 2000, $continous = array())
    {
        if($this->check(true)) return false;
        $res = sql_query(sprintf('SELECT DATEDIFF(%s, `added`) AS diff, `days`, `total_days`, `total_points` FROM `attendance` WHERE `uid` = %u ORDER BY `id` DESC LIMIT 1', sqlesc($this->curdate), $this->userid)) or sqlerr(__FILE__,__LINE__);
        $doUpdate = mysql_num_rows($res);
        if ($doUpdate) {
            $row = mysql_fetch_row($res);
            do_log("uid: {$this->userid}, row: " . json_encode($row));
        } else {
            $row = [0, 0, 0, 0];
        }
        $points = min($initial + $step * $row['total_attend_times'], $maximum);
        list($datediff, $days, $totalDays, $totalPoints) = $row;
        $cdays = $datediff == 1 ? ++$days : 1;
        if($cdays > 1){
            krsort($continous);
            foreach($continous as $sday => $svalue){
                if($cdays >= $sday){
                    $points += $svalue;
                    break;
                }
            }
        }
//        sql_query(sprintf('INSERT INTO `attendance` (`uid`,`added`,`points`,`days`) VALUES (%u, %s, %u, %u)', $this->userid, sqlesc(date('Y-m-d H:i:s')), $points, $cdays)) or sqlerr(__FILE__, __LINE__);
        if ($doUpdate) {
            $sql = sprintf(
                'UPDATE `attendance` set added = %s, points = %s, days = %s, total_days= %s, total_points = %s where uid = %s order by id desc limit 1',
                sqlesc(date('Y-m-d H:i:s')), $points, $cdays, $totalDays + 1, $totalPoints + $points, $this->userid
            );
        } else {
            $sql = sprintf(
                'INSERT INTO `attendance` (`uid`, `added`, `points`, `days`, `total_days`, `total_points`) VALUES (%u, %s, %u, %u, %u, %u)',
                $this->userid, sqlesc(date('Y-m-d H:i:s')), $points, $cdays, $totalDays + 1, $totalPoints + $points
            );
        }
        do_log(sprintf('uid: %s, date: %s, doUpdate: %s, sql: %s', $this->userid, $this->curdate, $doUpdate, $sql), 'notice');
        sql_query($sql) or sqlerr(__FILE__, __LINE__);
        KPS('+', $points, $this->userid);
        global $Cache;
        $Cache->delete_value($this->cachename);
        return array(++$totalDays, $cdays, $points);
    }
}
