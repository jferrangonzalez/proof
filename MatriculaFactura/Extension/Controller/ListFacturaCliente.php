<?php

namespace FacturaScripts\Plugins\MatriculaFactura\Extension\Controller;



use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class ListFacturaCliente
{
    public function createViews()
    {
        return function() {
            // Añadir el filtro de autocomplete para matrículas
            $this->addFilterAutocomplete(
                'ListFacturaCliente',  // Nombre de la vista
                'matricula',           // Clave del filtro
                'Matrícula',           // Etiqueta del filtro
                'matricula',           // Campo a filtrar
                'facturascli',         // Tabla de la base de datos
                'matricula',           // Campo para el valor (code)
                'matricula'            // Campo para la descripción (description)
            );
        };
    }
}