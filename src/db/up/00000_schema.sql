-- create table to track schema changes
CREATE TABLE _schema_ (id varchar(100) primary key, performed_at timestamp not null default current_timestamp, downgrade text null default null) engine=innodb default charset=utf8;
