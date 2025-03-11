<?php
namespace FacturaScripts\Plugins\MatriculaFactura\Model;

use FacturaScripts\Core\Model\FacturaCliente as ParentModel;

class FacturaCliente extends ParentModel
{
    /**
     * Matrícula del vehículo asociado a la factura
     * @var string
     */
    public $matricula;

    public function __construct(array $data = [])
    {
        parent::__construct($data);
        // Inicializamos la propiedad
        if (empty($this->matricula)) {
            $this->matricula = '';
        }
    }

    public function clear()
    {
        parent::clear();
        $this->matricula = '';
    }
}