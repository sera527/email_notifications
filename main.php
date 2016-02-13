<?php
/**
 * Created by PhpStorm.
 * User: Сергій
 * Date: 12.02.2016
 * Time: 1:05
 */

//Данные для входа в БД настоящие. Можно запустить скрипт и проверить работоспособность
$dbtype = 'mysql';
$db = 'cross';
$host = 'eu-cdbr-azure-west-c.cloudapp.net';
$port = '3306';
$user = 'b29e268cbd294f';
$password = '88231ff3';
$days = array(1, 2, 5);//возрастающий массив. дни до окончания, когда нужно отправить email

new MailCreator($dbtype, $db, $host, $port, $user, $password, $days);

class Connector
{
    private $PDO;

    public function __construct($dbtype, $db, $host, $port, $user, $password)
    {
        $this->PDO = new PDO("$dbtype:host=$host;port=$port;dbname=$db", $user, $password);
    }

    public function getConnection()
    {
        return $this->PDO;
    }

    public function select($limit, array $days)
    {
        $query ="SELECT * FROM items WHERE status = 2 AND (";
        for($i=1;$i<count($days);$i++)
        {
            $query= $query."(send = $days[$i] AND DAYOFMONTH(publicated_to) = DAYOFMONTH(CURDATE()+".$days[$i-1].")) OR ";
        }
        $query = $query."(send = 0 AND DAYOFMONTH(publicated_to) = DAYOFMONTH(CURDATE()+".end($days)."))";
        $query = $query.") ORDER BY EXTRACT(HOUR_SECOND FROM publicated_to) LIMIT $limit";

        $result = $this->PDO->query($query);
        return $result->fetchAll();
    }
}

class MailCreator
{
    private $db;
    private $array;

    public function __construct($dbtype, $db, $host, $port, $user, $password, $days, $limit = 100)
    {
        $this->db = new Connector($dbtype, $db, $host, $port, $user, $password);
        $this->array = $this->db->select($limit, $days);
        $this->send($days);
    }

    private function send(array $days)
    {
        $st = $this->db->getConnection()->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");

        foreach ($this->array as $i => $result)
        {
            $st->execute(array($result['user_id']));
            $row = $st->fetch();
            $result['email'] = $row[0];

            if($result['send'] != 0)
            {
                $key = array_search($result['send'], $days);
                $key = $days[$key-1];
            }
            else
            {
                $key = end($days);
            }
            $this->db->getConnection()->query("UPDATE items SET send = $key WHERE id = ".$result['id'].";");
            $to = $result['email'];
            $subject = "Срок действия объявления заканчивается!";

            switch($key) {
                case 1:
                    $d = "день";
                    break;
                case 2:
                    $d = "дня";
                    break;
                default:
                    $d = "дней";
                    break;
            }

            $body = "<h1>Здраствуйте!</h1><p>Публикация Вашего объявления <a href=".$result['link'].">".$result['title']."</a> заканчивается через $key $d.</p>";

//строку ниже можно раскомментировать, что бы проверить работоспособность скрипта в браузере
//            echo "<p>to: $to<br>head: $subject<br>body: $body<br></p>";
            mail($to, $subject, $body);
        }
    }
}
