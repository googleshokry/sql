# نظام كاشير المطاعم والدليفري

| المجلد | الوصف |
|---|---|
| [`restaurant-pos/`](restaurant-pos/README.md) | النظام الأساسي: Laravel + Filament + MySQL — كاشير الويب، لوحة الإدارة، والـ API |
| [`mobile_app/`](mobile_app/README.md) | تطبيق Flutter واحد لـ Android و iOS: كاشير، مالك، طيار، وعميل |

## البناء التلقائي (GitHub Actions)
- **Backend tests (MySQL)**: بيشغل اختبارات النظام على MySQL 8 مع كل تعديل.
- **Mobile apps (Android APK + iOS)**: من تبويب Actions ← Run workflow ← نزّل الـ APK من Artifacts.

---

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
