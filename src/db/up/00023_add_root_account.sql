-- Add a root account
INSERT INTO account (id, first_name, last_name, email, role, status, admin, message)
 VALUES (0, "Techscore", "Admin", "root@localhost", "coach", "active", 2, "This is the default system account.")
;

UPDATE account SET id = 0 WHERE id = LAST_INSERT_ID();
