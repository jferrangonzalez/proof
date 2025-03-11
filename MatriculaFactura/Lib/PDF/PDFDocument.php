<?php
/**
 * Extensión de la clase PDFDocument para añadir la matrícula del vehículo.
 *
 * @author José Ferrán
 */

namespace FacturaScripts\Plugins\MatriculaFactura\Lib\PDF;


use FacturaScripts\Core\Lib\PDF\PDFDocument as ParentClass;

abstract class PDFDocument extends ParentClass
{
    /**
     * Sobrescribe el método insertBusinessDocHeader para añadir la matrícula.
     *
     * @param BusinessDocument $model
     */
    protected function insertBusinessDocHeader($model)
    {
        // Llamamos al método original de la clase padre
        parent::insertBusinessDocHeader($model);

        

        // Añadimos la matrícula si existe
        if (property_exists($model, 'matricula') && !empty($model->matricula)) {
        // Cambiar el rótulo y aumentar el tamaño del texto
            $this->pdf->ezText("\n\nMatrícula de vehículo: " . $model->matricula . "\n", self::FONT_SIZE + 3, ['justification' => 'left']);

        }
    }
}