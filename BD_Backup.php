<?php
ini_set('memory_limit', '-1');

class DB_Backup
{
    protected $host, $user, $password, $database, $pdo;
    protected $obj = PDO::FETCH_OBJ;

    public function __construct($host, $user, $password, $database) {
        $this->host     = $host;
        $this->user     = $user;
        $this->password = $password;
        $this->database = $database;

        // Set the DSN (Data Source Name)
        $dsn = "mysql:host=$this->host; dbname=$this->database";

        // Create the PDO Obj
        $this->pdo = new PDO($dsn, $this->user, $this->password, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]);
	}

    public function get_full_backup()
    {
        // Run Queries
        $stmt = $this->pdo->query("SHOW TABLES");
        $stmt->execute();
        $rows = $stmt->fetchAll($this->obj);

	// GET DATABASE VERSION
        $db_version_query = $this->pdo->query("SELECT VERSION() AS `version`");
        $db_version_query->execute();
        $db_version = $db_version_query->fetch($this->obj);

        date_default_timezone_set('Asia/kabul');

        $data = "-- https://github.com/mohd-baqeri/Backup-PDO-PHP" . PHP_EOL;
        $data .= "-- DATABASE: " . $this->database . PHP_EOL;
        $data .= "-- VERSION: " . $db_version->version . PHP_EOL;
        $data .= "-- DATE: " . date('Y-m-d H:i:s') . ' (Asia/kabul)'  . PHP_EOL . PHP_EOL . PHP_EOL;
        $data .= "START TRANSACTION;" . PHP_EOL . PHP_EOL;

        // GET TABLES
        $show_tbls_query = $this->pdo->query("SHOW TABLES");
        $show_tbls_query->execute();
        $show_tbls = $show_tbls_query->fetchAll($this->obj);

        foreach ($show_tbls as $tbl_item) {
            $tables_in_db = 'Tables_in_' . $this->database;
            $data .= $this->get_tbl_backup($tbl_item->$tables_in_db, false);
        }

        $data .= "COMMIT;";

        return $data;
        
    }

    // SQL FILE CREATOR
    public function sql_creator($data)
    {
        date_default_timezone_set('Asia/kabul');

        // Create the file name (backup-2024-5-31 20:40:45.sql)
        $file_name = $this->database . '-backup-' . date('Y-m-d-His') . '.sql';

        // Open the file in "a" mode, if its not exists it will create the file
        $file = fopen('./' . $file_name, 'a');

        // Write the data in file
        fwrite($file, $data);

        // Closing the file
        fclose($file);

        // Return the filename back
        return $file_name;
    }

    // GET TABLE BACKUP
    public function get_tbl_backup($tbl_name, $trans = true)
    {
        // GET TABLE DESCRIPTION
        $tbl_desc_query = $this->pdo->query("DESCRIBE " . $tbl_name);
        $tbl_desc_query->execute();
        $tbl_desc = $tbl_desc_query->fetchAll($this->obj);

        // GET TABLE STATUS FOR (Engine)
        $tbl_status_query = $this->pdo->query("SHOW TABLE STATUS FROM `$this->database` WHERE Name='$tbl_name'");
        $tbl_status_query->execute();
        $tbl_status = $tbl_status_query->fetch($this->obj);

        // GET TABLE CHARSET FOR (DEFAULT CHARSET)
        $charset_query = $this->pdo->query("SELECT DISTINCT TABLE_NAME, CHARACTER_SET_NAME FROM INFORMATION_SCHEMA.Columns Where TABLE_SCHEMA='$this->database' AND CHARACTER_SET_NAME IS NOT NULL AND TABLE_NAME='$tbl_name'");
        $charset_query->execute();
        $charset = $charset_query->fetch($this->obj);

        if ($trans) $output = "START TRANSACTION;" . PHP_EOL . PHP_EOL;
        else $output = PHP_EOL;

        $output .= "--" . PHP_EOL;
        $output .= "-- Table structure for table `$tbl_name`" . PHP_EOL;
        $output .= "--" . PHP_EOL;
        $output .= "DROP TABLE IF EXISTS " . "`" . $tbl_name . "`;" . PHP_EOL;
        $output .= "CREATE TABLE IF NOT EXISTS " . "`" . $tbl_name . "` (" . PHP_EOL;

        $col_keys = [];
        for ($x=0; $x<count($tbl_desc); $x++) {
            // chk for keys
            if ($tbl_desc[$x]->Key == 'PRI') {
                $col_keys[] = "  PRIMARY KEY (`" . $tbl_desc[$x]->Field . "`)";
            }
            if ($tbl_desc[$x]->Key == 'UNI') {
                $col_keys[] = "  UNIQUE KEY `" . $tbl_desc[$x]->Field . "` (`" . $tbl_desc[$x]->Field . "`)";
            }

            // Create Collumns
            $output .= "  `" . $tbl_desc[$x]->Field . "` " . $tbl_desc[$x]->Type . " ";
            if ($tbl_desc[$x]->Null == 'NO') $output .= "NOT NULL";
            else $output .= "NULL";
            if ($tbl_desc[$x]->Default || $tbl_desc[$x]->Default == '0') {
                $method_finder = strrchr($tbl_desc[$x]->Default, '()');
                if ($method_finder || $tbl_desc[$x]->Default == 'CURRENT_TIMESTAMP' /* There's also current date and current time and so and so, which they are not included! (Include them HERE if you encountered to them, PLEASE!!!) */) {
					$output .= " DEFAULT " . $tbl_desc[$x]->Default;
				} else {
					$output .= " DEFAULT '" . $tbl_desc[$x]->Default . "'";
				}
            }
            if ($tbl_desc[$x]->Extra) $output .= " " . $tbl_desc[$x]->Extra;

            // write keys at the end of TBL creation
            if ($x == count($tbl_desc) - 1) {
                if ($col_keys) {
                    $output .= ",";
                    $output .= PHP_EOL;
                    for ($y=0; $y<count($col_keys); $y++) {
                        $output .= $col_keys[$y];
                        if ($y != count($col_keys) - 1) {
                            $output .= "," . PHP_EOL;
                        } else $output .= PHP_EOL;
                    }
                } else $output .= PHP_EOL;
            } else {
                $output .= "," . PHP_EOL;
            }
        }

        $output .= ") ENGINE=" . $tbl_status->Engine . " AUTO_INCREMENT=";
		$output .= ($tbl_status->Auto_increment) ? ($tbl_status->Auto_increment) : (1);
		$output .= " DEFAULT CHARSET=" . $charset->CHARACTER_SET_NAME . ";" . PHP_EOL . PHP_EOL;
        

        // /*

        // Prepare for insertion

        // GET TABLE ROWS
        $tbl_rows_query = $this->pdo->query("SELECT * FROM $tbl_name");
        $tbl_rows_query->execute();
        $tbl_rows = $tbl_rows_query->fetchAll($this->obj);


        if ($tbl_rows) {
            $output .= "-- " . PHP_EOL;
            $output .= "-- Dumping data for table `" . $tbl_name . "`" . PHP_EOL;
            $output .= "-- " . PHP_EOL;
        }

        for ($y=0; $y<count($tbl_rows); $y++) {
            // BUILD THE INSERT QUERY
            $output .= "INSERT INTO `$tbl_name` VALUES" . PHP_EOL;

            // VALUES
            $output .= "(";

                for ($x=0; $x<count($tbl_desc); $x++) {
                    $field = $tbl_desc[$x]->Field;
                    if ($tbl_rows[$y]->$field == '' && $tbl_rows[$y]->$field != NULL) {
                        $output .= "''";
                    } elseif ($tbl_rows[$y]->$field == NULL && $tbl_rows[$y]->$field != '') {
                        $output .= NULL;
                    } else $output .= "'" . $tbl_rows[$y]->$field . "'";
                    if ($x != count($tbl_desc) - 1) $output .= ", ";
                }
          
             $output .= ");" . PHP_EOL;
        }

        $output .= PHP_EOL . PHP_EOL;
        if ($trans) $output .= "COMMIT;" . PHP_EOL;
        else $output .= PHP_EOL;

        $output .= "-- End of TABLE" . PHP_EOL . PHP_EOL;

        return $output;
    }

    // GET BACKUP
    public function get_backup($download = false, $tbl_name = false)
    {
		if ($download) {
			if ($tbl_name) {
				$data = $this->get_tbl_backup($tbl_name);
				$file = $this->sql_creator($data);
				// force download
				if (file_exists($file)) {
					header('Content-Description: File Transfer');
					header('Content-Type: application/octet-stream');
					header('Content-Disposition: attachment; filename="' . basename($file) . '"');
					header('Expires: 0');
					header('Cache-Control: must-revalidate');
					header('Pragma: public');
					header('Content-Length: ' . filesize($file));
					readfile($file);
					exit;
				}
				return $file;
			}
			$data = $this->get_full_backup();
			$file = $this->sql_creator($data);
			// force download
			if (file_exists($file)) {
				header('Content-Description: File Transfer');
				header('Content-Type: application/octet-stream');
				header('Content-Disposition: attachment; filename="' . basename($file) . '"');
				header('Expires: 0');
				header('Cache-Control: must-revalidate');
				header('Pragma: public');
				header('Content-Length: ' . filesize($file));
				readfile($file);
				exit;
			}
			return $file;
		} else {
			if ($tbl_name) {
				$data = $this->get_tbl_backup($tbl_name);
				return $this->sql_creator($data);
			}
			$data = $this->get_full_backup();
			return $this->sql_creator($data);
		}
    }
}
