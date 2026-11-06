<?php
namespace App\Nucleo;

use PDO;

/** Base de las clases «entity»: una entidad por tabla de la base de datos. */
abstract class EntidadBase
{
    protected PDO $bd;

    public function __construct()
    {
        $this->bd = Conexion::obtener();
    }
}
