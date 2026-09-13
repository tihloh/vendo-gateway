<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Database;
use PDO;
final class Migrator
{
    public function __construct(private PDO $pdo,private ?string $path=null) {}
    public function migrate(): array
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS vg_migrations (migration VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');$path=$this->path??dirname(__DIR__,2).'/database/migrations';$files=glob(rtrim($path,'/').'/*.sql')?:[];sort($files,SORT_NATURAL);$applied=[];$check=$this->pdo->prepare('SELECT 1 FROM vg_migrations WHERE migration=?');$record=$this->pdo->prepare('INSERT INTO vg_migrations (migration,applied_at) VALUES (?,UTC_TIMESTAMP())');foreach($files as $file){$name=basename($file);$check->execute([$name]);if($check->fetchColumn())continue;$sql=file_get_contents($file);if($sql===false)throw new \RuntimeException("Unable to read migration {$name}.");$this->pdo->exec($sql);$record->execute([$name]);$applied[]=$name;}return $applied;
    }
}
