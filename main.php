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

/**
 * Class Connector
 * Создает соединение с БД и делает запросы
 * @author Sergiy Posternak
 */
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

    /**
     * Выбирает только те объявления, по которым нужно отправить email, и сортирует по времени суток от меньшего
     *
     * @param $limit int максимальное количество выборки
     * @param array $days дни до конца публикации, когда нужно отправлять email
     * @return array результат выборки
     */
    public function select($limit, array $days)
    {
        $query ="SELECT id, user_id, send, title, link FROM items WHERE status = 2 AND (";
        for($i=1;$i<count($days);$i++)
        {
            $query= $query."(send = $days[$i] AND DAYOFMONTH(publicated_to) = DAYOFMONTH(CURDATE()+".$days[$i-1].")) OR ";
        }
        $query = $query."(send = 0 AND DAYOFMONTH(publicated_to) = DAYOFMONTH(CURDATE()+".end($days)."))";
        $query = $query.") ORDER BY EXTRACT(HOUR_SECOND FROM publicated_to) LIMIT $limit";

        $result = $this->PDO->query($query);
        return $result->fetchAll();
    }

    /**
     * Изменяет в БД значение поля "send"
     *
     * @param $send int актуальное значене
     * @param $id int id объявления
     */
    public function update($send, $id)
    {
        $this->PDO->query("UPDATE items SET send = $send WHERE id = $id;");
    }

    /**
     * Подготовка запроса выборки имейлов
     *
     * @return PDOStatement
     */
    public function prepare()
    {
        return $this->PDO->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    }

    /**
     * Добавляет для каждого элемента массива MailCreator::array поле "email"
     *
     * @param $st PDOStatement
     * @param $userId int
     */
    public function selectEmails($st, $userId)
    {
        $st->execute(array($userId));
        $row = $st->fetch();
        return $row[0];
    }
}

/**
 * Class MailCreator
 * Формирует и отправляет письма
 * @author Sergiy Posternak
 */
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

    /**
     * Возвращает новое значение "send"
     *
     * @param $old int старое значение "send"
     * @param array $days массив дней до окончания, когда нужно отправить email
     * @return int новое значение "send"
     */
    public static function newSendValue($old, array $days)
    {
        if($old != 0)
        {
            $key = array_search($old, $days);
            return $days[$key-1];
        }
        else
        {
            return end($days);
        }
    }

    /**
     * Возвращает правильный падеж слова "дни"
     *
     * @param $send int остаток дней публикации
     * @return string падеж
     */
    public static function selectCase($send)
    {
        switch($send) {
            case 1:
                return "день";
                break;
            case 2:
            case 3:
            case 4:
                return "дня";
                break;
            default:
                return "дней";
                break;
        }
    }

    /**
     * Формирует и отправляет сообщения
     *
     * @param array $days дни до окончания, когда нужно отправить email
     */
    private function send(array $days)
    {
        $st = $this->db->prepare();

        foreach ($this->array as $i => $result)
        {
            $result['email'] = $this->db->selectEmails($st, $result['user_id']);

            $send = self::newSendValue($result['send'], $days);

            $this->db->update($send, $result['id']);

            $to = $result['email'];
            $subject = "Срок действия объявления заканчивается!";

            $d = self::selectCase($send);
            $body = "<h1>Здраствуйте!</h1><p>Публикация Вашего объявления <a href=".$result['link'].">".$result['title']."</a> заканчивается через $send $d.</p><p>Если объявление для вас еще актуально, зайдите на сайт и возобновите его.</p>";

//строку ниже можно раскомментировать, что бы проверить работоспособность скрипта в браузере
            echo "<p>to: $to<br>head: $subject<br>body: $body<br></p>";
            mail($to, $subject, $body);
        }
    }
}
