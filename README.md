# Backup-PDO-PHP

require_once(dirname(__FILE__) . '/DB_Backup.php');
/**
* Backup Class
*/
class Backup extends DB_Backup
{
	  $backup = new DB_Backup($hostname, $username, $password, $database);

    // this command will create .sql file and then that will be force downloaded.
		$backup_file = $backup->get_backup(true);
}
