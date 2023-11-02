# Backup-PDO-PHP

require_once(dirname(__FILE__) . '/DB_Backup.php');

class Backup extends DB_Backup
{

    $backup = new DB_Backup($hostname, $username, $password, $database);

    // This command will create a .sql file and then that will be force downloaded.

    $backup_file = $backup->get_backup(true);
}
