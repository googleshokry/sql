```shell
BEGIN
   SET FOREIGN_KEY_CHECKS = 0;
	 SET GROUP_CONCAT_MAX_LEN=32768;
	 SET @tables = NULL;
   SELECT GROUP_CONCAT('`', table_schema, '`.`', table_name,'`') INTO @tables FROM 

information_schema.tables
	 WHERE table_schema != 'information_schema';
   SET @tables = CONCAT('DROP TABLE ', @tables);

   PREPARE stmt1 FROM @tables;
   EXECUTE stmt1;
   DEALLOCATE PREPARE stmt1;

	 SET FOREIGN_KEY_CHECKS = 1;
END




SET @tables = NULL;
SELECT GROUP_CONCAT('`', table_schema, '`.`', table_name,'`') INTO @tables FROM information_schema.tables 
  WHERE table_schema != 'information_schema';

SET @tables = CONCAT('DROP TABLE ', @tables);
PREPARE stmt1 FROM @tables;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;


```
