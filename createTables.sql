/*Таблица объявлений*/
CREATE TABLE IF NOT EXISTS items (
id int(11) unsigned NOT NULL auto_increment, -- ID объявления
user_id int(11) unsigned NOT NULL default 0, -- ID пользователя
status tinyint(1) unsigned NOT NULL default 1, -- статус объявления
send tinyint(1) unsigned NOT NULL default 0,  -- маркер отправки email
  /*
  0 - никаких email-оповещений не отправлялось
  5 - отправлено оповещение за 5 дней до окончания публикации объявления
  2 - за 2 дня
  1 - за 1 день

  * После возобновления объявления нужно сбросить в 0
  */
title varchar(150) NOT NULL default '', -- заголовок объявления
link text, -- ссылка на страницу просмотра объявления
descr text, -- описание объявления
publicated_to timestamp NOT NULL default '0000-00-00 00:00:00', -- срок окончания публикации объявления
PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

/*Таблица пользователей*/
CREATE TABLE IF NOT EXISTS users (
id int(11) unsigned NOT NULL auto_increment, -- ID пользователя
email text, -- Email пользователя
PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;